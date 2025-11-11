<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Notification {
    private $db;
    private $table = 'notifications';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new notification
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (
                user_id, company_id, message, type, data
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['company_id'],
            $data['message'],
            $data['type'] ?? 'system',
            $data['data'] ? json_encode($data['data']) : null
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Get notifications for a user
     */
    public function getByUser($user_id, $company_id, $limit = 50) {
        // Ensure limit is an integer
        $limit = (int)$limit;
        
        // Use direct integer value for LIMIT to avoid prepared statement issues
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = ? AND company_id = ? 
            ORDER BY created_at DESC 
            LIMIT {$limit}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id, $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount($user_id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND company_id = ? AND status = 'unread'
        ");
        
        $stmt->execute([$user_id, $company_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id, $user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET status = 'read', read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$id, $user_id]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET status = 'read', read_at = NOW() 
            WHERE user_id = ? AND company_id = ? AND status = 'unread'
        ");
        
        return $stmt->execute([$user_id, $company_id]);
    }

    /**
     * Delete old notifications (cleanup)
     */
    public function deleteOld($days = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        return $stmt->execute([$days]);
    }

    /**
     * Create repair notification
     */
    public function createRepairNotification($repair_id, $technician_id, $company_id, $message) {
        return $this->create([
            'user_id' => $technician_id,
            'company_id' => $company_id,
            'message' => $message,
            'type' => 'repair',
            'data' => ['repair_id' => $repair_id]
        ]);
    }

    /**
     * Create stock notification
     */
    public function createStockNotification($product_id, $manager_id, $company_id, $message) {
        return $this->create([
            'user_id' => $manager_id,
            'company_id' => $company_id,
            'message' => $message,
            'type' => 'stock',
            'data' => ['product_id' => $product_id]
        ]);
    }
}
