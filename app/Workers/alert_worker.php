<?php
/**
 * Alert Worker - Runs periodically to check and trigger alerts
 * 
 * This should be run via cron every minute for critical alerts:
 * * * * * * php /path/to/app/Workers/alert_worker.php
 * 
 * Or every 5 minutes for non-critical:
 * *\/5 * * * * php /path/to/app/Workers/alert_worker.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/AlertService.php';

use App\Services\AlertService;

// Load configuration
$config = [
    'run_critical_only' => $argv[1] === '--critical-only' ?? false,
    'company_id' => $argv[2] ?? null // Optional: run for specific company only
];

echo "[" . date('Y-m-d H:i:s') . "] Alert Worker started\n";

try {
    $alertService = new AlertService();
    $db = \Database::getInstance()->getConnection();

    // Get all companies (or specific one)
    if ($config['company_id']) {
        $companies = [['id' => $config['company_id']]];
    } else {
        $stmt = $db->query("SELECT DISTINCT id FROM companies WHERE id IS NOT NULL");
        $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $totalTriggered = 0;
    foreach ($companies as $company) {
        $companyId = $company['id'];
        
        // Check if company has any enabled alerts
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM alerts 
            WHERE company_id = :company_id AND enabled = 1
        ");
        $stmt->execute(['company_id' => $companyId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ((int)($result['count'] ?? 0) === 0) {
            continue; // Skip companies with no alerts
        }

        $triggered = $alertService->checkAndTrigger($companyId);
        
        if (!empty($triggered)) {
            $totalTriggered += count($triggered);
            foreach ($triggered as $alert) {
                echo "[" . date('Y-m-d H:i:s') . "] Company {$companyId}: Alert '{$alert['key']}' triggered (severity: {$alert['severity']})\n";
            }
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Alert Worker completed. Triggered {$totalTriggered} alerts.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

