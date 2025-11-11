<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

/**
 * Reset Notification Service (PHASE E)
 * Handles email and SMS notifications for reset operations
 */
class ResetNotificationService {
    private $db;
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }
    
    /**
     * Send notification when reset operation completes
     * 
     * @param int $adminActionId Admin action ID
     * @param string $actionType 'company_reset' or 'system_reset'
     * @param bool $success Whether reset was successful
     * @param array $summary Reset summary data
     */
    public function notifyResetCompletion($adminActionId, $actionType, $success, $summary = []) {
        try {
            // Get admin action details
            $stmt = $this->db->prepare("
                SELECT 
                    aa.*,
                    u.email as admin_email,
                    u.full_name as admin_name,
                    u.username as admin_username,
                    c.name as company_name,
                    c.email as company_email
                FROM admin_actions aa
                JOIN users u ON aa.admin_user_id = u.id
                LEFT JOIN companies c ON aa.target_company_id = c.id
                WHERE aa.id = ?
            ");
            $stmt->execute([$adminActionId]);
            $action = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$action) {
                error_log("Cannot send notification: Admin action {$adminActionId} not found");
                return;
            }
            
            $payload = json_decode($action['payload'], true) ?: [];
            $rowCounts = json_decode($action['row_counts'], true) ?: [];
            
            // Send email to system admin
            $this->sendAdminEmail($action, $success, $summary, $rowCounts);
            
            // If company reset, notify company manager if present
            if ($actionType === 'company_reset' && $action['target_company_id']) {
                $this->notifyCompanyManager($action['target_company_id'], $action, $success);
            }
            
        } catch (\Exception $e) {
            error_log("Failed to send reset notification: " . $e->getMessage());
        }
    }
    
    /**
     * Send email to system admin
     */
    private function sendAdminEmail($action, $success, $summary, $rowCounts) {
        $subject = $success 
            ? "Reset Operation Completed: {$action['action_type']}"
            : "Reset Operation Failed: {$action['action_type']}";
        
        $companyInfo = $action['target_company_id'] 
            ? "Company: {$action['company_name']} (ID: {$action['target_company_id']})\n"
            : "System-wide reset\n";
        
        $totalAffected = array_sum($rowCounts);
        
        $message = "
Reset Operation Notification
============================

Action Type: {$action['action_type']}
Status: " . ($success ? 'COMPLETED' : 'FAILED') . "
{$companyInfo}
Performed By: {$action['admin_name']} ({$action['admin_username']})
Date: {$action['created_at']}
" . ($action['completed_at'] ? "Completed: {$action['completed_at']}\n" : "") . "
" . ($action['backup_reference'] ? "Backup Reference: {$action['backup_reference']}\n" : "") . "
" . ($action['dry_run'] ? "Mode: DRY RUN (No data deleted)\n" : "Mode: ACTUAL RESET\n") . "

Affected Rows: {$totalAffected}
" . ($action['error_message'] ? "Error: {$action['error_message']}\n" : "") . "

Row Counts:
" . $this->formatRowCounts($rowCounts) . "

View Details: " . $this->getStatusUrl($action['id']) . "
        ";
        
        // Send email (implement your email sending logic here)
        // For now, just log it
        error_log("EMAIL TO {$action['admin_email']}:\n{$subject}\n\n{$message}");
        
        // TODO: Integrate with your email service (PHPMailer, SendGrid, etc.)
        // mail($action['admin_email'], $subject, $message);
    }
    
    /**
     * Notify company manager about company reset
     */
    private function notifyCompanyManager($companyId, $action, $success) {
        try {
            // Get company manager
            $stmt = $this->db->prepare("
                SELECT id, email, full_name, phone_number 
                FROM users 
                WHERE company_id = ? AND role = 'manager' 
                LIMIT 1
            ");
            $stmt->execute([$companyId]);
            $manager = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$manager) {
                // No manager found, skip notification
                return;
            }
            
            // Send email to manager
            $subject = "Company Data Reset Notification";
            $message = "
Dear {$manager['full_name']},

Your company data has been reset by a system administrator.

Company: {$action['company_name']}
Reset Date: {$action['created_at']}
" . ($action['backup_reference'] ? "Backup Reference: {$action['backup_reference']}\n" : "") . "

All transactional data (products, sales, customers, etc.) has been cleared.
The company record and global catalogs remain intact.

If you have any questions, please contact system administration.

Best regards,
SellApp System
            ";
            
            error_log("EMAIL TO MANAGER {$manager['email']}:\n{$subject}\n\n{$message}");
            
            // TODO: Send email
            // mail($manager['email'], $subject, $message);
            
            // TODO: Send SMS if phone number available and SMS service configured
            // if ($manager['phone_number']) {
            //     $this->sendSMS($manager['phone_number'], "Company data reset completed. Check email for details.");
            // }
            
        } catch (\Exception $e) {
            error_log("Failed to notify company manager: " . $e->getMessage());
        }
    }
    
    /**
     * Format row counts for email display
     */
    private function formatRowCounts($rowCounts) {
        $formatted = "";
        foreach ($rowCounts as $table => $count) {
            $formatted .= "  - {$table}: {$count}\n";
        }
        return $formatted ?: "  (No data affected)";
    }
    
    /**
     * Get status page URL
     */
    private function getStatusUrl($actionId) {
        $baseUrl = getenv('APP_URL') ?: 'http://localhost';
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/sellapp';
        return "{$baseUrl}{$basePath}/dashboard/admin/reset/{$actionId}";
    }
}

