<?php
$log = $GLOBALS['log'] ?? [];
$BASE = BASE_URL_PATH;
?>

<div class="p-3 sm:p-4 pb-4 max-w-full">
    <!-- Header -->
    <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">Audit Trail Details</h1>
            <p class="text-xs sm:text-sm text-gray-600 mt-1">Event ID: #<?= htmlspecialchars($log['id'] ?? 'N/A') ?></p>
        </div>
        <a href="<?= $BASE ?>/dashboard/audit-trail" class="inline-flex items-center px-3 sm:px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-xs sm:text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Back to Audit Trail
        </a>
    </div>

    <!-- Main Content -->
    <div>
        <!-- Event Details -->
        <div class="mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200">
            <h2 class="text-base sm:text-lg lg:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">Event Information</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 lg:gap-6">
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-600 mb-1">Event Type</label>
                    <div class="text-sm sm:text-base font-semibold text-gray-900">
                        <span class="inline-block px-2 py-1 rounded text-xs font-medium bg-gray-100">
                            <?= htmlspecialchars($log['event_type'] ?? '-') ?>
                        </span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-600 mb-1">Timestamp</label>
                    <div class="text-sm sm:text-base text-gray-900">
                        <?= !empty($log['created_at']) ? date('F j, Y, g:i a', strtotime($log['created_at'])) : '-' ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-600 mb-1">User</label>
                    <div class="text-sm sm:text-base text-gray-900">
                        <?= htmlspecialchars($log['user_name'] ?? $log['user_full_name'] ?? '-') ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs sm:text-sm font-medium text-gray-600 mb-1">IP Address</label>
                    <div class="text-sm sm:text-base text-gray-900 font-mono">
                        <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs sm:text-sm font-medium text-gray-600 mb-1">Entity</label>
                    <div class="text-sm sm:text-base text-gray-900">
                        <?php if (!empty($log['entity_type']) && !empty($log['entity_id'])): ?>
                            <span class="font-semibold"><?= htmlspecialchars($log['entity_type']) ?></span>
                            <span class="text-gray-600">#<?= htmlspecialchars($log['entity_id']) ?></span>
                        <?php else: ?>
                            <span class="text-gray-500">-</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payload Details -->
        <div class="mb-4 sm:mb-6">
            <h2 class="text-base sm:text-lg lg:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">Event Payload</h2>
            <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                <?php
                $payload = $log['payload'] ?? [];
                
                // If payload is a JSON string, decode it
                if (is_string($payload)) {
                    $payload = json_decode($payload, true) ?? [];
                }
                
                // If payload is empty, show message
                if (empty($payload)) {
                    echo '<p class="text-sm text-gray-500 italic">No payload data available</p>';
                } else {
                    // Display payload in a readable format
                    echo '<div class="space-y-3">';
                    foreach ($payload as $key => $value) {
                        // Skip internal signature fields or show them differently
                        if (strpos($key, '_signature') !== false) {
                            continue; // Skip signature fields
                        }
                        
                        // Format the key (convert snake_case to Title Case)
                        $formattedKey = ucwords(str_replace('_', ' ', $key));
                        
                        // Format the value
                        $formattedValue = '';
                        if (is_array($value)) {
                            $formattedValue = '<span class="text-gray-500 italic">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</span>';
                        } elseif (is_bool($value)) {
                            $formattedValue = '<span class="font-medium">' . ($value ? 'Yes' : 'No') . '</span>';
                        } elseif (is_null($value)) {
                            $formattedValue = '<span class="text-gray-400 italic">null</span>';
                        } elseif (empty($value)) {
                            $formattedValue = '<span class="text-gray-400 italic">(empty)</span>';
                        } else {
                            $formattedValue = htmlspecialchars($value);
                        }
                        
                        echo '<div class="border-b border-gray-200 pb-2 last:border-b-0">';
                        echo '<div class="text-xs sm:text-sm font-semibold text-gray-700 mb-1">' . htmlspecialchars($formattedKey) . '</div>';
                        echo '<div class="text-sm sm:text-base text-gray-900 pl-2">' . $formattedValue . '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

