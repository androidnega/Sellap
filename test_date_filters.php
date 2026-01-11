<?php
/**
 * Test Date Filters - Verify Audit Trail date filtering works correctly
 * Access: https://sellapp.store/test_date_filters.php
 */

// Prevent direct access in production (comment out for testing)
// die('Test file disabled');

require_once __DIR__ . '/config/database.php';

// Set company ID to test (change this to your company ID)
$companyId = 11; // Update this to your actual company ID

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Filter Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .success { background: #d4edda; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .test-section { margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 4px; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .date-range { font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Date Filter Test Tool</h1>
        <div class="info">
            <strong>Company ID:</strong> <?= $companyId ?><br>
            <strong>Test Time:</strong> <?= date('Y-m-d H:i:s') ?><br>
            <strong>Purpose:</strong> Verify that date filters work correctly for Today, This Week, This Month, This Year, and All Time
        </div>

        <?php
        try {
            $db = Database::getInstance()->getConnection();
            
            // Get current date info
            $today = date('Y-m-d');
            $todayObj = new DateTime($today);
            
            // Calculate date ranges
            $ranges = [
                'today' => [
                    'from' => $today,
                    'to' => $today,
                    'label' => 'Today'
                ],
                'this_week' => [
                    'from' => date('Y-m-d', strtotime($today . ' -' . date('w', strtotime($today)) . ' days')),
                    'to' => $today,
                    'label' => 'This Week (Sunday to Today)'
                ],
                'this_month' => [
                    'from' => date('Y-m-01'),
                    'to' => $today,
                    'label' => 'This Month'
                ],
                'this_year' => [
                    'from' => date('Y-01-01'),
                    'to' => $today,
                    'label' => 'This Year'
                ],
                'all_time' => [
                    'from' => '1900-01-01',
                    'to' => '2099-12-31',
                    'label' => 'All Time'
                ]
            ];
            
            echo "<h2>📊 Sales Data by Date Range</h2>";
            
            foreach ($ranges as $key => $range) {
                echo "<div class='test-section'>";
                echo "<h3>🔍 {$range['label']}</h3>";
                echo "<p><strong>Date Range:</strong> <span class='date-range'>{$range['from']}</span> to <span class='date-range'>{$range['to']}</span></p>";
                
                // Query sales for this date range
                $sql = "
                    SELECT 
                        COUNT(*) as sales_count,
                        COALESCE(SUM(final_amount), 0) as total_revenue,
                        MIN(DATE(created_at)) as first_sale,
                        MAX(DATE(created_at)) as last_sale
                    FROM pos_sales
                    WHERE company_id = :company_id
                    AND DATE(created_at) BETWEEN :date_from AND :date_to
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    'company_id' => $companyId,
                    'date_from' => $range['from'],
                    'date_to' => $range['to']
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['sales_count'] > 0) {
                    echo "<div class='success'>";
                    echo "✅ <strong>{$result['sales_count']} sales</strong> found<br>";
                    echo "💰 <strong>Revenue:</strong> ₵" . number_format($result['total_revenue'], 2) . "<br>";
                    echo "📅 <strong>First Sale:</strong> {$result['first_sale']}<br>";
                    echo "📅 <strong>Last Sale:</strong> {$result['last_sale']}";
                    echo "</div>";
                    
                    // Show sample sales
                    $sampleSql = "
                        SELECT 
                            id,
                            DATE(created_at) as sale_date,
                            TIME(created_at) as sale_time,
                            final_amount,
                            payment_status
                        FROM pos_sales
                        WHERE company_id = :company_id
                        AND DATE(created_at) BETWEEN :date_from AND :date_to
                        ORDER BY created_at DESC
                        LIMIT 5
                    ";
                    $sampleStmt = $db->prepare($sampleSql);
                    $sampleStmt->execute([
                        'company_id' => $companyId,
                        'date_from' => $range['from'],
                        'date_to' => $range['to']
                    ]);
                    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($samples) > 0) {
                        echo "<table>";
                        echo "<tr><th>Sale ID</th><th>Date</th><th>Time</th><th>Amount</th><th>Status</th></tr>";
                        foreach ($samples as $sale) {
                            echo "<tr>";
                            echo "<td>#{$sale['id']}</td>";
                            echo "<td>{$sale['sale_date']}</td>";
                            echo "<td>{$sale['sale_time']}</td>";
                            echo "<td>₵" . number_format($sale['final_amount'], 2) . "</td>";
                            echo "<td>{$sale['payment_status']}</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                } else {
                    echo "<div class='warning'>";
                    echo "⚠️ No sales found for this date range";
                    echo "</div>";
                }
                
                echo "</div>";
            }
            
            // Test API endpoint behavior
            echo "<h2>🔧 API Endpoint Test</h2>";
            echo "<div class='test-section'>";
            echo "<p>Testing how the backend calculates date ranges...</p>";
            
            $testCases = [
                'today' => ['Y-m-d', 'Y-m-d'],
                'this_week' => ['Y-m-d (Monday)', 'Y-m-d (Today)'],
                'this_month' => ['Y-m-01', 'Y-m-d'],
                'this_year' => ['Y-01-01', 'Y-m-d']
            ];
            
            echo "<table>";
            echo "<tr><th>Range</th><th>Backend Calculation</th><th>Expected From</th><th>Expected To</th></tr>";
            
            foreach ($testCases as $rangeKey => $format) {
                switch ($rangeKey) {
                    case 'today':
                        $from = date('Y-m-d');
                        $to = date('Y-m-d');
                        break;
                    case 'this_week':
                        $from = date('Y-m-d', strtotime('monday this week'));
                        $to = date('Y-m-d');
                        break;
                    case 'this_month':
                        $from = date('Y-m-01');
                        $to = date('Y-m-d');
                        break;
                    case 'this_year':
                        $from = date('Y-01-01');
                        $to = date('Y-m-d');
                        break;
                }
                
                echo "<tr>";
                echo "<td><code>{$rangeKey}</code></td>";
                echo "<td>" . ucwords(str_replace('_', ' ', $rangeKey)) . "</td>";
                echo "<td><span class='date-range'>{$from}</span></td>";
                echo "<td><span class='date-range'>{$to}</span></td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='info'>";
            echo "<strong>⚠️ IMPORTANT NOTE:</strong><br>";
            echo "The backend uses <code>strtotime('monday this week')</code> for 'this_week', which calculates from Monday.<br>";
            echo "But the frontend JavaScript uses <code>today.getDay()</code> to calculate from Sunday.<br>";
            echo "<strong>This is a MISMATCH!</strong> Frontend sends explicit dates, so backend should use those.";
            echo "</div>";
            
            echo "</div>";
            
            // Check if there are sales outside current month
            echo "<h2>📅 Sales Distribution by Month</h2>";
            $monthSql = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as sales_count,
                    COALESCE(SUM(final_amount), 0) as revenue
                FROM pos_sales
                WHERE company_id = :company_id
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12
            ";
            $monthStmt = $db->prepare($monthSql);
            $monthStmt->execute(['company_id' => $companyId]);
            $months = $monthStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($months) > 0) {
                echo "<div class='test-section'>";
                echo "<table>";
                echo "<tr><th>Month</th><th>Sales Count</th><th>Revenue</th></tr>";
                foreach ($months as $month) {
                    echo "<tr>";
                    echo "<td>{$month['month']}</td>";
                    echo "<td>{$month['sales_count']}</td>";
                    echo "<td>₵" . number_format($month['revenue'], 2) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                if (count($months) == 1) {
                    echo "<div class='warning'>";
                    echo "⚠️ <strong>All sales are in the same month!</strong><br>";
                    echo "This explains why 'This Month' and 'This Year' show the same data.";
                    echo "</div>";
                } else {
                    echo "<div class='success'>";
                    echo "✅ Sales span multiple months. Date filters should show different data.";
                    echo "</div>";
                }
                echo "</div>";
            }
            
            echo "<h2>✅ Test Complete</h2>";
            echo "<div class='success'>";
            echo "All date range calculations have been tested. Review the results above to verify correct behavior.";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>";
            echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
        ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #dee2e6;">
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Review the sales data for each date range above</li>
                <li>Compare with what you see in the Audit Trail page</li>
                <li>If data doesn't match, check the API endpoint: <code>/api/audit-trail/data</code></li>
                <li>If "This Week" shows zeros, check if today is Saturday and verify Sunday-Saturday range</li>
            </ol>
        </div>
    </div>
</body>
</html>

