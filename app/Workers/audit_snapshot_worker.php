<?php
/**
 * Audit Snapshot Worker - Creates daily/weekly analytics snapshots
 * Phase 6: Deployment & Data Seeding
 * 
 * This should be run via cron:
 * 0 1 * * * php /path/to/app/Workers/audit_snapshot_worker.php daily
 * 0 2 * * 1 php /path/to/app/Workers/audit_snapshot_worker.php weekly
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AnalyticsService.php';
require_once __DIR__ . '/../Services/ExportService.php';

use App\Services\AnalyticsService;
use App\Services\ExportService;

$snapshotType = $argv[1] ?? 'daily'; // 'daily' or 'weekly'

echo "[" . date('Y-m-d H:i:s') . "] Audit Snapshot Worker started ({$snapshotType})\n";

try {
    $analyticsService = new AnalyticsService();
    $exportService = new ExportService();
    $db = \Database::getInstance()->getConnection();

    // Create snapshots directory
    $snapshotsDir = __DIR__ . '/../../storage/audit_snapshots';
    if (!is_dir($snapshotsDir)) {
        mkdir($snapshotsDir, 0755, true);
    }

    // Get all companies
    $stmt = $db->query("SELECT id FROM companies WHERE id IS NOT NULL");
    $companies = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    $dateFrom = null;
    $dateTo = date('Y-m-d');

    if ($snapshotType === 'daily') {
        $dateFrom = date('Y-m-d', strtotime('-1 day'));
        $dateTo = date('Y-m-d', strtotime('-1 day'));
    } else {
        // Weekly
        $dateFrom = date('Y-m-d', strtotime('monday last week'));
        $dateTo = date('Y-m-d', strtotime('sunday last week'));
    }

    foreach ($companies as $companyId) {
        echo "[" . date('Y-m-d H:i:s') . "] Creating snapshot for company {$companyId}...\n";
        
        try {
            // Get analytics data
            $snapshot = [
                'company_id' => $companyId,
                'snapshot_type' => $snapshotType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'created_at' => date('Y-m-d H:i:s'),
                'sales' => $analyticsService->getSalesStats($companyId, $dateFrom, $dateTo),
                'swaps' => $analyticsService->getSwapStats($companyId, $dateFrom, $dateTo),
                'repairs' => $analyticsService->getRepairStats($companyId, $dateFrom, $dateTo),
                'inventory' => $analyticsService->getInventoryStats($companyId),
                'profit' => $analyticsService->getProfitStats($companyId, $dateFrom, $dateTo)
            ];

            // Save snapshot as JSON
            $companyDir = $snapshotsDir . '/' . $companyId;
            if (!is_dir($companyDir)) {
                mkdir($companyDir, 0755, true);
            }

            $filename = "snapshot_{$snapshotType}_" . date('Ymd', strtotime($dateTo)) . ".json";
            $filepath = $companyDir . '/' . $filename;

            file_put_contents($filepath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            echo "[" . date('Y-m-d H:i:s') . "] Snapshot saved: {$filename} (" . filesize($filepath) . " bytes)\n";

            // Cleanup old snapshots (keep last 30 days for daily, last 12 weeks for weekly)
            $this->cleanupOldSnapshots($companyDir, $snapshotType);

        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERROR creating snapshot for company {$companyId}: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Audit Snapshot Worker completed.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

/**
 * Cleanup old snapshots
 */
function cleanupOldSnapshots($companyDir, $type) {
    $keepDays = $type === 'daily' ? 30 : 84; // 30 days or 12 weeks
    $cutoffTime = strtotime("-{$keepDays} days");
    
    $files = glob($companyDir . "/snapshot_{$type}_*.json");
    $deleted = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
            $deleted++;
        }
    }
    
    if ($deleted > 0) {
        echo "  - Cleaned up {$deleted} old {$type} snapshots\n";
    }
}

