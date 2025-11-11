<?php

namespace App\Services;

use PDO;
use App\Services\AuditService;

require_once __DIR__ . '/../../config/database.php';

class AlertService {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Check and trigger alerts for a company
     * 
     * @param int $companyId
     * @return array Array of triggered alerts
     */
    public function checkAndTrigger($companyId) {
        $triggered = [];

        // Get all enabled alerts for company
        $stmt = $this->db->prepare("
            SELECT * FROM alerts 
            WHERE company_id = :company_id AND enabled = 1
        ");
        $stmt->execute(['company_id' => $companyId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($alerts as $alert) {
            try {
                if ($this->evaluateCondition($companyId, $alert)) {
                    // Check if alert already triggered recently (avoid spam)
                    if (!$this->isRecentlyTriggered($alert['id'])) {
                        $notificationId = $this->createNotification($alert, $companyId);
                        if ($notificationId) {
                            $triggered[] = [
                                'alert_id' => $alert['id'],
                                'notification_id' => $notificationId,
                                'key' => $alert['key_name'],
                                'severity' => $alert['severity']
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("AlertService: Error evaluating alert {$alert['id']}: " . $e->getMessage());
            }
        }

        return $triggered;
    }

    /**
     * Evaluate alert condition
     */
    private function evaluateCondition($companyId, $alert) {
        $condition = json_decode($alert['condition_json'], true);
        
        if (!$condition || !isset($condition['type'])) {
            return false;
        }

        switch ($condition['type']) {
            case 'sql':
                return $this->evaluateSqlCondition($companyId, $condition);
            case 'metric':
                return $this->evaluateMetricCondition($companyId, $condition);
            default:
                return false;
        }
    }

    /**
     * Evaluate SQL-based condition (sandboxed)
     */
    private function evaluateSqlCondition($companyId, $condition) {
        // Only allow SELECT queries and prevent SQL injection
        $query = $condition['query'] ?? '';
        $params = $condition['params'] ?? [];

        // Basic safety check - must be SELECT
        if (strtoupper(trim(substr($query, 0, 6))) !== 'SELECT') {
            return false;
        }

        // Ensure company_id is always included for security
        if (strpos($query, ':company_id') === false && strpos($query, '?') === false) {
            $query .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        } else {
            $params['company_id'] = $companyId;
        }

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check threshold
            $threshold = $condition['threshold'] ?? 0;
            $value = $result['value'] ?? $result['count'] ?? 0;
            $operator = $condition['operator'] ?? '>=';

            return $this->compareValues($value, $operator, $threshold);
        } catch (\Exception $e) {
            error_log("AlertService SQL condition error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Evaluate metric-based condition
     */
    private function evaluateMetricCondition($companyId, $condition) {
        $metric = $condition['metric'] ?? '';
        $operator = $condition['operator'] ?? '>';
        $value = $condition['value'] ?? 0;

        $metricValue = $this->getMetricValue($companyId, $metric);
        
        return $this->compareValues($metricValue, $operator, $value);
    }

    /**
     * Get metric value for company
     */
    private function getMetricValue($companyId, $metric) {
        switch ($metric) {
            case 'today_profit':
                $stmt = $this->db->prepare("
                    SELECT 
                        COALESCE(SUM(ps.final_amount - COALESCE(
                            (SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, 0))
                             FROM pos_sale_items psi 
                             LEFT JOIN products p ON psi.item_id = p.id 
                             WHERE psi.pos_sale_id = ps.id),
                            ps.final_amount * 0.8
                        )), 0) as profit
                    FROM pos_sales ps
                    WHERE ps.company_id = :company_id 
                    AND DATE(ps.created_at) = CURDATE()
                ");
                $stmt->execute(['company_id' => $companyId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (float)($result['profit'] ?? 0);

            case 'low_sms_balance':
                $stmt = $this->db->prepare("
                    SELECT (total_sms - sms_used) as remaining
                    FROM company_sms_accounts
                    WHERE company_id = :company_id
                    LIMIT 1
                ");
                $stmt->execute(['company_id' => $companyId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)($result['remaining'] ?? 0);

            case 'low_stock_count':
                $threshold = 10; // Default threshold
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM products
                    WHERE company_id = :company_id
                    AND (quantity > 0 AND quantity < :threshold)
                ");
                $stmt->execute([
                    'company_id' => $companyId,
                    'threshold' => $threshold
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)($result['count'] ?? 0);

            default:
                return 0;
        }
    }

    /**
     * Compare two values with operator
     */
    private function compareValues($value, $operator, $threshold) {
        switch ($operator) {
            case '>':
                return $value > $threshold;
            case '>=':
                return $value >= $threshold;
            case '<':
                return $value < $threshold;
            case '<=':
                return $value <= $threshold;
            case '=':
            case '==':
                return $value == $threshold;
            case '!=':
            case '<>':
                return $value != $threshold;
            default:
                return false;
        }
    }

    /**
     * Check if alert was recently triggered (avoid spam)
     */
    private function isRecentlyTriggered($alertId, $minutes = 60) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM alert_notifications
            WHERE alert_id = :alert_id
            AND handled = 0
            AND triggered_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute([
            'alert_id' => $alertId,
            'minutes' => $minutes
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0) > 0;
    }

    /**
     * Create alert notification
     */
    private function createNotification($alert, $companyId) {
        try {
            $payload = [
                'alert_key' => $alert['key_name'],
                'title' => $alert['title'],
                'severity' => $alert['severity'],
                'triggered_at' => date('Y-m-d H:i:s')
            ];

            // Determine channels based on severity
            $channels = ['dashboard'];
            if ($alert['severity'] === 'critical') {
                $channels[] = 'email';
                $channels[] = 'sms';
            } elseif ($alert['severity'] === 'warning') {
                $channels[] = 'email';
            }

            $stmt = $this->db->prepare("
                INSERT INTO alert_notifications
                (alert_id, company_id, triggered_at, payload, channels)
                VALUES (:alert_id, :company_id, NOW(), :payload, :channels)
            ");
            
            $stmt->execute([
                'alert_id' => $alert['id'],
                'company_id' => $companyId,
                'payload' => json_encode($payload),
                'channels' => json_encode($channels)
            ]);

            $notificationId = $this->db->lastInsertId();

            // Log audit event
            AuditService::log(
                $companyId,
                null,
                'alert.triggered',
                'alert',
                $alert['id'],
                ['notification_id' => $notificationId, 'alert_key' => $alert['key_name']]
            );

            return $notificationId;
        } catch (\Exception $e) {
            error_log("AlertService: Error creating notification: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create default alerts for a company
     * 
     * @param int $companyId
     */
    public function createDefaultAlerts($companyId) {
        $defaultAlerts = [
            [
                'key_name' => 'low_sms',
                'title' => 'Low SMS Balance',
                'condition_json' => json_encode([
                    'type' => 'metric',
                    'metric' => 'low_sms_balance',
                    'operator' => '<=',
                    'value' => 100
                ]),
                'severity' => 'warning'
            ],
            [
                'key_name' => 'low_stock',
                'title' => 'Low Stock Alert',
                'condition_json' => json_encode([
                    'type' => 'metric',
                    'metric' => 'low_stock_count',
                    'operator' => '>',
                    'value' => 0
                ]),
                'severity' => 'warning'
            ],
            [
                'key_name' => 'negative_profit',
                'title' => 'Negative Day Profit',
                'condition_json' => json_encode([
                    'type' => 'metric',
                    'metric' => 'today_profit',
                    'operator' => '<',
                    'value' => 0
                ]),
                'severity' => 'critical'
            ],
            [
                'key_name' => 'low_stock_forecasted',
                'title' => 'Forecasted Stock Depletion',
                'condition_json' => json_encode([
                    'type' => 'sql',
                    'query' => 'SELECT COUNT(*) as count FROM (SELECT p.id, (p.quantity / NULLIF((SELECT AVG(psi.quantity) FROM pos_sale_items psi INNER JOIN pos_sales ps ON psi.pos_sale_id = ps.id WHERE psi.item_id = p.id AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0)) as days_until_out FROM products p WHERE p.company_id = :company_id AND p.quantity > 0) as forecast WHERE days_until_out > 0 AND days_until_out < 3',
                    'params' => [],
                    'threshold' => 0,
                    'operator' => '>'
                ]),
                'severity' => 'critical'
            ],
            [
                'key_name' => 'low_profit_trend',
                'title' => 'Declining Profit Trend',
                'condition_json' => json_encode([
                    'type' => 'sql',
                    'query' => 'SELECT AVG(daily_profit) as avg_profit FROM (SELECT DATE(created_at) as date, SUM(final_amount - COALESCE((SELECT SUM(psi.quantity * COALESCE(p.cost, p.cost_price, 0)) FROM pos_sale_items psi LEFT JOIN products p ON psi.item_id = p.id WHERE psi.pos_sale_id = ps.id), ps.final_amount * 0.7)) as daily_profit FROM pos_sales ps WHERE company_id = :company_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at)) as daily_profits',
                    'params' => [],
                    'threshold' => 0,
                    'operator' => '<'
                ]),
                'severity' => 'warning'
            ]
        ];

        foreach ($defaultAlerts as $alert) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO alerts (company_id, key_name, title, condition_json, severity, enabled)
                    VALUES (:company_id, :key_name, :title, :condition_json, :severity, 1)
                    ON DUPLICATE KEY UPDATE title = VALUES(title)
                ");
                $stmt->execute([
                    'company_id' => $companyId,
                    'key_name' => $alert['key_name'],
                    'title' => $alert['title'],
                    'condition_json' => $alert['condition_json'],
                    'severity' => $alert['severity']
                ]);
            } catch (\Exception $e) {
                error_log("AlertService: Error creating default alert: " . $e->getMessage());
            }
        }
    }

    /**
     * Get notifications for a company
     * 
     * @param int $companyId
     * @param bool $unhandledOnly
     * @param int $limit
     * @return array
     */
    public function getNotifications($companyId, $unhandledOnly = true, $limit = 50) {
        // Check if tables exist
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'alert_notifications'");
            if ($checkTable->rowCount() == 0) {
                return [];
            }
        } catch (\Exception $e) {
            error_log("AlertService::getNotifications - Could not check alert_notifications table: " . $e->getMessage());
            return [];
        }
        
        try {
            $where = "company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($unhandledOnly) {
                $where .= " AND handled = 0";
            }

            $stmt = $this->db->prepare("
                SELECT 
                    an.*,
                    a.title as alert_title,
                    a.severity,
                    a.key_name
                FROM alert_notifications an
                INNER JOIN alerts a ON an.alert_id = a.id
                WHERE {$where}
                ORDER BY an.triggered_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($results as &$result) {
                if (!empty($result['payload'])) {
                    $result['payload'] = json_decode($result['payload'], true);
                }
                if (!empty($result['channels'])) {
                    $result['channels'] = json_decode($result['channels'], true);
                }
            }

            return $results;
        } catch (\Exception $e) {
            error_log("AlertService::getNotifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Acknowledge/handle an alert notification
     * 
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function acknowledgeNotification($notificationId, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE alert_notifications
                SET handled = 1, handled_by = :user_id, handled_at = NOW()
                WHERE id = :notification_id
            ");
            $stmt->execute([
                'notification_id' => $notificationId,
                'user_id' => $userId
            ]);

            // Log audit event
            $notif = $this->db->prepare("SELECT company_id FROM alert_notifications WHERE id = :id");
            $notif->execute(['id' => $notificationId]);
            $result = $notif->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                AuditService::log(
                    $result['company_id'],
                    $userId,
                    'alert.acknowledged',
                    'alert_notification',
                    $notificationId,
                    []
                );
            }

            return true;
        } catch (\Exception $e) {
            error_log("AlertService: Error acknowledging notification: " . $e->getMessage());
            return false;
        }
    }
}

