<?php
// Generic Migration Result Page
$logs = $GLOBALS['migration_logs'] ?? [];
$status = $GLOBALS['migration_status'] ?? 'success';
$error = $GLOBALS['migration_error'] ?? null;
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL_PATH ?>/dashboard/tools" class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center mb-4">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Migration Tools
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Migration Result</h1>
    </div>

    <?php if ($status === 'success'): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <div class="flex items-center mb-4">
            <svg class="w-8 h-8 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-2xl font-bold text-green-900">Migration Successful!</h2>
        </div>
        <p class="text-green-800">The migration has been completed successfully.</p>
    </div>
    <?php else: ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
        <div class="flex items-center mb-4">
            <svg class="w-8 h-8 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-2xl font-bold text-red-900">Migration Failed</h2>
        </div>
        <?php if ($error): ?>
        <p class="text-red-800 font-medium mb-2">Error:</p>
        <pre class="text-red-700 bg-red-100 p-3 rounded overflow-x-auto text-sm"><?= htmlspecialchars($error) ?></pre>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Migration Log</h3>
        
        <?php if (empty($logs)): ?>
        <p class="text-gray-500">No logs available.</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($logs as $log): ?>
            <div class="flex items-start p-3 rounded <?php
                switch ($log['type']) {
                    case 'success':
                        echo 'bg-green-50 border border-green-200';
                        break;
                    case 'error':
                        echo 'bg-red-50 border border-red-200';
                        break;
                    case 'warning':
                        echo 'bg-yellow-50 border border-yellow-200';
                        break;
                    default:
                        echo 'bg-blue-50 border border-blue-200';
                }
            ?>">
                <?php
                switch ($log['type']) {
                    case 'success':
                        echo '<svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
                        break;
                    case 'error':
                        echo '<svg class="w-5 h-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
                        break;
                    case 'warning':
                        echo '<svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
                        break;
                    default:
                        echo '<svg class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>';
                }
                ?>
                <p class="text-sm <?php
                    switch ($log['type']) {
                        case 'success':
                            echo 'text-green-800';
                            break;
                        case 'error':
                            echo 'text-red-800';
                            break;
                        case 'warning':
                            echo 'text-yellow-800';
                            break;
                        default:
                            echo 'text-blue-800';
                    }
                ?>"><?= htmlspecialchars($log['message']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <a href="<?= BASE_URL_PATH ?>/dashboard/tools" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition">
            Back to Tools
        </a>
        <?php if ($status === 'success'): ?>
        <a href="<?= BASE_URL_PATH ?>/dashboard/backup" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
            Go to Backup Page
        </a>
        <?php endif; ?>
    </div>
</div>

