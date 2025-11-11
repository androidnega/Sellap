<?php
/**
 * File Retention Worker - Archives or deletes old audit artifacts
 * 
 * This should be run via cron daily:
 * 0 2 * * * php /path/to/app/Workers/file_retention_worker.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] File Retention Worker started\n";

try {
    $db = \Database::getInstance()->getConnection();
    
    // Retention policy (in days)
    $policies = [
        'audit_logs_retention' => 365 * 3, // 3 years
        'reports_retention' => 365 * 1,   // 1 year
        'export_retention' => 90           // 90 days
    ];

    // Get retention settings from system_settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%retention%'");
    $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    
    foreach ($policies as $key => $defaultDays) {
        $days = isset($settings[$key]) ? (int)$settings[$key] : $defaultDays;
        
        if ($key === 'audit_logs_retention') {
            // Archive old audit logs (mark as archived, don't delete)
            $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
            $stmt = $db->prepare("
                UPDATE audit_logs
                SET indexed_for_search = 2
                WHERE created_at < :cutoff_date
                AND indexed_for_search = 0
            ");
            $stmt->execute(['cutoff_date' => $cutoffDate]);
            $archived = $stmt->rowCount();
            echo "[" . date('Y-m-d H:i:s') . "] Archived {$archived} audit log entries older than {$days} days\n";
            
        } elseif ($key === 'reports_retention') {
            // Delete old report files
            $reportsDir = __DIR__ . '/../../storage/reports';
            if (is_dir($reportsDir)) {
                $cutoffTime = strtotime("-{$days} days");
                $files = glob($reportsDir . '/*');
                $deleted = 0;
                
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoffTime) {
                        unlink($file);
                        $deleted++;
                    }
                }
                echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deleted} report files older than {$days} days\n";
            }
        }
    }

    // Clean up old alert notifications (keep last 1000 per company)
    $stmt = $db->prepare("
        DELETE an FROM alert_notifications an
        INNER JOIN (
            SELECT company_id, id
            FROM alert_notifications
            WHERE company_id = ?
            ORDER BY triggered_at DESC
            LIMIT 999999999 OFFSET 1000
        ) as old
        ON an.id = old.id AND an.company_id = old.company_id
    ");
    
    $stmt = $db->prepare("
        SELECT DISTINCT company_id FROM alert_notifications
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
    foreach ($companies as $companyId) {
        $stmt = $db->prepare("
            DELETE FROM alert_notifications
            WHERE company_id = :company_id
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM alert_notifications
                    WHERE company_id = :company_id
                    ORDER BY triggered_at DESC
                    LIMIT 1000
                ) as keep
            )
        ");
        $stmt->execute(['company_id' => $companyId]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Cleaned {$deleted} old alert notifications for company {$companyId}\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] File Retention Worker completed.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

