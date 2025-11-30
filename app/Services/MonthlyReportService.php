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
     * Send monthly reports to all active companies and users
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
                    // Send to company email if available
                    if (!empty($company['email'])) {
                        $this->sendCompanyMonthlyReport($company['id'], $company['name'], $company['email']);
                        $results['sent']++;
                    }
                    
                    // Send to individual users based on their roles
                    $this->sendUserMonthlyReports($company['id'], $company['name']);
                    
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
     * Send monthly reports to individual users based on their roles
     */
    private function sendUserMonthlyReports($companyId, $companyName) {
        try {
            // Get last month's date range
            $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
            $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
            $monthName = date('F Y', strtotime('last month'));
            
            // Get all users for this company with email addresses
            $stmt = $this->db->prepare("
                SELECT id, email, full_name, username, role 
                FROM users 
                WHERE company_id = ? AND email IS NOT NULL AND email != ''
                AND role IN ('manager', 'admin', 'salesperson', 'technician')
            ");
            $stmt->execute([$companyId]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                try {
                    if (empty($user['email'])) {
                        continue;
                    }
                    
                    // Get user-specific performance data based on role
                    $userData = $this->getUserPerformanceData($user['id'], $user['role'], $companyId, $lastMonthStart, $lastMonthEnd);
                    
                    // Generate role-specific report
                    $reportHtml = $this->generateUserReportHtml($user, $companyName, $monthName, $userData, $lastMonthStart, $lastMonthEnd);
                    
                    // Send email
                    $subject = "Monthly Performance Report - {$monthName} - {$companyName}";
                    $result = $this->emailService->sendEmail($user['email'], $subject, $reportHtml);
                    
                    if ($result['success']) {
                        error_log("Monthly report sent to user {$user['id']} ({$user['email']}) - Role: {$user['role']}");
                    } else {
                        error_log("Failed to send monthly report to user {$user['id']}: " . $result['message']);
                    }
                } catch (\Exception $e) {
                    error_log("Error sending report to user {$user['id']}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("Error sending user monthly reports: " . $e->getMessage());
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
    
    /**
     * Get user-specific performance data based on role
     */
    private function getUserPerformanceData($userId, $userRole, $companyId, $dateFrom, $dateTo) {
        $data = [
            'role' => $userRole,
            'total_sales' => 0,
            'total_revenue' => 0,
            'total_items' => 0,
            'repairs_completed' => 0,
            'customers_served' => 0,
            'personal_stats' => []
        ];
        
        try {
            if (in_array($userRole, ['salesperson', 'manager', 'admin'])) {
                // Sales performance
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total, SUM(total_amount) as revenue
                    FROM pos_sales 
                    WHERE company_id = ? AND created_by = ? AND DATE(created_at) BETWEEN ? AND ?
                ");
                $stmt->execute([$companyId, $userId, $dateFrom, $dateTo]);
                $sales = $stmt->fetch(\PDO::FETCH_ASSOC);
                $data['total_sales'] = (int)($sales['total'] ?? 0);
                $data['total_revenue'] = (float)($sales['revenue'] ?? 0);
                
                // Items sold
                $stmt = $this->db->prepare("
                    SELECT SUM(psi.quantity) as total_items
                    FROM pos_sale_items psi
                    JOIN pos_sales ps ON psi.sale_id = ps.id
                    WHERE ps.company_id = ? AND ps.created_by = ? AND DATE(ps.created_at) BETWEEN ? AND ?
                ");
                $stmt->execute([$companyId, $userId, $dateFrom, $dateTo]);
                $items = $stmt->fetch(\PDO::FETCH_ASSOC);
                $data['total_items'] = (int)($items['total_items'] ?? 0);
                
                // Customers served
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT customer_id) as customers
                    FROM pos_sales 
                    WHERE company_id = ? AND created_by = ? AND DATE(created_at) BETWEEN ? AND ?
                ");
                $stmt->execute([$companyId, $userId, $dateFrom, $dateTo]);
                $customers = $stmt->fetch(\PDO::FETCH_ASSOC);
                $data['customers_served'] = (int)($customers['customers'] ?? 0);
            }
            
            if ($userRole === 'technician') {
                // Repair performance
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total, SUM(repair_cost) as revenue
                    FROM repairs_new 
                    WHERE company_id = ? AND technician_id = ? AND status = 'completed'
                    AND DATE(completed_at) BETWEEN ? AND ?
                ");
                $stmt->execute([$companyId, $userId, $dateFrom, $dateTo]);
                $repairs = $stmt->fetch(\PDO::FETCH_ASSOC);
                $data['repairs_completed'] = (int)($repairs['total'] ?? 0);
                $data['total_revenue'] = (float)($repairs['revenue'] ?? 0);
            }
            
            // Manager/Admin get company-wide stats
            if (in_array($userRole, ['manager', 'admin'])) {
                $companyData = $this->getSalesData($companyId, $dateFrom, $dateTo);
                $data['company_total_sales'] = $companyData['total_sales'];
                $data['company_total_revenue'] = $companyData['total_revenue'];
                $data['top_products'] = $companyData['top_products'];
            }
            
        } catch (\Exception $e) {
            error_log("Error getting user performance data: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * Generate role-specific user report HTML
     */
    private function generateUserReportHtml($user, $companyName, $monthName, $userData, $dateFrom, $dateTo) {
        $userName = $user['full_name'] ?: $user['username'];
        $roleName = ucfirst($user['role']);
        
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
                .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
                .badge-manager { background: #dbeafe; color: #1e40af; }
                .badge-salesperson { background: #dcfce7; color: #166534; }
                .badge-technician { background: #fef3c7; color: #92400e; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 28px;'>Monthly Performance Report</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>{$userName} - {$companyName}</p>
                    <p style='margin: 5px 0 0 0; opacity: 0.8; font-size: 14px;'>{$monthName}</p>
                </div>
                
                <div class='content'>
        ";
        
        // Role-specific content
        if (in_array($userData['role'], ['salesperson', 'manager', 'admin'])) {
            $html .= "
                    <div class='stats-grid'>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($userData['total_sales']) . "</div>
                            <div class='stat-label'>Sales Made</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>₵" . number_format($userData['total_revenue'], 2) . "</div>
                            <div class='stat-label'>Revenue Generated</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($userData['total_items']) . "</div>
                            <div class='stat-label'>Items Sold</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($userData['customers_served']) . "</div>
                            <div class='stat-label'>Customers Served</div>
                        </div>
                    </div>
                    
                    <div class='section'>
                        <div class='section-title'>Your Performance Summary</div>
                        <p>During <strong>{$monthName}</strong>, you made <strong>" . number_format($userData['total_sales']) . "</strong> sales, generating <strong class='highlight'>₵" . number_format($userData['total_revenue'], 2) . "</strong> in revenue.</p>
                        <p>You served <strong>" . number_format($userData['customers_served']) . "</strong> customers and sold <strong>" . number_format($userData['total_items']) . "</strong> items.</p>
                        " . ($userData['total_sales'] > 0 ? "<p>Your average sale value was <strong class='highlight'>₵" . number_format($userData['total_revenue'] / $userData['total_sales'], 2) . "</strong>.</p>" : "") . "
                    </div>
            ";
            
            // Manager/Admin get company-wide stats
            if (in_array($userData['role'], ['manager', 'admin']) && isset($userData['company_total_sales'])) {
                $html .= "
                    <div class='section'>
                        <div class='section-title'>Company Overview</div>
                        <p>Your company processed <strong>" . number_format($userData['company_total_sales']) . "</strong> total sales, generating <strong class='highlight'>₵" . number_format($userData['company_total_revenue'], 2) . "</strong> in revenue.</p>
                        <p>Your personal contribution represents <strong>" . number_format(($userData['total_revenue'] / max($userData['company_total_revenue'], 1)) * 100, 1) . "%</strong> of the company's total revenue.</p>
                ";
                
                if (!empty($userData['top_products'])) {
                    $html .= "
                        <h4 style='margin-top: 20px; margin-bottom: 10px;'>Top 5 Products (Company-wide)</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                    ";
                    foreach (array_slice($userData['top_products'], 0, 5) as $product) {
                        $html .= "<tr>
                            <td>{$product['name']}</td>
                            <td>" . number_format($product['quantity']) . "</td>
                            <td class='highlight'>₵" . number_format($product['revenue'], 2) . "</td>
                        </tr>";
                    }
                    $html .= "
                            </tbody>
                        </table>
                    ";
                }
                $html .= "</div>";
            }
        } elseif ($userData['role'] === 'technician') {
            $html .= "
                    <div class='stats-grid'>
                        <div class='stat-card'>
                            <div class='stat-value'>" . number_format($userData['repairs_completed']) . "</div>
                            <div class='stat-label'>Repairs Completed</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-value'>₵" . number_format($userData['total_revenue'], 2) . "</div>
                            <div class='stat-label'>Revenue Generated</div>
                        </div>
                    </div>
                    
                    <div class='section'>
                        <div class='section-title'>Your Performance Summary</div>
                        <p>During <strong>{$monthName}</strong>, you completed <strong>" . number_format($userData['repairs_completed']) . "</strong> repairs, generating <strong class='highlight'>₵" . number_format($userData['total_revenue'], 2) . "</strong> in revenue.</p>
                        " . ($userData['repairs_completed'] > 0 ? "<p>Your average repair value was <strong class='highlight'>₵" . number_format($userData['total_revenue'] / $userData['repairs_completed'], 2) . "</strong>.</p>" : "") . "
                    </div>
            ";
        }
        
        $html .= "
                    <div class='section'>
                        <div class='section-title'>Report Period</div>
                        <p>This report covers the period from <strong>{$dateFrom}</strong> to <strong>{$dateTo}</strong>.</p>
                        <p>Keep up the great work! Your contributions are valuable to {$companyName}.</p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated monthly performance report from SellApp.</p>
                    <p>For questions or support, please contact your system administrator.</p>
                    <p>&copy; " . date('Y') . " SellApp. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
}

