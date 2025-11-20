-- =====================================================
-- POS SALE PAYMENTS TABLE (Track Individual Payments for Partial Payment System)
-- =====================================================
CREATE TABLE IF NOT EXISTS pos_sale_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_sale_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER') DEFAULT 'CASH',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by_user_id BIGINT UNSIGNED NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_sale (pos_sale_id),
    INDEX idx_company (company_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_recorded_by (recorded_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



