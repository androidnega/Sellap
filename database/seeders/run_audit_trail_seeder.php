<?php
/**
 * Run Audit Trail Seeder
 * Usage: php database/seeders/run_audit_trail_seeder.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AuditTrailSeeder.php';

try {
    $seeder = new AuditTrailSeeder();
    $seeder->seed();
    echo "\n✅ Audit Trail seeder completed successfully!\n";
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

