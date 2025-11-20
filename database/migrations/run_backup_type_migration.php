<?php
/**
 * Run backup_type column migration
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM backups LIKE 'backup_type'");
    
    if ($stmt->rowCount() == 0) {
        // Read migration file
        $migrationFile = __DIR__ . '/add_backup_type_column.sql';
        
        if (!file_exists($migrationFile)) {
            die("Migration file not found: {$migrationFile}\n");
        }
        
        $sql = file_get_contents($migrationFile);
        
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    // Replace IF NOT EXISTS with manual check (MySQL doesn't support IF NOT EXISTS for ALTER TABLE)
                    if (stripos($statement, 'ADD COLUMN IF NOT EXISTS') !== false) {
                        $statement = str_ireplace('IF NOT EXISTS', '', $statement);
                    }
                    if (stripos($statement, 'ADD INDEX IF NOT EXISTS') !== false) {
                        $statement = str_ireplace('IF NOT EXISTS', '', $statement);
                    }
                    
                    $db->exec($statement);
                } catch (\PDOException $e) {
                    // Ignore "duplicate column" or "duplicate key" errors
                    if (strpos($e->getMessage(), 'Duplicate column') === false && 
                        strpos($e->getMessage(), 'Duplicate key') === false) {
                        throw $e;
                    }
                }
            }
        }
        
        echo "✓ Backup type column added successfully!\n";
    } else {
        echo "✓ Backup type column already exists.\n";
    }
    
    // Verify column
    $stmt = $db->query("DESCRIBE backups");
    $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $hasBackupType = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'backup_type') {
            $hasBackupType = true;
            break;
        }
    }
    
    if ($hasBackupType) {
        echo "✓ Verified: backup_type column exists\n";
    } else {
        echo "✗ Warning: backup_type column not found after migration\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

