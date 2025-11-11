-- Audit Logs Table - Immutable Event Store
-- Phase 3: Advanced Intelligence & Audit Logging

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  user_id BIGINT NULL,
  event_type VARCHAR(100) NOT NULL COMMENT 'e.g., sale.created, swap.completed, user.login',
  entity_type VARCHAR(50) NULL COMMENT 'e.g., pos_sale, swap, repair',
  entity_id BIGINT NULL COMMENT 'id of the related entity',
  payload JSON NULL COMMENT 'full event data (snapshot)',
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  indexed_for_search TINYINT(1) DEFAULT 0,
  
  INDEX idx_company_id (company_id),
  INDEX idx_user_id (user_id),
  INDEX idx_event_type (event_type),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_created_at (created_at),
  INDEX idx_company_event (company_id, event_type, created_at),
  FULLTEXT INDEX ft_payload (payload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immutable audit trail - append-only event log';

