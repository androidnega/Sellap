-- Create system_settings table for storing configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('cloudinary_cloud_name', '', 'Cloudinary cloud name for image storage'),
('cloudinary_api_key', '', 'Cloudinary API key'),
('cloudinary_api_secret', '', 'Cloudinary API secret'),
('sms_api_key', '', 'Arkasel SMS API key'),
('sms_sender_id', 'SellApp', 'SMS sender ID'),
('default_image_quality', 'auto', 'Default image quality for uploads'),
('sms_purchase_enabled', '1', 'Enable SMS notifications for purchases'),
('sms_repair_enabled', '1', 'Enable SMS notifications for repairs'),
('sms_swap_enabled', '1', 'Enable SMS notifications for swaps'),
('app_name', 'SellApp', 'Application name'),
('app_version', '1.0.0', 'Application version'),
('paypal_client_id', '', 'PayPal Client ID for SMS credit purchases'),
('paypal_client_secret', '', 'PayPal Client Secret for SMS credit purchases'),
('paypal_mode', 'sandbox', 'PayPal mode: sandbox or live'),
('sms_credit_rate', '0.05', 'Price per SMS credit in GHS (default: 0.05)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

