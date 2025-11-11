<?php
/**
 * Emergency Data Loss Investigation Script
 * Check what happened to Mhannuellens company data
 */
require_once __DIR__ . '/config/database.php';

$db = \Database::getInstance()->getConnection();

echo "=== DATA LOSS INVESTIGATION FOR MHANNULLENS ===\n\n";

// 1. Check if company still exists
echo "1. Checking if company exists...\n";
$stmt = $db->query("SELECT * FROM companies WHERE name LIKE '%Mhannuellen%' OR id = 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if ($company) {
    echo "   ✓ Company found: ID={$company['id']}, Name={$company['name']}\n\n";
    $companyId = $company['id'];
} else {
    echo "   ✗ Company NOT FOUND!\n\n";
    // Try to find by ID 1
    $stmt = $db->query("SELECT * FROM companies WHERE id = 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        echo "   Found company with ID=1: {$company['name']}\n";
        $companyId = 1;
    } else {
        echo "   No company with ID=1 either!\n";
        exit(1);
    }
}

// 2. Check customer count
echo "2. Checking customer data...\n";
$stmt = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE company_id = ?");
$stmt->execute([$companyId]);
$customerCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Customers for company {$companyId}: {$customerCount['count']}\n";

// 3. Check other data
$tables = ['products', 'pos_sales', 'repairs_new', 'swaps', 'users'];
foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM {$table} WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   {$table}: {$result['count']}\n";
    } catch (\Exception $e) {
        echo "   {$table}: Table doesn't exist or error: {$e->getMessage()}\n";
    }
}

// 4. Check admin_actions (reset logs)
echo "\n3. Checking reset/admin actions...\n";
try {
    $stmt = $db->prepare("
        SELECT * FROM admin_actions 
        WHERE target_company_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($actions)) {
        echo "   No reset actions found in admin_actions table\n";
    } else {
        echo "   Found " . count($actions) . " admin action(s):\n";
        foreach ($actions as $action) {
            echo "   - {$action['action_type']} on {$action['created_at']} - Status: {$action['status']}\n";
            if ($action['row_counts']) {
                $counts = json_decode($action['row_counts'], true);
                echo "     Deleted: " . json_encode($counts) . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   admin_actions table doesn't exist or error: {$e->getMessage()}\n";
}

// 5. Check available backups
echo "\n4. Checking available backups...\n";
$backupDir = __DIR__ . '/storage/backups';
if (is_dir($backupDir)) {
    $backups = [];
    $dirs = scandir($backupDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $path = $backupDir . '/' . $dir;
        if (is_dir($path)) {
            // Check if it's a company backup
            if (strpos($dir, 'company_' . $companyId) !== false || strpos($dir, 'system_') !== false) {
                $manifest = $path . '/manifest.json';
                if (file_exists($manifest)) {
                    $manifestData = json_decode(file_get_contents($manifest), true);
                    $backups[] = [
                        'dir' => $dir,
                        'date' => $manifestData['created_at'] ?? 'unknown',
                        'type' => strpos($dir, 'company_') !== false ? 'company' : 'system'
                    ];
                }
            }
        }
    }
    
    if (empty($backups)) {
        echo "   No backups found in storage/backups/\n";
    } else {
        echo "   Found " . count($backups) . " backup(s):\n";
        foreach ($backups as $backup) {
            echo "   - {$backup['dir']} ({$backup['type']}) - {$backup['date']}\n";
        }
    }
} else {
    echo "   Backup directory doesn't exist!\n";
}

// 6. Check if there's a company backup SQL file
echo "\n5. Checking for SQL backup files...\n";
$sqlBackup = $backupDir . '/company_' . $companyId . '_*/database.sql';
$files = glob($sqlBackup);
if (empty($files)) {
    // Check system backups
    $files = glob($backupDir . '/system_*/database.sql');
}
if (!empty($files)) {
    echo "   Found SQL backup file(s):\n";
    foreach ($files as $file) {
        $size = filesize($file);
        $date = date('Y-m-d H:i:s', filemtime($file));
        echo "   - " . basename(dirname($file)) . "/database.sql ({$size} bytes, {$date})\n";
    }
} else {
    echo "   No SQL backup files found\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "RECOMMENDATION: Check the backups listed above and restore from the most recent one.\n";



