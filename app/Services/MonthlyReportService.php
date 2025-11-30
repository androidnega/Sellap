<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

/**
 * Monthly Report Service
 * Generates and sends monthly sales reports to clients
 */
class MonthlyReportService {
    private $db;
    private $emailService;
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->emailService = new EmailService();
    }
    
    /**
     * Send monthly reports to all active companies
     */
    public function sendMonthlyReports() {
        try {
            // Get all active companies
            $stmt = $this->db->query("SELECT id, name, email FROM companies WHERE status = 'active'");
            $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $results = [
                'sent' => 0,
                'failed' => 0,
                'errors' => []
            ];
            
            foreach ($companies as $company) {
                try {
                    $this->sendCompanyMonthlyReport($company['id'], $company['name'], $company['email']);
                    $results['sent']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Company {$company['id']} ({$company['name']}): " . $e->getMessage();
                    error_log("Failed to send monthly report to company {$company['id']}: " . $e->getMessage());
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            error_log("MonthlyReportService error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send monthly report to a specific company
     */
    public function sendCompanyMonthlyReport($companyId, $companyName, $companyEmail) {
        if (empty($companyEmail)) {
            // Try to get email from company manager
            $stmt = $this->db->prepare("
                SELECT email FROM users 
                WHERE company_id = ? AND role IN ('manager', 'admin') 
                LIMIT 1
            ");
            $stmt->execute([$companyId]);
            $manager = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$manager || empty($manager['email'])) {
                throw new \Exception("No email address found for company");
            }
            
            $companyEmail = $manager['email'];
        }
        
        // Get last month's date range
        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
        $monthName = date('F Y', strtotime('last month'));
        
        // Get sales data
        $salesData = $this->getSalesData($companyId, $lastMonthStart, $lastMonthEnd);
        
        // Generate report HTML
        $reportHtml = $this->generateReportHtml($companyName, $monthName, $salesData, $lastMonthStart, $lastMonthEnd);
        
        // Send email
        $subject = "Monthly Sales Report - {$monthName} - {$companyName}";
        $result = $this->emailService->sendEmail($companyEmail, $subject, $reportHtml);
        
        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Failed to send email');
        }
        
        return true;
    }
    
    /**
     * Get sales data for the period
     */
    private function getSalesData($companyId, $dateFrom, $dateTo) {
        $data = [
            'total_sales' => 0,
            'total_revenue' => 0,
            'total_items' => 0,
            'top_products' => [],
            'daily_sales' => [],
            'payment_methods' => []
        ];
        
        try {
            // Total sales count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total, SUM(total_amount) as revenue
                FROM pos_sales 
                WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $sales = $stmt->fetch(\PDO::FETCH_ASSOC);
            $data['total_sales'] = (int)($sales['total'] ?? 0);
            $data['total_revenue'] = (float)($sales['revenue'] ?? 0);
            
            // Total items sold
            $stmt = $this->db->prepare("
                SELECT SUM(quantity) as total_items
                FROM pos_sale_items psi
                JOIN pos_sales ps ON psi.sale_id = ps.id
                WHERE ps.company_id = ? AND DATE(ps.created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $items = $stmt->fetch(\PDO::FETCH_ASSOC);
            $data['total_items'] = (int)($items['total_items'] ?? 0);
            
            // Top 5 products
            $stmt = $this->db->prepare("
                SELECT p.name, SUM(psi.quantity) as quantity, SUM(psi.total_price) as revenue
                FROM pos_sale_items psi
                JOIN pos_sales ps ON psi.sale_id = ps.id
                JOIN products p ON psi.product_id = p.id
                WHERE ps.company_id = ? AND DATE(ps.created_at) BETWEEN ? AND ?
                GROUP BY p.id, p.name
                ORDER BY revenue DESC
                LIMIT 5
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $data['top_products'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Daily sales breakdown
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as sales_count, SUM(total_amount) as revenue
                FROM pos_sales 
                WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $data['daily_sales'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Payment methods breakdown
            $stmt = $this->db->prepare("
                SELECT payment_method, COUNT(*) as count, SUM(amount) as total
                FROM sale_payments sp
                JOIN pos_sales ps ON sp.sale_id = ps.id
                WHERE ps.company_id = ? AND DATE(ps.created_at) BETWEEN ? AND ?
                GROUP BY payment_method
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $data['payment_methods'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("Error getting sales data: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Generate HTML report
     */
    private function generateReportHtml($companyName, $monthName, $salesData, $dateFrom, $dateTo) {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
                .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .stat-value { font-size: 32px; font-weight: bold; color: #3b82f6; }
                .stat-label { font-size: 14px; color: #6b7280; margin-top: 5px; }
                .section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .section-title { font-size: 20px; font-weight: bold; color: #1f2937; margin-bottom: 15px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
                th { background: #f3f4f6; font-weight: 600; color: #374151; }
                .footer { text-align: center; margin-top: 30px; padding: 20px; color: #6b7280; font-size: 12px; }
                .highlight { color: #059669; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 28px;'>Monthly Sales Report</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>{$companyName} - {$monthName}</p>
                </div>
                
                <div class='content'>
                    <div class='stats-grid'>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($salesData['total_sales']) . "</div>
                            <div class='stat-label'>Total Sales</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>₵" . number_format($salesData['total_revenue'], 2) . "</div>
                            <div class='stat-label'>Total Revenue</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($salesData['total_items']) . "</div>
                            <div class='stat-label'>Items Sold</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>₵" . number_format($salesData['total_revenue'] / max($salesData['total_sales'], 1), 2) . "</div>
                            <div class='stat-label'>Average Sale</div>
                        </div>
                    </div>
                    
                    " . ($salesData['top_products'] ? "
                    <div class='section'>
                        <div class='section-title'>Top 5 Products</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                    " . implode('', array_map(function($product) {
                        return "<tr>
                            <td>{$product['name']}</td>
                            <td>" . number_format($product['quantity']) . "</td>
                            <td class='highlight'>₵" . number_format($product['revenue'], 2) . "</td>
                        </tr>";
                    }, $salesData['top_products'])) . "
                            </tbody>
                        </table>
                    </div>
                    " : "") . "
                    
                    " . ($salesData['payment_methods'] ? "
                    <div class='section'>
                        <div class='section-title'>Payment Methods</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                    " . implode('', array_map(function($method) {
                        return "<tr>
                            <td>" . ucfirst($method['payment_method']) . "</td>
                            <td>" . number_format($method['count']) . "</td>
                            <td class='highlight'>₵" . number_format($method['total'], 2) . "</td>
                        </tr>";
                    }, $salesData['payment_methods'])) . "
                            </tbody>
                        </table>
                    </div>
                    " : "") . "
                    
                    <div class='section'>
                        <div class='section-title'>Performance Summary</div>
                        <p>This report covers the period from <strong>{$dateFrom}</strong> to <strong>{$dateTo}</strong>.</p>
                        <p>Your business processed <strong>" . number_format($salesData['total_sales']) . "</strong> sales transactions, generating a total revenue of <strong class='highlight'>₵" . number_format($salesData['total_revenue'], 2) . "</strong>.</p>
                        " . (count($salesData['daily_sales']) > 0 ? "<p>The best performing day was <strong>" . $this->getBestDay($salesData['daily_sales']) . "</strong>.</p>" : "") . "
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated monthly report from SellApp.</p>
                    <p>For questions or support, please contact your system administrator.</p>
                    <p>&copy; " . date('Y') . " SellApp. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
    
    /**
     * Get best performing day
     */
    private function getBestDay($dailySales) {
        if (empty($dailySales)) {
            return 'N/A';
        }
        
        $bestDay = $dailySales[0];
        foreach ($dailySales as $day) {
            if ($day['revenue'] > $bestDay['revenue']) {
                $bestDay = $day;
            }
        }
        
        return date('F j, Y', strtotime($bestDay['date']));
    }
}

