-- PHASE H: Reset Metrics Table for Monitoring & Telemetry
-- Stores metrics for reset operations: timing, row counts, etc.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example metrics stored:
-- metric_name: 'reset.company.started', 'reset.company.completed', 'reset.system.started', etc.
-- tags: { company_id: '123', admin_user_id: '456', dry_run: 'false' }
-- fields: { total_rows: 5000, duration_ms: 1234.5, duration_seconds: 1.234 }

