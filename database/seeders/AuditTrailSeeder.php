<?php
/**
 * Audit Trail System Seeder
 * Phase 6: Deployment & Data Seeding
 * 
 * Usage: php run_migration.php (or manually run the SQL)
 */

require_once __DIR__ . '/../../config/database.php';

class AuditTrailSeeder {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Seed audit trail defaults for all companies
     */
    public function seed() {
        echo "Starting Audit Trail seeder...\n";

        try {
            // 1. Create default alerts for existing companies
            $this->seedDefaultAlerts();
            
            // 2. Create default scheduled reports
            $this->seedScheduledReports();
            
            // 3. Add audit trail settings to system_settings
            $this->seedSystemSettings();
            
            echo "Audit Trail seeding completed successfully!\n";
        } catch (\Exception $e) {
            echo "Error seeding Audit Trail: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Seed default alerts for all companies
     */
    private function seedDefaultAlerts() {
        echo "Creating default alerts...\n";

        $alertService = new \App\Services\AlertService();
        
        // Get all companies
        $stmt = $this->db->query("SELECT id FROM companies WHERE id IS NOT NULL");
        $companies = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($companies as $companyId) {
            try {
                $alertService->createDefaultAlerts($companyId);
                echo "  - Default alerts created for company {$companyId}\n";
            } catch (\Exception $e) {
                echo "  - Warning: Failed to create alerts for company {$companyId}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed scheduled reports for companies
     */
    private function seedScheduledReports() {
        echo "Creating default scheduled reports...\n";

        // Get all companies
        $stmt = $this->db->query("SELECT id FROM companies WHERE id IS NOT NULL");
        $companies = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $defaultReports = [
            [
                'name' => 'Daily Sales Report',
                'type' => 'daily_sales',
                'cron_expr' => '0 2 * * *', // Daily at 2 AM
                'parameters' => [
                    'format' => 'csv',
                    'recipients' => []
                ]
            ],
            [
                'name' => 'Weekly Inventory Report',
                'type' => 'weekly_inventory',
                'cron_expr' => '0 3 * * 1', // Monday at 3 AM
                'parameters' => [
                    'format' => 'xlsx',
                    'recipients' => []
                ]
            ],
            [
                'name' => 'Monthly Profit Report',
                'type' => 'monthly_profit',
                'cron_expr' => '0 4 1 * *', // 1st of month at 4 AM
                'parameters' => [
                    'format' => 'pdf',
                    'recipients' => []
                ]
            ],
            [
                'name' => 'Weekly Backup',
                'type' => 'backup',
                'cron_expr' => '0 1 * * 0', // Sunday at 1 AM
                'parameters' => [
                    'format' => 'json',
                    'recipients' => []
                ]
            ]
        ];

        foreach ($companies as $companyId) {
            foreach ($defaultReports as $report) {
                try {
                    // Check if report already exists
                    $checkStmt = $this->db->prepare("
                        SELECT id FROM scheduled_reports 
                        WHERE company_id = ? AND type = ? AND name = ?
                    ");
                    $checkStmt->execute([$companyId, $report['type'], $report['name']]);
                    
                    if (!$checkStmt->fetch()) {
                        // Calculate next run time (simplified - use cron parser in production)
                        $nextRun = $this->calculateNextRun($report['cron_expr']);
                        
                        $stmt = $this->db->prepare("
                            INSERT INTO scheduled_reports
                            (company_id, name, type, cron_expr, next_run, parameters, enabled)
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([
                            $companyId,
                            $report['name'],
                            $report['type'],
                            $report['cron_expr'],
                            $nextRun,
                            json_encode($report['parameters'])
                        ]);
                        
                        echo "  - Created '{$report['name']}' for company {$companyId}\n";
                    }
                } catch (\Exception $e) {
                    echo "  - Warning: Failed to create report for company {$companyId}: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * Seed system settings for audit trail
     */
    private function seedSystemSettings() {
        echo "Adding audit trail system settings...\n";

        $settings = [
            [
                'key' => 'audit_trail_default_date_range',
                'value' => 'this_month',
                'description' => 'Default date range for audit trail analytics'
            ],
            [
                'key' => 'audit_trail_default_export_format',
                'value' => 'csv',
                'description' => 'Default export format for audit trail reports'
            ],
            [
                'key' => 'audit_trail_backup_frequency',
                'value' => 'weekly',
                'description' => 'Default backup frequency (daily, weekly, monthly)'
            ],
            [
                'key' => 'audit_trail_auto_refresh_interval',
                'value' => '60',
                'description' => 'Auto-refresh interval in seconds for live data'
            ],
            [
                'key' => 'audit_trail_enable_real_time',
                'value' => '1',
                'description' => 'Enable real-time data fetching'
            ],
            [
                'key' => 'audit_trail_report_categories',
                'value' => json_encode(['sales', 'profit', 'loss', 'repairs', 'swaps', 'inventory']),
                'description' => 'Available report categories'
            ]
        ];

        foreach ($settings as $setting) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, description)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([
                    $setting['key'],
                    $setting['value'],
                    $setting['description']
                ]);
                echo "  - Setting '{$setting['key']}' updated\n";
            } catch (\Exception $e) {
                echo "  - Warning: Failed to add setting '{$setting['key']}': " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Calculate next run time from cron expression (simplified)
     */
    private function calculateNextRun($cronExpr) {
        // Very basic implementation
        // For production, use mtdowling/cron-expression library
        $parts = explode(' ', $cronExpr);
        
        if (count($parts) !== 5) {
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        }

        // Parse cron (minute hour day month weekday)
        $minute = $parts[0];
        $hour = $parts[1];
        $day = $parts[2];
        $month = $parts[3];
        $weekday = $parts[4];

        // Simplified: if hour/minute specified, use today or tomorrow
        if ($minute !== '*' && $hour !== '*') {
            $now = new \DateTime();
            $next = clone $now;
            $next->setTime((int)$hour, (int)$minute, 0);
            
            if ($next <= $now) {
                $next->modify('+1 day');
            }
            
            return $next->format('Y-m-d H:i:s');
        }

        // Default to tomorrow
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }
}

// Run seeder if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $seeder = new AuditTrailSeeder();
    $seeder->seed();
}

