<?php
/**
 * Migration: Add total_value column to swaps table
 * 
 * This migration adds the total_value column to the swaps table if it doesn't exist,
 * and populates it with the cash top-up value (final_price - given_phone_value)
 * for existing swaps.
 * 
 * Usage: php database/migrations/add_total_value_to_swaps.php
 * Or visit: http://localhost/sellapp/database/migrations/add_total_value_to_swaps.php
 */

require_once __DIR__ . '/../../config/database.php';

// Only allow CLI or localhost access
if (php_sapi_name() !== 'cli') {
    $allowed = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']) || 
               (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
    
    if (!$allowed) {
        die("This script can only be run from localhost or command line.");
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>Add total_value to Swaps</title><style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body><div class='container'>";
}

try {
    $db = \Database::getInstance()->getConnection();
    
    echo php_sapi_name() === 'cli' ? "=== Adding total_value to swaps table ===\n\n" : "<h1>Migration: Add total_value to swaps table</h1>";

    // Check if total_value column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM swaps LIKE 'total_value'");
    $columnExists = $checkColumn->rowCount() > 0;
    
    if ($columnExists) {
        echo php_sapi_name() === 'cli' ? "✓ total_value column already exists in swaps table.\n" : "<div class='info'>✓ total_value column already exists in swaps table.</div>";
    } else {
        echo php_sapi_name() === 'cli' ? "Adding total_value column to swaps table...\n" : "<div class='info'>Adding total_value column to swaps table...</div>";
        
        // Add the column
        $db->exec("ALTER TABLE swaps ADD COLUMN total_value DECIMAL(10, 2) DEFAULT 0 AFTER given_phone_value");
        
        echo php_sapi_name() === 'cli' ? "✓ Column added successfully.\n" : "<div class='success'>✓ Column added successfully.</div>";
    }
    
    // Check which columns exist for calculating cash top-up
    $checkFinalPrice = $db->query("SHOW COLUMNS FROM swaps LIKE 'final_price'");
    $checkGivenPhoneValue = $db->query("SHOW COLUMNS FROM swaps LIKE 'given_phone_value'");
    $checkAddedCash = $db->query("SHOW COLUMNS FROM swaps LIKE 'added_cash'");
    $checkCashAdded = $db->query("SHOW COLUMNS FROM swaps LIKE 'cash_added'");
    
    $hasFinalPrice = $checkFinalPrice->rowCount() > 0;
    $hasGivenPhoneValue = $checkGivenPhoneValue->rowCount() > 0;
    $hasAddedCash = $checkAddedCash->rowCount() > 0;
    $hasCashAdded = $checkCashAdded->rowCount() > 0;
    
    // Count swaps that need total_value populated
    $countQuery = "SELECT COUNT(*) as count FROM swaps WHERE total_value = 0 OR total_value IS NULL";
    $countResult = $db->query($countQuery)->fetch(PDO::FETCH_ASSOC);
    $swapsToUpdate = intval($countResult['count'] ?? 0);
    
    if ($swapsToUpdate > 0) {
        $msg = "Found {$swapsToUpdate} swap(s) that need total_value populated.";
        echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
        
        // Determine how to calculate total_value
        if ($hasAddedCash) {
            // Use added_cash directly
            $msg = "Using added_cash column to populate total_value...";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
            $updateQuery = "
                UPDATE swaps 
                SET total_value = COALESCE(added_cash, 0)
                WHERE total_value = 0 OR total_value IS NULL
            ";
        } elseif ($hasCashAdded) {
            // Use cash_added
            $msg = "Using cash_added column to populate total_value...";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
            $updateQuery = "
                UPDATE swaps 
                SET total_value = COALESCE(cash_added, 0)
                WHERE total_value = 0 OR total_value IS NULL
            ";
        } elseif ($hasFinalPrice && $hasGivenPhoneValue) {
            // Calculate from final_price - given_phone_value
            $msg = "Calculating total_value from final_price - given_phone_value (cash top-up)...";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
            $updateQuery = "
                UPDATE swaps 
                SET total_value = GREATEST(0, COALESCE(final_price, 0) - COALESCE(given_phone_value, 0))
                WHERE total_value = 0 OR total_value IS NULL
            ";
        } elseif ($hasFinalPrice) {
            // Use final_price as fallback
            $msg = "Using final_price as total_value (given_phone_value not available)...";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='warning'>" . $msg . "</div>";
            $updateQuery = "
                UPDATE swaps 
                SET total_value = COALESCE(final_price, 0)
                WHERE total_value = 0 OR total_value IS NULL
            ";
        } else {
            $msg = "Error: Cannot determine how to calculate total_value. No suitable columns found.";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='error'>" . $msg . "</div>";
            exit;
        }
        
        // Execute the update (sets total_value to cash top-up)
        $updated = $db->exec($updateQuery);
        
        if ($updated !== false) {
            $msg = "✓ Updated {$updated} swap(s) with cash top-up as initial total_value.";
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='success'>" . $msg . "</div>";
            
            // Now add resold prices to total_value for swaps with resold items
            // Check if swapped_items table exists
            $checkSwappedItems = $db->query("SHOW TABLES LIKE 'swapped_items'");
            $hasSwappedItems = $checkSwappedItems->rowCount() > 0;
            
            if ($hasSwappedItems) {
                $msg = "Adding resold prices to total_value for swaps with resold items...";
                echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
                
                // Update total_value to include resold prices
                // Total Value = Cash Top-up + Sum of Resold Prices
                $resoldUpdateQuery = "
                    UPDATE swaps s
                    INNER JOIN (
                        SELECT swap_id, SUM(resell_price) as total_resold
                        FROM swapped_items
                        WHERE status = 'sold' AND resell_price > 0
                        GROUP BY swap_id
                    ) si ON s.id = si.swap_id
                    SET s.total_value = s.total_value + si.total_resold
                ";
                
                $resoldUpdated = $db->exec($resoldUpdateQuery);
                
                if ($resoldUpdated !== false) {
                    $msg = "✓ Updated {$resoldUpdated} swap(s) to include resold prices in total_value.";
                    echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='success'>" . $msg . "</div>";
                } else {
                    $error = $db->errorInfo();
                    $msg = "⚠ Could not update resold prices. Error: " . ($error[2] ?? 'Unknown error');
                    echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='warning'>" . htmlspecialchars($msg) . "</div>";
                }
            } else {
                $msg = "Note: swapped_items table not found. Skipping resold price updates.";
                echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
            }
        } else {
            $error = $db->errorInfo();
            $msg = "✗ Failed to update swaps. Error: " . ($error[2] ?? 'Unknown error');
            echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='error'>" . htmlspecialchars($msg) . "</div>";
        }
    } else {
        $msg = "All swaps already have total_value set.";
        echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
        
        // Still check and update resold prices if needed
        $checkSwappedItems = $db->query("SHOW TABLES LIKE 'swapped_items'");
        $hasSwappedItems = $checkSwappedItems->rowCount() > 0;
        
        if ($hasSwappedItems) {
            // Check if any swaps need resold prices added
            $checkResoldQuery = "
                SELECT COUNT(*) as count
                FROM swaps s
                INNER JOIN (
                    SELECT swap_id, SUM(resell_price) as total_resold
                    FROM swapped_items
                    WHERE status = 'sold' AND resell_price > 0
                    GROUP BY swap_id
                ) si ON s.id = si.swap_id
                WHERE s.total_value < (GREATEST(0, COALESCE(s.final_price, 0) - COALESCE(s.given_phone_value, 0)) + si.total_resold)
            ";
            $needsUpdate = $db->query($checkResoldQuery)->fetch(PDO::FETCH_ASSOC);
            
            if (intval($needsUpdate['count'] ?? 0) > 0) {
                $msg = "Updating total_value to include resold prices for swaps with resold items...";
                echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='info'>" . $msg . "</div>";
                
                $resoldUpdateQuery = "
                    UPDATE swaps s
                    INNER JOIN (
                        SELECT swap_id, SUM(resell_price) as total_resold
                        FROM swapped_items
                        WHERE status = 'sold' AND resell_price > 0
                        GROUP BY swap_id
                    ) si ON s.id = si.swap_id
                    SET s.total_value = GREATEST(0, COALESCE(s.final_price, 0) - COALESCE(s.given_phone_value, 0)) + si.total_resold
                ";
                
                $resoldUpdated = $db->exec($resoldUpdateQuery);
                if ($resoldUpdated !== false) {
                    $msg = "✓ Updated {$resoldUpdated} swap(s) to include resold prices.";
                    echo php_sapi_name() === 'cli' ? $msg . "\n" : "<div class='success'>" . $msg . "</div>";
                }
            }
        }
    }
    
    // Show summary of updated swaps
    $title = "Summary";
    echo php_sapi_name() === 'cli' ? "\n=== " . $title . " ===\n" : "<h3>" . $title . "</h3>";
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_swaps,
            SUM(CASE WHEN total_value > 0 THEN 1 ELSE 0 END) as swaps_with_value,
            SUM(total_value) as total_revenue,
            AVG(total_value) as avg_value
        FROM swaps
    ";
    $summary = $db->query($summaryQuery)->fetch(PDO::FETCH_ASSOC);
    
    if (php_sapi_name() === 'cli') {
        echo "Total swaps: " . intval($summary['total_swaps'] ?? 0) . "\n";
        echo "Swaps with total_value > 0: " . intval($summary['swaps_with_value'] ?? 0) . "\n";
        echo "Total revenue (sum of total_value): ₵" . number_format(floatval($summary['total_revenue'] ?? 0), 2) . "\n";
        echo "Average total_value: ₵" . number_format(floatval($summary['avg_value'] ?? 0), 2) . "\n";
    } else {
        echo "<ul>";
        echo "<li>Total swaps: " . intval($summary['total_swaps'] ?? 0) . "</li>";
        echo "<li>Swaps with total_value > 0: " . intval($summary['swaps_with_value'] ?? 0) . "</li>";
        echo "<li>Total revenue (sum of total_value): ₵" . number_format(floatval($summary['total_revenue'] ?? 0), 2) . "</li>";
        echo "<li>Average total_value: ₵" . number_format(floatval($summary['avg_value'] ?? 0), 2) . "</li>";
        echo "</ul>";
    }
    
    // Show sample of updated swaps
    $sampleQuery = "
        SELECT 
            id,
            unique_id,
            final_price,
            given_phone_value,
            total_value,
            created_at
        FROM swaps
        ORDER BY id DESC
        LIMIT 10
    ";
    $samples = $db->query($sampleQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($samples)) {
        $title = "Sample Swaps (Last 10)";
        echo php_sapi_name() === 'cli' ? "\n=== " . $title . " ===\n" : "<h3>" . $title . "</h3>";
        
        if (php_sapi_name() === 'cli') {
            foreach ($samples as $swap) {
                echo "ID: {$swap['id']}, Unique ID: {$swap['unique_id']}, ";
                echo "Final Price: ₵" . number_format(floatval($swap['final_price'] ?? 0), 2) . ", ";
                echo "Given Phone Value: ₵" . number_format(floatval($swap['given_phone_value'] ?? 0), 2) . ", ";
                echo "Total Value: ₵" . number_format(floatval($swap['total_value'] ?? 0), 2) . ", ";
                echo "Created: {$swap['created_at']}\n";
            }
        } else {
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Unique ID</th>";
            echo "<th>Final Price</th>";
            echo "<th>Given Phone Value</th>";
            echo "<th>Total Value</th>";
            echo "<th>Created At</th>";
            echo "</tr>";
            
            foreach ($samples as $swap) {
                echo "<tr>";
                echo "<td>{$swap['id']}</td>";
                echo "<td>{$swap['unique_id']}</td>";
                echo "<td>₵" . number_format(floatval($swap['final_price'] ?? 0), 2) . "</td>";
                echo "<td>₵" . number_format(floatval($swap['given_phone_value'] ?? 0), 2) . "</td>";
                echo "<td><strong>₵" . number_format(floatval($swap['total_value'] ?? 0), 2) . "</strong></td>";
                echo "<td>{$swap['created_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    }
    
    $msg1 = "✓ Migration completed successfully!";
    $msg2 = "You can now use the check_and_fix_swap_revenue.php script to track resold items.";
    
    if (php_sapi_name() === 'cli') {
        echo "\n" . $msg1 . "\n";
        echo $msg2 . "\n";
    } else {
        echo "<hr>";
        echo "<div class='success'><strong>" . $msg1 . "</strong></div>";
        echo "<div class='info'>" . $msg2 . "</div>";
        echo "</div></body></html>";
    }
    
} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo $errorMsg . "\n";
        echo $e->getTraceAsString() . "\n";
    } else {
        echo "<div class='error'>" . htmlspecialchars($errorMsg) . "</div>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div></body></html>";
    }
}

