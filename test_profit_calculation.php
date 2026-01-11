<?php
/**
 * Profit Calculation Test & Diagnostic Tool
 * Tests profit calculations across different time periods
 * 
 * Usage: Run this from command line or browser
 * CLI: php test_profit_calculation.php
 * Browser: https://sellapp.store/test_profit_calculation.php
 */

// Load configuration
require_once __DIR__ . '/config/database.php';

// Test configuration
$TEST_COMPANY_ID = 1; // Change this to your company ID
$DEBUG = true;

// Output helper
function output($message, $level = 'INFO') {
    $colors = [
        'INFO' => "\033[0;36m",
        'SUCCESS' => "\033[0;32m",
        'ERROR' => "\033[0;31m",
        'WARNING' => "\033[0;33m",
        'RESET' => "\033[0m"
    ];
    
    $isCLI = php_sapi_name() === 'cli';
    
    if ($isCLI) {
        echo $colors[$level] . "[$level] " . $colors['RESET'] . $message . PHP_EOL;
    } else {
        $colorMap = [
            'INFO' => 'blue',
            'SUCCESS' => 'green',
            'ERROR' => 'red',
            'WARNING' => 'orange'
        ];
        echo "<div style='color: {$colorMap[$level]}; padding: 5px; margin: 2px 0;'><strong>[$level]</strong> $message</div>";
    }
}

function formatCurrency($amount) {
    return '₵' . number_format($amount, 2);
}

// HTML header for browser
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Profit Calculation Test</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
            h2 { color: #666; margin-top: 30px; }
            .test-result { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
            .pass { border-left-color: #4CAF50; }
            .fail { border-left-color: #f44336; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #4CAF50; color: white; }
            .metric { font-size: 24px; font-weight: bold; }
            .good { color: #4CAF50; }
            .bad { color: #f44336; }
        </style>
    </head>
    <body>
    <div class="container">
    <h1>🧪 Profit Calculation Test & Diagnostic Tool</h1>
    <p><strong>Company ID:</strong> ' . $TEST_COMPANY_ID . ' | <strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
}

output("Starting Profit Calculation Tests...", 'INFO');
output("Company ID: $TEST_COMPANY_ID", 'INFO');

try {
    $db = Database::getInstance()->getConnection();
    
    // Test 1: Check if products table exists and has cost data
    output("Test 1: Checking Products Table Structure", 'INFO');
    
    $checkProductsNew = $db->query("SHOW TABLES LIKE 'products_new'");
    $productsTable = ($checkProductsNew && $checkProductsNew->rowCount() > 0) ? 'products_new' : 'products';
    output("Using table: $productsTable", 'SUCCESS');
    
    // Check cost columns
    $checkCostPrice = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost_price'");
    $checkCost = $db->query("SHOW COLUMNS FROM {$productsTable} LIKE 'cost'");
    $hasCostPrice = $checkCostPrice->rowCount() > 0;
    $hasCost = $checkCost->rowCount() > 0;
    
    if ($hasCostPrice) {
        $costColumn = 'COALESCE(p.cost_price, p.cost, 0)';
        output("Cost column found: cost_price (with fallback to cost)", 'SUCCESS');
    } elseif ($hasCost) {
        $costColumn = 'COALESCE(p.cost, 0)';
        output("Cost column found: cost", 'SUCCESS');
    } else {
        $costColumn = '0';
        output("No cost column found! All costs will be 0", 'ERROR');
    }
    
    // Test 2: Check sample products with costs
    output("\nTest 2: Sample Products Cost Data", 'INFO');
    
    // Build dynamic SELECT based on available columns
    $selectColumns = "id, name";
    if ($hasCostPrice) {
        $selectColumns .= ", COALESCE(cost_price, 0) as cost_price";
    }
    if ($hasCost) {
        $selectColumns .= ", COALESCE(cost, 0) as cost";
    }
    $selectColumns .= ", COALESCE(price, 0) as price";
    
    $sampleProducts = $db->prepare("
        SELECT {$selectColumns}
        FROM {$productsTable}
        WHERE company_id = ?
        LIMIT 5
    ");
    $sampleProducts->execute([$TEST_COMPANY_ID]);
    $products = $sampleProducts->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        output("Found " . count($products) . " sample products", 'SUCCESS');
        if (php_sapi_name() !== 'cli') {
            echo "<table><tr><th>ID</th><th>Name</th>";
            if ($hasCostPrice) echo "<th>Cost Price</th>";
            if ($hasCost) echo "<th>Cost</th>";
            echo "<th>Selling Price</th></tr>";
            
            foreach ($products as $p) {
                echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td>";
                if ($hasCostPrice) echo "<td>" . formatCurrency($p['cost_price'] ?? 0) . "</td>";
                if ($hasCost) echo "<td>" . formatCurrency($p['cost'] ?? 0) . "</td>";
                echo "<td>" . formatCurrency($p['price']) . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        output("No products found for company $TEST_COMPANY_ID", 'WARNING');
    }
    
    // Test 3: Test OLD query (buggy)
    output("\nTest 3: OLD Query (Buggy - Nested SUM)", 'WARNING');
    
    $oldQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(ps.final_amount), 0) as revenue,
            COALESCE(SUM(
                (SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0)
                 FROM pos_sale_items psi 
                 LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                 WHERE psi.pos_sale_id = ps.id AND p.id IS NOT NULL)
            ), 0) as cost
        FROM pos_sales ps
        WHERE ps.company_id = ? 
        AND DATE(ps.created_at) = CURDATE()
        AND ps.swap_id IS NULL
    ");
    $oldQuery->execute([$TEST_COMPANY_ID]);
    $oldResult = $oldQuery->fetch(PDO::FETCH_ASSOC);
    
    $oldRevenue = floatval($oldResult['revenue'] ?? 0);
    $oldCost = floatval($oldResult['cost'] ?? 0);
    $oldProfit = $oldRevenue - $oldCost;
    
    output("OLD Query Results (Today):", 'WARNING');
    output("  Revenue: " . formatCurrency($oldRevenue), 'INFO');
    output("  Cost: " . formatCurrency($oldCost), 'INFO');
    output("  Profit: " . formatCurrency($oldProfit), 'INFO');
    
    if ($oldCost > $oldRevenue && $oldRevenue > 0) {
        output("  ⚠️ ISSUE DETECTED: Cost exceeds revenue!", 'ERROR');
    }
    
    // Test 4: Test NEW query (fixed)
    output("\nTest 4: NEW Query (Fixed - LEFT JOIN)", 'SUCCESS');
    
    $newQuery = $db->prepare("
        SELECT 
            COALESCE(SUM(ps.final_amount), 0) as revenue,
            COALESCE(SUM(psi_cost.total_cost), 0) as cost
        FROM pos_sales ps
        LEFT JOIN (
            SELECT 
                psi.pos_sale_id,
                SUM(psi.quantity * {$costColumn}) as total_cost
            FROM pos_sale_items psi 
            LEFT JOIN {$productsTable} p ON psi.item_id = p.id
            WHERE p.id IS NOT NULL AND p.company_id = ?
            GROUP BY psi.pos_sale_id
        ) psi_cost ON psi_cost.pos_sale_id = ps.id
        WHERE ps.company_id = ?
        AND DATE(ps.created_at) = CURDATE()
        AND ps.swap_id IS NULL
    ");
    $newQuery->execute([$TEST_COMPANY_ID, $TEST_COMPANY_ID]);
    $newResult = $newQuery->fetch(PDO::FETCH_ASSOC);
    
    $newRevenue = floatval($newResult['revenue'] ?? 0);
    $newCost = floatval($newResult['cost'] ?? 0);
    $newProfit = $newRevenue - $newCost;
    
    output("NEW Query Results (Today):", 'SUCCESS');
    output("  Revenue: " . formatCurrency($newRevenue), 'INFO');
    output("  Cost: " . formatCurrency($newCost), 'INFO');
    output("  Profit: " . formatCurrency($newProfit), 'INFO');
    
    if ($newCost <= $newRevenue || $newRevenue == 0) {
        output("  ✓ LOOKS GOOD: Cost is reasonable", 'SUCCESS');
    } else {
        output("  ⚠️ ISSUE: Cost still exceeds revenue", 'ERROR');
    }
    
    // Test 5: Compare the difference
    output("\nTest 5: Comparison", 'INFO');
    
    $costDiff = $oldCost - $newCost;
    $profitDiff = $newProfit - $oldProfit;
    
    output("  Cost Difference: " . formatCurrency($costDiff) . " (" . ($costDiff > 0 ? "OLD was inflated" : "NEW is higher") . ")", 'INFO');
    output("  Profit Difference: " . formatCurrency($profitDiff) . " (" . ($profitDiff > 0 ? "NEW is better" : "OLD was better") . ")", 'INFO');
    
    if ($costDiff > 0) {
        $percentInflation = ($costDiff / max($newCost, 1)) * 100;
        output("  🔴 OLD query inflated cost by " . round($percentInflation, 2) . "%", 'ERROR');
    }
    
    // Test 6: Test across different periods
    output("\nTest 6: Testing All Time Periods", 'INFO');
    
    $periods = [
        'Today' => [
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d')
        ],
        'This Week' => [
            'from' => date('Y-m-d', strtotime('monday this week')),
            'to' => date('Y-m-d')
        ],
        'This Month' => [
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d')
        ],
        'All Time' => [
            'from' => '2020-01-01',
            'to' => date('Y-m-d')
        ]
    ];
    
    if (php_sapi_name() !== 'cli') {
        echo "<h2>📊 Profit Calculation Across Time Periods</h2>";
        echo "<table>
                <tr>
                    <th>Period</th>
                    <th>Date Range</th>
                    <th>Revenue</th>
                    <th>Cost (OLD)</th>
                    <th>Cost (NEW)</th>
                    <th>Profit (NEW)</th>
                    <th>Status</th>
                </tr>";
    }
    
    foreach ($periods as $periodName => $dates) {
        // New query for this period
        $periodQuery = $db->prepare("
            SELECT 
                COALESCE(SUM(ps.final_amount), 0) as revenue,
                COALESCE(SUM(psi_cost.total_cost), 0) as cost
            FROM pos_sales ps
            LEFT JOIN (
                SELECT 
                    psi.pos_sale_id,
                    SUM(psi.quantity * {$costColumn}) as total_cost
                FROM pos_sale_items psi 
                LEFT JOIN {$productsTable} p ON psi.item_id = p.id
                WHERE p.id IS NOT NULL AND p.company_id = ?
                GROUP BY psi.pos_sale_id
            ) psi_cost ON psi_cost.pos_sale_id = ps.id
            WHERE ps.company_id = ?
            AND ps.created_at >= ?
            AND ps.created_at <= ?
            AND ps.swap_id IS NULL
        ");
        $periodQuery->execute([
            $TEST_COMPANY_ID, 
            $TEST_COMPANY_ID, 
            $dates['from'] . ' 00:00:00',
            $dates['to'] . ' 23:59:59'
        ]);
        $periodResult = $periodQuery->fetch(PDO::FETCH_ASSOC);
        
        $periodRevenue = floatval($periodResult['revenue'] ?? 0);
        $periodCost = floatval($periodResult['cost'] ?? 0);
        $periodProfit = $periodRevenue - $periodCost;
        
        // Old query for comparison
        $oldPeriodQuery = $db->prepare("
            SELECT 
                COALESCE(SUM(
                    (SELECT COALESCE(SUM(psi.quantity * {$costColumn}), 0)
                     FROM pos_sale_items psi 
                     LEFT JOIN {$productsTable} p ON psi.item_id = p.id AND p.company_id = ps.company_id
                     WHERE psi.pos_sale_id = ps.id AND p.id IS NOT NULL)
                ), 0) as cost
            FROM pos_sales ps
            WHERE ps.company_id = ?
            AND ps.created_at >= ?
            AND ps.created_at <= ?
            AND ps.swap_id IS NULL
        ");
        $oldPeriodQuery->execute([
            $TEST_COMPANY_ID,
            $dates['from'] . ' 00:00:00',
            $dates['to'] . ' 23:59:59'
        ]);
        $oldPeriodResult = $oldPeriodQuery->fetch(PDO::FETCH_ASSOC);
        $oldPeriodCost = floatval($oldPeriodResult['cost'] ?? 0);
        
        $status = ($periodCost <= $periodRevenue || $periodRevenue == 0) ? '✓ Good' : '✗ Issue';
        $statusClass = ($periodCost <= $periodRevenue || $periodRevenue == 0) ? 'good' : 'bad';
        
        output("$periodName ({$dates['from']} to {$dates['to']}):", 'INFO');
        output("  Revenue: " . formatCurrency($periodRevenue), 'INFO');
        output("  Cost (NEW): " . formatCurrency($periodCost), 'INFO');
        output("  Profit: " . formatCurrency($periodProfit), 'INFO');
        output("  Status: $status", ($status == '✓ Good' ? 'SUCCESS' : 'ERROR'));
        
        if (php_sapi_name() !== 'cli') {
            echo "<tr>
                    <td><strong>$periodName</strong></td>
                    <td>{$dates['from']} to {$dates['to']}</td>
                    <td>" . formatCurrency($periodRevenue) . "</td>
                    <td class='bad'>" . formatCurrency($oldPeriodCost) . "</td>
                    <td>" . formatCurrency($periodCost) . "</td>
                    <td class='metric $statusClass'>" . formatCurrency($periodProfit) . "</td>
                    <td class='$statusClass'>$status</td>
                  </tr>";
        }
    }
    
    if (php_sapi_name() !== 'cli') {
        echo "</table>";
    }
    
    // Test 7: Verify AnalyticsService is using the fixed query
    output("\nTest 7: Checking AnalyticsService.php", 'INFO');
    
    $analyticsFile = __DIR__ . '/app/Services/AnalyticsService.php';
    if (file_exists($analyticsFile)) {
        $analyticsContent = file_get_contents($analyticsFile);
        
        // Check if it has the old buggy pattern
        if (strpos($analyticsContent, 'SUM(psi_cost.total_cost)') !== false) {
            output("  ✓ AnalyticsService is using the FIXED query", 'SUCCESS');
        } elseif (strpos($analyticsContent, '(SELECT COALESCE(SUM(psi.quantity') !== false) {
            output("  ✗ AnalyticsService is still using the OLD buggy query!", 'ERROR');
            output("  ACTION REQUIRED: Run 'git pull origin master' on the server", 'ERROR');
        } else {
            output("  ? Unable to determine which query version is in use", 'WARNING');
        }
    } else {
        output("  AnalyticsService.php not found", 'ERROR');
    }
    
    // Final summary
    output("\n" . str_repeat("=", 50), 'INFO');
    output("TEST SUMMARY", 'INFO');
    output(str_repeat("=", 50), 'INFO');
    
    if ($oldCost > $oldRevenue && $oldRevenue > 0) {
        output("❌ OLD query has issues - Cost exceeds Revenue", 'ERROR');
    } else {
        output("✓ OLD query results look reasonable (but may still have bugs)", 'WARNING');
    }
    
    if ($newCost <= $newRevenue || $newRevenue == 0) {
        output("✓ NEW query results look good", 'SUCCESS');
    } else {
        output("❌ NEW query still has issues", 'ERROR');
    }
    
    if ($costDiff > 1000) {
        output("⚠️ Significant cost difference detected: " . formatCurrency($costDiff), 'WARNING');
        output("   This confirms the bug was inflating costs", 'WARNING');
    }
    
    output("\n📋 RECOMMENDATIONS:", 'INFO');
    output("1. Run 'git pull origin master' on your server to get the fixed code", 'INFO');
    output("2. Clear browser cache and refresh the audit trail page", 'INFO');
    output("3. Click 'This Month' button to load monthly data with fixed calculation", 'INFO');
    output("4. Verify profit shows correctly across all time periods", 'INFO');
    
} catch (Exception $e) {
    output("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    output("Stack trace: " . $e->getTraceAsString(), 'ERROR');
}

// HTML footer for browser
if (php_sapi_name() !== 'cli') {
    echo '<div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-left: 4px solid #2196F3;">
            <h3>🔧 Next Steps:</h3>
            <ol>
                <li>If tests show issues, run <code>git pull origin master</code> on your server</li>
                <li>Refresh your audit trail page with <code>Ctrl + Shift + R</code></li>
                <li>Verify profit calculations are now correct</li>
            </ol>
          </div>
          </div>
          </body>
          </html>';
}

output("\n✅ Test completed at " . date('Y-m-d H:i:s'), 'SUCCESS');

