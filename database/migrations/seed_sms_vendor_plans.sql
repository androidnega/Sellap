-- Seed SMS vendor plans with Arkasel bundles
INSERT INTO sms_vendor_plans (vendor_name, label, cost_amount, messages, expires_in_days) VALUES
('Arkasel', 'GHS20 - 645 (No Expiry)', 20.00, 645, NULL),
('Arkasel', 'GHS50 - 1667', 50.00, 1667, NULL),
('Arkasel', 'GHS100 - 3448', 100.00, 3448, NULL),
('Arkasel', 'GHS200 - 7905 (Expires)', 200.00, 7905, 30),
('Arkasel', 'GHS500 - 20704 (Expires)', 500.00, 20704, 30),
('Arkasel', 'GHS1000 - 43478', 1000.00, 43478, NULL),
('Arkasel', 'GHS2000 - 99533', 2000.00, 99533, NULL),
('Arkasel', 'GHS200 - 7143 (No Expiry)', 200.00, 7143, NULL),
('Arkasel', 'GHS500 - 18519 (No Expiry)', 500.00, 18519, NULL)
ON DUPLICATE KEY UPDATE 
    vendor_name = VALUES(vendor_name),
    label = VALUES(label),
    cost_amount = VALUES(cost_amount),
    messages = VALUES(messages),
    expires_in_days = VALUES(expires_in_days);

