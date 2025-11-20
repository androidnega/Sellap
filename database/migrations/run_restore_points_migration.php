<?php
/**
 * Run restore_points table migration
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    // Check if table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'restore_points'");
    
    if ($checkTable->rowCount() == 0) {
        // Read migration file
        $migrationFile = __DIR__ . '/create_restore_points_table.sql';
        
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
                    $db->exec($statement);
                } catch (\PDOException $e) {
                    // Ignore "table already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }
        
        echo "✓ Restore points table created successfully!\n";
    } else {
        echo "✓ Restore points table already exists.\n";
    }
    
    // Verify table structure
    $stmt = $db->query("DESCRIBE restore_points");
    $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "✓ Table has " . count($columns) . " columns\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

