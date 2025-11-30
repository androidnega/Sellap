<?php
// Email Logs View
$emailLogs = $emailLogs ?? [];
$companies = $companies ?? [];
$stats = $stats ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? [];
?>

<div class="w-full">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Email Logs</h1>
        <p class="text-sm text-gray-600 mt-1">View all emails sent by the system (automatic and manual)</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div class="text-sm text-gray-600">Total Emails</div>
            <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['total'] ?? 0) ?></div>
        </div>
        <div class="bg-green-50 rounded-lg shadow p-4 border border-green-200">
            <div class="text-sm text-green-600">Sent</div>
            <div class="text-2xl font-bold text-green-700"><?= number_format($stats['sent'] ?? 0) ?></div>
        </div>
        <div class="bg-red-50 rounded-lg shadow p-4 border border-red-200">
            <div class="text-sm text-red-600">Failed</div>
            <div class="text-2xl font-bold text-red-700"><?= number_format($stats['failed'] ?? 0) ?></div>
        </div>
        <div class="bg-blue-50 rounded-lg shadow p-4 border border-blue-200">
            <div class="text-sm text-blue-600">Monthly Reports</div>
            <div class="text-2xl font-bold text-blue-700"><?= number_format($stats['monthly_reports'] ?? 0) ?></div>
        </div>
        <div class="bg-purple-50 rounded-lg shadow p-4 border border-purple-200">
            <div class="text-sm text-purple-600">Backup Emails</div>
            <div class="text-2xl font-bold text-purple-700"><?= number_format($stats['backups'] ?? 0) ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" action="<?= BASE_URL_PATH ?>/dashboard/email-logs" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Email Type</label>
                <select name="email_type" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="">All Types</option>
                    <option value="automatic" <?= ($filters['email_type'] ?? '') === 'automatic' ? 'selected' : '' ?>>Automatic</option>
                    <option value="manual" <?= ($filters['email_type'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
                    <option value="test" <?= ($filters['email_type'] ?? '') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="monthly_report" <?= ($filters['email_type'] ?? '') === 'monthly_report' ? 'selected' : '' ?>>Monthly Report</option>
                    <option value="backup" <?= ($filters['email_type'] ?? '') === 'backup' ? 'selected' : '' ?>>Backup</option>
                    <option value="notification" <?= ($filters['email_type'] ?? '') === 'notification' ? 'selected' : '' ?>>Notification</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="">All Status</option>
                    <option value="sent" <?= ($filters['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Company</label>
                <select name="company_id" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= $company['id'] ?>" <?= ($filters['company_id'] ?? '') == $company['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                       placeholder="Email or subject..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                    <i class="fas fa-filter mr-1"></i>Filter
                </button>
                <a href="<?= BASE_URL_PATH ?>/dashboard/email-logs" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 text-sm">
                    <i class="fas fa-times mr-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Email Logs Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($emailLogs)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No email logs found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($emailLogs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= date('Y-m-d H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= htmlspecialchars($log['recipient_email']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= htmlspecialchars($log['subject']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?= $log['email_type'] === 'monthly_report' ? 'bg-blue-100 text-blue-800' : 
                                            ($log['email_type'] === 'backup' ? 'bg-purple-100 text-purple-800' : 
                                            ($log['email_type'] === 'test' ? 'bg-yellow-100 text-yellow-800' : 
                                            ($log['email_type'] === 'automatic' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'))) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $log['email_type'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($log['status'] === 'sent'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>Sent
                                        </span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($log['company_name'] ?? 'N/A') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php if ($log['user_name']): ?>
                                        <?= htmlspecialchars($log['user_name']) ?>
                                        <?php if ($log['role']): ?>
                                            <span class="text-xs text-gray-500">(<?= ucfirst($log['role']) ?>)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($log['status'] === 'failed' && !empty($log['error_message'])): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-2 bg-red-50 text-xs text-red-700">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Error: <?= htmlspecialchars($log['error_message']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?= (($pagination['page'] - 1) * $pagination['limit']) + 1 ?> to 
                    <?= min($pagination['page'] * $pagination['limit'], $pagination['total']) ?> of 
                    <?= number_format($pagination['total']) ?> results
                </div>
                <div class="flex gap-2">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="?page=<?= $pagination['page'] - 1 ?><?= !empty($filters) ? '&' . http_build_query($filters) : '' ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="?page=<?= $pagination['page'] + 1 ?><?= !empty($filters) ? '&' . http_build_query($filters) : '' ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-md text-sm hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

