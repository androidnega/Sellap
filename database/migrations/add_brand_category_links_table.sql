-- Migration: Add brand_category_links table for multi-category brands
-- Date: 2025-11-23

CREATE TABLE IF NOT EXISTS brand_category_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_brand_category (brand_id, category_id),
    INDEX idx_brand_category_brand (brand_id),
    INDEX idx_brand_category_category (category_id),
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Backfill legacy primary category assignments
INSERT IGNORE INTO brand_category_links (brand_id, category_id)
SELECT id, category_id
FROM brands
WHERE category_id IS NOT NULL;

