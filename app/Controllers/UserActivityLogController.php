<?php

namespace App\Controllers;

use App\Models\UserActivityLog;
use App\Middleware\WebAuthMiddleware;

/**
 * User Activity Log Controller
 * Manages viewing user activity logs (login/logout) for system admin
 */
class UserActivityLogController {
    private $activityLog;

    public function __construct() {
        $this->activityLog = new UserActivityLog();
    }

    /**
     * Display user activity logs page
     */
    public function index() {
        // Only system admin can access
        WebAuthMiddleware::handle(['system_admin']);
        
        // Get pagination parameters
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(10, min(100, intval($_GET['limit'] ?? 50))); // Default 50 per page, max 100
        $offset = ($page - 1) * $limit;
        
        // Get filters from query parameters
        // Note: We only show login records (logout is indicated by logout_time being set)
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'company_id' => $_GET['company_id'] ?? null,
            'user_role' => $_GET['user_role'] ?? null,
            'status' => $_GET['status'] ?? null, // 'active' or 'completed'
            'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d')
        ];
        
        // Remove null filters (but keep empty strings for date filters)
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });
        
        // Add pagination to filters
        $filters['limit'] = $limit;
        $filters['offset'] = $offset;
        
        // Get total count for pagination
        $totalLogs = $this->activityLog->getCount($filters);
        $totalPages = ceil($totalLogs / $limit);
        
        // Get activity logs
        $logs = $this->activityLog->getAll($filters);
        
        // Get statistics (without pagination)
        $statsFilters = $filters;
        unset($statsFilters['limit'], $statsFilters['offset']);
        $stats = $this->activityLog->getStatistics($statsFilters);
        
        // Pagination data
        $pagination = [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalLogs,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ];
        
        // Get all companies for filter dropdown
        $db = \Database::getInstance()->getConnection();
        $companies = $db->query("SELECT id, name FROM companies ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get all roles for filter dropdown
        $roles = ['system_admin', 'admin', 'manager', 'salesperson', 'technician'];
        
        $title = 'User Activity Logs';
        $page = 'user-logs';
        
        // Capture the view content
        ob_start();
        include __DIR__ . '/../Views/user_activity_logs.php';
        $content = ob_get_clean();
        
        // Set content variable for layout
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        
        // Pass user data to layout for sidebar role detection
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user'])) {
            $GLOBALS['user_data'] = $_SESSION['user'];
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
    }
}

