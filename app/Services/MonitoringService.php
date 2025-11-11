<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

/**
 * Monitoring & Telemetry Service (PHASE H)
 * Handles metrics emission, timing, and rate limiting for reset operations
 */
class MonitoringService {
    private $db;
    
    // Static storage for timers (CLI/API compatible)
    private static $activeTimers = [];
    
    // Rate limiting configuration
    private $rateLimits = [
        'company_reset' => [
            'max_per_hour' => 5,
            'max_per_day' => 20
        ],
        'system_reset' => [
            'max_per_hour' => 1,
            'max_per_day' => 3
        ]
    ];
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }
    
    /**
     * Emit metric event
     * 
     * @param string $metricName e.g., 'reset.company.started', 'reset.company.completed'
     * @param array $tags Additional metadata (company_id, admin_user_id, etc.)
     * @param array $fields Numeric fields (duration, row_count, etc.)
     */
    public function emitMetric($metricName, $tags = [], $fields = []) {
        $metric = [
            'name' => $metricName,
            'timestamp' => time(),
            'tags' => $tags,
            'fields' => $fields
        ];
        
        // Log to database (metrics table)
        $this->logMetricToDatabase($metric);
        
        // Log to file for external monitoring systems
        $this->logMetricToFile($metric);
        
        // Emit to external monitoring stack (if configured)
        $this->emitToExternalStack($metric);
        
        return $metric;
    }
    
    /**
     * Start timing an operation
     * Returns timer ID to use with stopTiming()
     * Uses static storage to work in CLI/API contexts
     */
    public function startTiming($operationName, $context = []) {
        $timerId = uniqid('timer_', true);
        
        // Store in static variable accessible by stopTiming
        self::$activeTimers[$timerId] = [
            'operation' => $operationName,
            'start_time' => microtime(true),
            'start_datetime' => date('Y-m-d H:i:s'),
            'context' => $context
        ];
        
        return $timerId;
    }
    
    /**
     * Stop timing and return duration
     */
    public function stopTiming($timerId) {
        if (!isset(self::$activeTimers[$timerId])) {
            return ['duration' => 0, 'duration_ms' => 0, 'timer' => null];
        }
        
        $timer = self::$activeTimers[$timerId];
        $duration = microtime(true) - $timer['start_time'];
        $durationMs = round($duration * 1000, 2);
        
        // Store in timer record
        $timer['duration'] = $duration;
        $timer['duration_ms'] = $durationMs;
        $timer['end_time'] = microtime(true);
        $timer['end_datetime'] = date('Y-m-d H:i:s');
        
        // Log timing metric
        $this->emitMetric(
            $timer['operation'] . '.duration',
            $timer['context'],
            [
                'duration_ms' => $durationMs,
                'duration_seconds' => round($duration, 3)
            ]
        );
        
        unset(self::$activeTimers[$timerId]);
        
        return [
            'duration' => $duration,
            'duration_ms' => $durationMs,
            'timer' => $timer
        ];
    }
    
    /**
     * Check if reset operation is rate-limited
     * 
     * @param int $adminUserId Admin user ID
     * @param string $actionType 'company_reset' or 'system_reset'
     * @param int|null $companyId Company ID (for company reset)
     * @return array ['allowed' => bool, 'reason' => string, 'limits' => array]
     */
    public function checkRateLimit($adminUserId, $actionType, $companyId = null) {
        if (!isset($this->rateLimits[$actionType])) {
            return ['allowed' => true, 'reason' => 'No rate limit configured'];
        }
        
        $limits = $this->rateLimits[$actionType];
        $now = date('Y-m-d H:i:s');
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-1 day'));
        
        // Count resets in last hour
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM admin_actions 
            WHERE admin_user_id = ? 
            AND action_type = ?
            AND dry_run = 0
            AND created_at >= ?
        ");
        $stmt->execute([$adminUserId, $actionType, $oneHourAgo]);
        $countLastHour = (int)$stmt->fetchColumn();
        
        // Count resets in last day
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM admin_actions 
            WHERE admin_user_id = ? 
            AND action_type = ?
            AND dry_run = 0
            AND created_at >= ?
        ");
        $stmt->execute([$adminUserId, $actionType, $oneDayAgo]);
        $countLastDay = (int)$stmt->fetchColumn();
        
        // Check limits
        if ($countLastHour >= $limits['max_per_hour']) {
            return [
                'allowed' => false,
                'reason' => "Rate limit exceeded: {$countLastHour}/{$limits['max_per_hour']} resets in the last hour",
                'limits' => $limits,
                'counts' => [
                    'last_hour' => $countLastHour,
                    'last_day' => $countLastDay
                ]
            ];
        }
        
        if ($countLastDay >= $limits['max_per_day']) {
            return [
                'allowed' => false,
                'reason' => "Rate limit exceeded: {$countLastDay}/{$limits['max_per_day']} resets in the last day",
                'limits' => $limits,
                'counts' => [
                    'last_hour' => $countLastHour,
                    'last_day' => $countLastDay
                ]
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => 'Within rate limits',
            'limits' => $limits,
            'counts' => [
                'last_hour' => $countLastHour,
                'last_day' => $countLastDay
            ]
        ];
    }
    
    /**
     * Log metric to database
     */
    private function logMetricToDatabase($metric) {
        try {
            // Create metrics table if it doesn't exist (lazy creation)
            $this->ensureMetricsTableExists();
            
            $stmt = $this->db->prepare("
                INSERT INTO reset_metrics (
                    metric_name,
                    timestamp,
                    tags,
                    fields,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $metric['name'],
                $metric['timestamp'],
                json_encode($metric['tags']),
                json_encode($metric['fields'])
            ]);
        } catch (\Exception $e) {
            // Silently fail - metrics shouldn't break the application
            error_log("Failed to log metric to database: " . $e->getMessage());
        }
    }
    
    /**
     * Log metric to file (for external monitoring systems)
     */
    private function logMetricToFile($metric) {
        try {
            $logDir = __DIR__ . '/../../storage/logs/metrics/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            $logFile = $logDir . 'metrics_' . date('Y-m-d') . '.log';
            $logLine = json_encode($metric) . "\n";
            
            file_put_contents($logFile, $logLine, FILE_APPEND);
        } catch (\Exception $e) {
            error_log("Failed to log metric to file: " . $e->getMessage());
        }
    }
    
    /**
     * Emit metric to external monitoring stack (StatsD, Prometheus, etc.)
     */
    private function emitToExternalStack($metric) {
        // Integration points for external monitoring:
        // - StatsD: Send UDP packets
        // - Prometheus: Push gateway
        // - CloudWatch: AWS SDK
        // - Custom HTTP endpoint
        
        // Example: HTTP endpoint
        $monitoringEndpoint = getenv('MONITORING_ENDPOINT');
        if ($monitoringEndpoint) {
            try {
                $ch = curl_init($monitoringEndpoint);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metric));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Don't block on metrics
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                // Silently fail
                error_log("Failed to emit metric to external stack: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Ensure metrics table exists
     */
    private function ensureMetricsTableExists() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS reset_metrics (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    metric_name VARCHAR(100) NOT NULL,
                    timestamp BIGINT NOT NULL,
                    tags JSON NULL,
                    fields JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_metric_name (metric_name),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Exception $e) {
            // Table might already exist or error
            error_log("Metrics table creation: " . $e->getMessage());
        }
    }
    
    /**
     * Get metrics for operator review
     * 
     * @param string|null $metricName Filter by metric name
     * @param int $limit Number of records to return
     * @return array
     */
    public function getMetrics($metricName = null, $limit = 100) {
        try {
            $this->ensureMetricsTableExists();
            
            $sql = "SELECT * FROM reset_metrics WHERE 1=1";
            $params = [];
            
            if ($metricName) {
                $sql .= " AND metric_name = ?";
                $params[] = $metricName;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $metrics = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($metrics as &$metric) {
                $metric['tags'] = json_decode($metric['tags'], true);
                $metric['fields'] = json_decode($metric['fields'], true);
            }
            
            return $metrics;
        } catch (\Exception $e) {
            error_log("Failed to get metrics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get reset operation statistics
     * 
     * @param int|null $adminUserId Filter by admin user
     * @param string|null $actionType Filter by action type
     * @param int $days Number of days to look back
     * @return array
     */
    public function getResetStatistics($adminUserId = null, $actionType = null, $days = 30) {
        $sinceDate = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $sql = "
            SELECT 
                action_type,
                COUNT(*) as total_count,
                SUM(CASE WHEN dry_run = 1 THEN 1 ELSE 0 END) as dry_run_count,
                SUM(CASE WHEN dry_run = 0 THEN 1 ELSE 0 END) as actual_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration_seconds
            FROM admin_actions
            WHERE created_at >= ?
        ";
        
        $params = [$sinceDate];
        
        if ($adminUserId) {
            $sql .= " AND admin_user_id = ?";
            $params[] = $adminUserId;
        }
        
        if ($actionType) {
            $sql .= " AND action_type = ?";
            $params[] = $actionType;
        }
        
        $sql .= " GROUP BY action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

