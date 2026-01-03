<?php
/**
 * Quick Fix Script - Upload this to server via File Manager
 * Then access: https://sellapp.store/fix_git_conflict.php
 * This will delete the conflicting test file and allow git pull
 */

$fileToDelete = __DIR__ . '/test_product_query_live.php';
$result = [];

if (file_exists($fileToDelete)) {
    if (unlink($fileToDelete)) {
        $result['status'] = 'success';
        $result['message'] = 'File deleted successfully!';
        $result['file'] = $fileToDelete;
    } else {
        $result['status'] = 'error';
        $result['message'] = 'Could not delete file (permission denied?)';
    }
} else {
    $result['status'] = 'info';
    $result['message'] = 'File does not exist (already deleted?)';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Git Conflict Fix</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 50px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #e8f5e9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
        .error { background: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin: 20px 0; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
        h1 { color: #333; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Git Conflict Fix</h1>
        
        <?php if ($result['status'] === 'success'): ?>
            <div class="success">
                <h2>✅ Success!</h2>
                <p><strong><?= htmlspecialchars($result['message']) ?></strong></p>
                <p>File: <code><?= htmlspecialchars($result['file']) ?></code></p>
            </div>
            
            <div class="info">
                <h3>Next Steps:</h3>
                <ol>
                    <li>Go to <strong>cPanel → Git Version Control</strong></li>
                    <li>Click <strong>"Manage"</strong> on your repository</li>
                    <li>Click <strong>"Pull or Deploy"</strong></li>
                    <li>It should work now! ✅</li>
                </ol>
            </div>
            
        <?php elseif ($result['status'] === 'error'): ?>
            <div class="error">
                <h2>❌ Error</h2>
                <p><?= htmlspecialchars($result['message']) ?></p>
                <p><strong>Solution:</strong> Delete the file manually via File Manager:</p>
                <code>/home3/manuelc8/sellapp.store/test_product_query_live.php</code>
            </div>
            
        <?php else: ?>
            <div class="info">
                <h2>ℹ️ Info</h2>
                <p><?= htmlspecialchars($result['message']) ?></p>
                <p>The file may have already been deleted.</p>
            </div>
        <?php endif; ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>Alternative: Manual Fix</h3>
            <p>If this doesn't work, manually delete via File Manager:</p>
            <ol>
                <li>cPanel → <strong>File Manager</strong></li>
                <li>Navigate to: <code>/home3/manuelc8/sellapp.store/</code></li>
                <li>Find: <code>test_product_query_live.php</code></li>
                <li>Right-click → <strong>Delete</strong></li>
                <li>Then try git pull again</li>
            </ol>
        </div>
        
        <p style="margin-top: 30px; color: #666; font-size: 12px;">
            <strong>Note:</strong> After fixing, you can delete this file (<code>fix_git_conflict.php</code>) too.
        </p>
    </div>
</body>
</html>

