<?php

namespace App\Controllers;

use App\Middleware\WebAuthMiddleware;
use PDO;

class EmailLogsController {
    private $db;
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }
    
    /**
     * Show email logs page
     * GET /dashboard/email-logs
     */
    public function index() {
        WebAuthMiddleware::handle(['system_admin']);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ' . BASE_URL_PATH . '/');
            exit;
        }
        
        // Check if email_logs table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'email_logs'");
            if ($checkTable->rowCount() == 0) {
                // Table doesn't exist, show migration message
                $title = 'Email Logs - Migration Required';
                $GLOBALS['pageTitle'] = $title;
                $GLOBALS['migrationRequired'] = true;
                $GLOBALS['content'] = $this->getMigrationRequiredView();
                $GLOBALS['title'] = $title;
                $GLOBALS['user_data'] = $_SESSION['user'];
                require __DIR__ . '/../Views/simple_layout.php';
                return;
            }
        } catch (\Exception $e) {
            error_log("Error checking email_logs table: " . $e->getMessage());
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        // Filters
        $emailType = $_GET['email_type'] ?? null;
        $status = $_GET['status'] ?? null;
        $companyId = $_GET['company_id'] ?? null;
        $search = $_GET['search'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if ($emailType) {
            $whereConditions[] = "el.email_type = :email_type";
            $params['email_type'] = $emailType;
        }
        
        if ($status) {
            $whereConditions[] = "el.status = :status";
            $params['status'] = $status;
        }
        
        if ($companyId) {
            $whereConditions[] = "el.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if ($search) {
            $whereConditions[] = "(el.recipient_email LIKE :search OR el.subject LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(el.created_at) >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(el.created_at) <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        try {
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM email_logs el {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get email logs
            $sql = "
                SELECT 
                    el.*,
                    c.name as company_name,
                    u.full_name as user_name,
                    u.username as user_username
                FROM email_logs el
                LEFT JOIN companies c ON el.company_id = c.id
                LEFT JOIN users u ON el.user_id = u.id
                {$whereClause}
                ORDER BY el.created_at DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $emailLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get companies for filter
            $companiesStmt = $this->db->query("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
            $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get statistics
            $statsStmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN email_type = 'monthly_report' THEN 1 ELSE 0 END) as monthly_reports,
                    SUM(CASE WHEN email_type = 'backup' THEN 1 ELSE 0 END) as backups
                FROM email_logs
            ");
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error loading email logs: " . $e->getMessage());
            // Set defaults on error
            $emailLogs = [];
            $companies = [];
            $stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'monthly_reports' => 0, 'backups' => 0];
            $total = 0;
        }
        
        $title = 'Email Logs';
        $GLOBALS['pageTitle'] = $title;
        $GLOBALS['emailLogs'] = $emailLogs;
        $GLOBALS['companies'] = $companies;
        $GLOBALS['stats'] = $stats;
        $GLOBALS['filters'] = [
            'email_type' => $emailType,
            'status' => $status,
            'company_id' => $companyId,
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        $GLOBALS['pagination'] = [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ];
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/email_logs.php';
        $content = ob_get_clean();
        
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }
    
    /**
     * Get migration required view
     */
    private function getMigrationRequiredView() {
        ob_start();
        ?>
        <div class="w-full">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Email Logs</h1>
                <p class="text-sm text-gray-600 mt-1">View all emails sent by the system (automatic and manual)</p>
            </div>
            
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-semibold text-yellow-800 mb-2">Database Migration Required</h3>
                        <p class="text-sm text-yellow-700 mb-4">
                            The <code class="bg-yellow-100 px-2 py-1 rounded">email_logs</code> table has not been created yet. 
                            You need to run the migration to enable email logging functionality.
                        </p>
                        <div class="mt-4">
                            <a href="<?= BASE_URL_PATH ?>/dashboard/tools" 
                               class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition">
                                <i class="fas fa-tools mr-2"></i>
                                Go to Migration Tools
                            </a>
                            <p class="text-xs text-yellow-600 mt-2">
                                Or run the migration manually: <code class="bg-yellow-100 px-2 py-1 rounded">database/migrations/run_create_email_logs_migration.php</code>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

