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
}

