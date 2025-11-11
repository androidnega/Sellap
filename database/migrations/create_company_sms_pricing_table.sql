-- Create company_sms_pricing table for dynamic pricing per company
CREATE TABLE IF NOT EXISTS company_sms_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    vendor_plan_id INT NOT NULL,
    markup_percent DECIMAL(5,2) DEFAULT 90.00 COMMENT '90% = factor 1.9',
    custom_price DECIMAL(12,2) NULL COMMENT 'Override computed price if set',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_plan_id) REFERENCES sms_vendor_plans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_vendor_plan (company_id, vendor_plan_id),
    INDEX idx_company_id (company_id),
    INDEX idx_vendor_plan_id (vendor_plan_id),
    INDEX idx_active (active)
);

