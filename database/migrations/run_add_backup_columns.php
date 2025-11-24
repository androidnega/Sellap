<?php
/**
 * Runner for adding backup_type and description columns to backups table
 */

require_once __DIR__ . '/../../config/database.php';

$db = \Database::getInstance()->getConnection();

function logMessage($message, $prefix = '•') {
    echo "{$prefix} {$message}\n";
}

echo "🚀 Running Migration: Add backup_type and description columns\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    logMessage("Database: {$dbName}", '📊');
    echo "\n";

    // Check if columns already exist
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'backups' 
        AND COLUMN_NAME IN ('backup_type', 'description')
    ");
    $stmt->execute([$dbName]);
    $existingColumns = $stmt->fetchColumn();

    if ($existingColumns >= 2) {
        logMessage("✓ Columns 'backup_type' and 'description' already exist in backups table", 'ℹ️');
    } else {
        logMessage("Adding missing columns to backups table...");
        
        // Add backup_type column if it doesn't exist
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'backups' 
            AND COLUMN_NAME = 'backup_type'
        ");
        $stmt->execute([$dbName]);
        if ($stmt->fetchColumn() == 0) {
            $db->exec("
                ALTER TABLE backups 
                ADD COLUMN backup_type VARCHAR(20) DEFAULT 'manual' 
                COMMENT 'Type of backup: manual or automatic'
                AFTER format
            ");
            logMessage("✓ Added 'backup_type' column");
        } else {
            logMessage("ℹ️  Column 'backup_type' already exists");
        }

        // Add description column if it doesn't exist
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'backups' 
            AND COLUMN_NAME = 'description'
        ");
        $stmt->execute([$dbName]);
        if ($stmt->fetchColumn() == 0) {
            $db->exec("
                ALTER TABLE backups 
                ADD COLUMN description TEXT NULL 
                COMMENT 'Optional description or notes about the backup'
                AFTER backup_type
            ");
            logMessage("✓ Added 'description' column");
        } else {
            logMessage("ℹ️  Column 'description' already exists");
        }

        // Add index for backup_type
        try {
            $db->exec("
                ALTER TABLE backups 
                ADD INDEX idx_backup_type (backup_type)
            ");
            logMessage("✓ Added index on 'backup_type' column");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                logMessage("ℹ️  Index 'idx_backup_type' already exists");
            } else {
                throw $e;
            }
        }
    }

    // Update existing backups with automatic marker
    $stmt = $db->query("
        UPDATE backups 
        SET backup_type = 'automatic' 
        WHERE description LIKE '%[AUTOMATIC DAILY BACKUP]%'
        AND backup_type = 'manual'
    ");
    $updatedRows = $stmt->rowCount();
    if ($updatedRows > 0) {
        logMessage("✓ Updated {$updatedRows} existing automatic backups");
    }

    echo "\n✅ Migration completed successfully!\n";
    echo "   • Columns 'backup_type' and 'description' are available\n";
    echo "   • Automatic backup tracking is now enabled\n";
    echo "   • Backup statistics will now display correctly\n";
    echo "\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

