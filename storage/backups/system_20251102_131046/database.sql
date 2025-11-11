-- Full System Backup SQL
-- Created: 2025-11-02 13:10:48


-- Table: brands
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `category_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `brands_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brands` VALUES
('1', 'Apple', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('2', 'Samsung', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('3', 'Huawei', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('4', 'Xiaomi', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('5', 'OnePlus', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('6', 'Google', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('7', 'Sony', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('8', 'LG', '1', '1', '2025-10-24 14:55:01', '2025-10-24 14:55:01'),
('9', 'Generic', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('10', 'OEM', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('11', 'Anker', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('12', 'Baseus', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('13', 'Belkin', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('14', 'Spigen', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('15', 'OtterBox', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('16', 'UAG', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('17', 'Caseology', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('18', 'ESR', '2', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('19', 'Generic', '5', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('20', 'OEM', '5', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('21', 'iFixit', '5', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('22', 'RepairTech', '5', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('23', 'MobileParts', '5', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('24', 'Apple', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('25', 'Samsung', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('26', 'Tecno', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('27', 'Infinix', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('28', 'Huawei', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('29', 'Xiaomi', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('30', 'OnePlus', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('31', 'Google', '1', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('32', 'Apple', '4', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('33', 'Samsung', '4', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('34', 'Huawei', '4', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('35', 'Lenovo', '4', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('36', 'Microsoft', '4', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('37', 'Anker', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('38', 'Baseus', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('39', 'Generic', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('40', 'OEM', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('41', 'Belkin', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('42', 'Spigen', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('43', 'JBL', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('44', 'Sony', '2', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('45', 'Apple', '6', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('46', 'Samsung', '6', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('47', 'Fitbit', '6', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('48', 'Garmin', '6', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30'),
('49', 'Huawei', '6', '1', '2025-10-24 18:53:30', '2025-10-24 18:53:30');


-- Table: categories
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES
('1', 'Phone', 'Mobile phones and smartphones', '1', '2025-10-24 14:44:38', '2025-10-24 14:44:38'),
('2', 'Accessory', 'Phone accessories like cases, chargers, etc.', '1', '2025-10-24 14:44:38', '2025-10-24 14:44:38'),
('3', 'Others', 'Miscellaneous products or repair parts', '1', '2025-10-24 14:44:38', '2025-10-24 14:44:38'),
('4', 'Tablet', 'Tablets and iPads', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('5', 'Repair Parts', 'Parts used for phone and device repairs', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15'),
('6', 'Wearables', 'Smartwatches, fitness trackers, and wearable devices', '1', '2025-10-24 15:56:15', '2025-10-24 15:56:15');


-- Table: companies
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_created_by` (`created_by_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `companies` VALUES
('1', 'Mhannuellens', 'kwofiee3@gmail.com', '+233541069241', 'WH-0001-2124', NULL, 'active', NULL, '2025-10-24 05:42:40', '2025-10-24 05:42:40'),
('2', 'PhoneHub Ltd', 'contact@phonehub.com', '+233501234567', 'Kumasi, Ghana', NULL, 'active', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38');


-- Table: company_modules
DROP TABLE IF EXISTS `company_modules`;
CREATE TABLE `company_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_company_module` (`company_id`,`module_key`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_module_key` (`module_key`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_company_enabled` (`company_id`,`enabled`),
  CONSTRAINT `company_modules_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `company_modules` VALUES
('1', '1', 'products_inventory', '1', '2025-11-02 08:55:24', '2025-11-02 08:55:24'),
('2', '1', 'pos_sales', '1', '2025-11-02 08:55:26', '2025-11-02 08:55:26'),
('3', '1', 'repairs', '1', '2025-11-02 08:55:30', '2025-11-02 08:55:30'),
('4', '1', 'swap', '1', '2025-11-02 08:55:32', '2025-11-02 08:55:32'),
('5', '1', 'staff_management', '1', '2025-11-02 08:55:35', '2025-11-02 08:55:35'),
('6', '1', 'customers', '1', '2025-11-02 08:55:38', '2025-11-02 08:55:38'),
('7', '1', 'reports_analytics', '1', '2025-11-02 08:55:40', '2025-11-02 08:55:40'),
('8', '1', 'notifications_sms', '1', '2025-11-02 08:55:44', '2025-11-02 08:55:44');


-- Table: company_sms_accounts
DROP TABLE IF EXISTS `company_sms_accounts`;
CREATE TABLE `company_sms_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `total_sms` int(11) NOT NULL DEFAULT 0,
  `sms_used` int(11) NOT NULL DEFAULT 0,
  `sms_remaining` int(11) GENERATED ALWAYS AS (`total_sms` - `sms_used`) STORED,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `sms_sender_name` varchar(15) NOT NULL DEFAULT 'SellApp',
  `custom_sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`),
  CONSTRAINT `company_sms_accounts_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `company_sms_accounts` VALUES
('1', '1', '10', '0', '10', 'active', 'Mhannuellens', '1', '2025-11-02 07:07:19', '2025-11-02 07:21:56'),
('2', '2', '5', '0', '5', 'active', 'SellApp', '0', '2025-11-02 07:07:19', '2025-11-02 07:11:00');


-- Table: customer_products
DROP TABLE IF EXISTS `customer_products`;
CREATE TABLE `customer_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specs`)),
  `condition` enum('new','used','faulty') DEFAULT 'used',
  `estimated_value` decimal(10,2) DEFAULT 0.00,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('in_stock','sold','swapped') DEFAULT 'in_stock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_brand` (`brand`),
  KEY `idx_condition` (`condition`),
  CONSTRAINT `customer_products_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customer_products` VALUES
('11', '1', 'DJI', 'Air2', NULL, 'used', '960.00', '2025-10-31 15:16:26', 'in_stock', 'Received in swap', '2025-10-31 15:16:26', '2025-10-31 15:16:26'),
('12', '1', 'DJI', 'Air2', NULL, 'used', '200.00', '2025-10-31 15:19:53', 'in_stock', 'Received in swap', '2025-10-31 15:19:53', '2025-10-31 15:19:53'),
('13', '1', 'DJI', 'Air2', NULL, 'used', '200.00', '2025-10-31 15:26:11', 'in_stock', 'Received in swap', '2025-10-31 15:26:11', '2025-10-31 15:26:11'),
('14', '1', 'DJI', 'Air2', NULL, 'used', '200.00', '2025-10-31 15:30:18', 'in_stock', 'Received in swap', '2025-10-31 15:30:18', '2025-10-31 15:30:18'),
('15', '1', 'DJI', 'Air2', NULL, 'used', '200.00', '2025-10-31 15:42:16', 'in_stock', 'Received in swap', '2025-10-31 15:42:16', '2025-10-31 15:42:16'),
('16', '1', 'DJI', 'Air2', NULL, 'used', '200.00', '2025-10-31 15:45:25', 'in_stock', 'Received in swap', '2025-10-31 15:45:25', '2025-10-31 15:45:25'),
('17', '1', 'Samsung', 'Galaxy a12', NULL, 'used', '200.00', '2025-10-31 16:25:28', 'in_stock', 'Received in swap', '2025-10-31 16:25:28', '2025-10-31 16:25:28'),
('18', '1', 'Samsung', 'S18', NULL, 'new', '345.00', '2025-10-31 22:30:15', 'in_stock', 'Received in swap', '2025-10-31 22:30:15', '2025-10-31 22:30:15'),
('19', '1', 'Samsung', 'm22 nokia', NULL, 'used', '3500.00', '2025-10-31 23:50:00', 'in_stock', 'Received in swap', '2025-10-31 23:50:00', '2025-10-31 23:50:00'),
('20', '1', 'Samsung', 'S10+', NULL, 'new', '1500.00', '2025-11-01 06:28:48', 'in_stock', 'Received in swap', '2025-11-01 06:28:48', '2025-11-01 06:28:48');


-- Table: customers
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `unique_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `idx_phone` (`phone_number`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` VALUES
('1', '1', 'CUS6900A52348056', 'MERCY HOWARD', '0538370699', 'asantewaaabena303@gmail.com', 'WH-0001-2124', '1', '2025-10-28 11:12:35', '2025-10-28 11:12:35'),
('2', '1', 'CUS6900AE0999D8C', 'Emmanuel Kwofie', '0597749930', 'kwofiee3@gmail.com', NULL, '1', '2025-10-28 11:50:33', '2025-10-28 11:50:33');


-- Table: notification_logs
DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_phone` (`phone_number`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notification_logs` VALUES
('1', 'test_sms', '0257940791', '1', 'Test SMS sent successfully', '2025-11-01 13:53:55'),
('2', 'test_sms', '0257940791', '1', 'Test SMS sent successfully', '2025-11-01 14:30:47');


-- Table: notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `message` text NOT NULL,
  `type` enum('repair','stock','swap','sale','system') DEFAULT 'system',
  `status` enum('unread','read') DEFAULT 'unread',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: phones
DROP TABLE IF EXISTS `phones`;
CREATE TABLE `phones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `unique_id` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `imei` varchar(20) DEFAULT NULL,
  `phone_condition` enum('new','excellent','good','fair','poor') DEFAULT 'good',
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `phone_value` decimal(10,2) NOT NULL,
  `status` enum('AVAILABLE','SOLD','RESERVED','SWAPPED') DEFAULT 'AVAILABLE',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `imei` (`imei`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_imei` (`imei`),
  KEY `idx_status` (`status`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `phones_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `phones` VALUES
('1', '1', 'PHN-2025-6502', 'Samsung', 'Galaxy A12', NULL, 'good', NULL, '3230.00', '3230.00', 'AVAILABLE', NULL, '2025-10-31 15:16:26', '2025-10-31 15:16:26'),
('2', '1', 'PHN-2025-8770', 'Samsung', 'Galaxy A19', NULL, 'good', NULL, '3454.00', '3454.00', 'AVAILABLE', NULL, '2025-10-31 22:30:15', '2025-10-31 22:30:15'),
('3', '1', 'PHN-2025-3934', 'Samsung', 'Galaxy s22 Ultra', NULL, 'good', NULL, '2500.00', '2500.00', 'AVAILABLE', NULL, '2025-11-01 06:28:48', '2025-11-01 06:28:48');


-- Table: pos_sale_items
DROP TABLE IF EXISTS `pos_sale_items`;
CREATE TABLE `pos_sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pos_sale_id` bigint(20) unsigned NOT NULL,
  `item_type` enum('PHONE','ACCESSORY','PART','SERVICE','OTHER') DEFAULT 'OTHER',
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale` (`pos_sale_id`),
  CONSTRAINT `pos_sale_items_ibfk_1` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pos_sale_items` VALUES
('1', '1', '', '4', 'Product', '2', '10000.00', '20000.00', '2025-10-26 06:13:22'),
('2', '2', '', '4', 'Samsung S33 Ultra', '4', '10000.00', '40000.00', '2025-10-26 06:48:42'),
('3', '3', '', '4', 'Samsung S33 Ultra', '2', '10000.00', '20000.00', '2025-10-27 23:04:27'),
('4', '3', '', '5', 'Galaxy A12', '1', '3230.00', '3230.00', '2025-10-27 23:04:27'),
('5', '4', '', '4', 'Samsung S33 Ultra', '3', '10000.00', '30000.00', '2025-10-27 23:50:54'),
('6', '5', '', '4', 'Samsung S33 Ultra', '2', '10000.00', '20000.00', '2025-10-27 23:51:38'),
('7', '6', '', '5', 'Galaxy A12', '3', '3230.00', '9690.00', '2025-10-28 04:31:57'),
('8', '7', 'PHONE', '5', 'Galaxy A12', '1', '3230.00', '3230.00', '2025-10-31 15:45:25'),
('9', '8', 'PHONE', '5', 'Galaxy A12', '1', '3230.00', '3230.00', '2025-10-31 16:25:28'),
('10', '9', 'PHONE', '95', 'Galaxy A19', '1', '3454.00', '3454.00', '2025-10-31 22:30:15'),
('11', '10', 'PHONE', '95', 'Galaxy A19', '1', '3454.00', '3454.00', '2025-10-31 23:50:00'),
('12', '11', 'PHONE', '100', 'Galaxy s22 Ultra', '1', '2500.00', '2500.00', '2025-11-01 06:28:48'),
('13', '12', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 06:43:21'),
('14', '13', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 06:43:25'),
('15', '14', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 06:52:24'),
('16', '15', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 06:55:26'),
('17', '16', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 06:55:31'),
('18', '17', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:09:34'),
('19', '18', 'OTHER', '94', 'Galaxy A16', '1', '3454.00', '3454.00', '2025-11-01 07:09:42'),
('20', '19', 'OTHER', '94', 'Galaxy A16', '1', '3454.00', '3454.00', '2025-11-01 07:09:51'),
('21', '20', 'OTHER', '94', 'Galaxy A16', '1', '3454.00', '3454.00', '2025-11-01 07:14:20'),
('22', '21', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:14:26'),
('23', '22', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:17:27'),
('24', '23', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:17:38'),
('25', '24', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:21:40'),
('26', '25', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:22:11'),
('27', '26', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:26:23'),
('28', '27', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:26:28'),
('29', '28', 'OTHER', '101', 'Samsung S10+', '1', '1500.00', '1500.00', '2025-11-01 07:28:28'),
('30', '29', 'OTHER', '94', 'Galaxy A16', '1', '3454.00', '3454.00', '2025-11-01 07:28:56');


-- Table: pos_sales
DROP TABLE IF EXISTS `pos_sales`;
CREATE TABLE `pos_sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `unique_id` varchar(50) NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('CASH','MOBILE_MONEY','CARD','BANK_TRANSFER') DEFAULT 'CASH',
  `payment_status` enum('PAID','PARTIAL','UNPAID') DEFAULT 'PAID',
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_date` (`created_at`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `pos_sales_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pos_sales_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pos_sales` VALUES
('1', '1', 'POS68FDBC024F420', NULL, '20000.00', '0.00', '0.00', '20000.00', 'MOBILE_MONEY', 'PAID', '15', '', '2025-10-26 06:13:22', '2025-10-26 06:13:22'),
('2', '1', 'POS68FDC44AC3CFB', NULL, '40000.00', '0.00', '0.00', '40000.00', 'CASH', 'PAID', '15', '', '2025-10-26 06:48:42', '2025-10-26 06:48:42'),
('3', '1', 'POS68FFFA7BA1AB4', NULL, '23230.00', '0.00', '0.00', '23230.00', 'CASH', 'PAID', '15', '', '2025-10-27 23:04:27', '2025-10-27 23:04:27'),
('4', '1', 'POS6900055E6135D', NULL, '30000.00', '0.00', '0.00', '30000.00', 'CASH', 'PAID', '15', '', '2025-10-27 23:50:54', '2025-10-27 23:50:54'),
('5', '1', 'POS6900058A3C9D1', NULL, '20000.00', '0.00', '0.00', '20000.00', 'CASH', 'PAID', '15', '', '2025-10-27 23:51:38', '2025-10-27 23:51:38'),
('6', '1', 'POS6900473D0F02F', NULL, '9690.00', '0.00', '0.00', '9690.00', 'CASH', 'PAID', '15', '', '2025-10-28 04:31:57', '2025-10-28 04:31:57'),
('7', '1', 'POS6904D9955DF0C', '2', '3230.00', '0.00', '0.00', '3230.00', 'CASH', 'PAID', '15', 'Swap transaction: SWP-20256733', '2025-10-31 15:45:25', '2025-10-31 15:45:25'),
('8', '1', 'POS6904E2F89B0C5', '2', '3230.00', '0.00', '0.00', '3230.00', 'CASH', 'PAID', '15', 'Swap transaction: SWP-20254882', '2025-10-31 16:25:28', '2025-10-31 16:25:28'),
('9', '1', 'POS69053877C7FCD', '1', '3454.00', '0.00', '0.00', '3454.00', 'CASH', 'PAID', '15', 'Swap transaction: SWP-20259595', '2025-10-31 22:30:15', '2025-10-31 22:30:15'),
('10', '1', 'POS69054B28AA339', '2', '3454.00', '0.00', '0.00', '3454.00', 'CASH', 'PAID', '15', 'Swap transaction: SWP-20259890', '2025-10-31 23:50:00', '2025-10-31 23:50:00'),
('11', '1', 'POS6905A8A0A3E95', '2', '2500.00', '0.00', '0.00', '2500.00', 'CASH', 'PAID', '15', 'Swap transaction: SWP-20250990', '2025-11-01 06:28:48', '2025-11-01 06:28:48'),
('12', '1', 'POS6905AC09655C3', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 06:43:21', '2025-11-01 06:43:21'),
('13', '1', 'POS6905AC0D96D88', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 06:43:25', '2025-11-01 06:43:25'),
('14', '1', 'POS6905AE281DE27', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 06:52:24', '2025-11-01 06:52:24'),
('15', '1', 'POS6905AEDE68548', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 06:55:26', '2025-11-01 06:55:26'),
('16', '1', 'POS6905AEE33F6AB', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 06:55:31', '2025-11-01 06:55:31'),
('17', '1', 'POS6905B22E80167', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:09:34', '2025-11-01 07:09:34'),
('18', '1', 'POS6905B23636F0F', NULL, '3454.00', '0.00', '0.00', '3454.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:09:42', '2025-11-01 07:09:42'),
('19', '1', 'POS6905B23F0BEC6', NULL, '3454.00', '0.00', '0.00', '3454.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:09:51', '2025-11-01 07:09:51'),
('20', '1', 'POS6905B34C58B27', NULL, '4954.00', '0.00', '0.00', '4954.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:14:20', '2025-11-01 07:14:20'),
('21', '1', 'POS6905B352EF91F', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:14:26', '2025-11-01 07:14:26'),
('22', '1', 'POS6905B4078E739', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:17:27', '2025-11-01 07:17:27'),
('23', '1', 'POS6905B41217218', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:17:38', '2025-11-01 07:17:38'),
('24', '1', 'POS6905B5046809E', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:21:40', '2025-11-01 07:21:40'),
('25', '1', 'POS6905B523A3CAD', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:22:11', '2025-11-01 07:22:11'),
('26', '1', 'POS6905B61FE154E', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:26:23', '2025-11-01 07:26:23'),
('27', '1', 'POS6905B6245D927', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:26:28', '2025-11-01 07:26:28'),
('28', '1', 'POS6905B69C357A9', NULL, '1500.00', '0.00', '0.00', '1500.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:28:28', '2025-11-01 07:28:28'),
('29', '1', 'POS6905B6B8C907A', NULL, '3454.00', '0.00', '0.00', '3454.00', 'CASH', 'PAID', '15', '', '2025-11-01 07:28:56', '2025-11-01 07:28:56');


-- Table: product_categories
DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_categories` VALUES
('1', 'phone', 'Phone', 'Mobile phones and smartphones', '1', '2025-10-24 09:54:58'),
('2', 'accessory', 'Accessory', 'Phone accessories like cases, chargers, etc.', '1', '2025-10-24 09:54:58'),
('3', 'repair_part', 'Repair Part', 'Parts used for phone repairs', '1', '2025-10-24 09:54:58');


-- Table: product_images
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_images` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products_old_backup` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table: product_specs
DROP TABLE IF EXISTS `product_specs`;
CREATE TABLE `product_specs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `spec_key` varchar(120) NOT NULL,
  `spec_value` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_specs` (`product_id`,`spec_key`),
  KEY `idx_spec_key` (`spec_key`),
  CONSTRAINT `product_specs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `product_specs` VALUES
('18', '4', 'model', 'SM-G6798', '2025-10-25 14:09:33'),
('19', '4', 'storage', '256GB', '2025-10-25 14:09:33'),
('20', '4', 'ram', '12GB', '2025-10-25 14:09:33'),
('21', '4', 'network', '4G LTE', '2025-10-25 14:09:33'),
('22', '4', 'color', 'Gray', '2025-10-25 14:09:33'),
('52', '5', 'model', 'SM-BCDE12', '2025-10-28 05:26:32'),
('53', '5', 'storage', '128GB', '2025-10-28 05:26:32'),
('54', '5', 'ram', '6GB', '2025-10-28 05:26:32'),
('55', '5', 'network', '4G LTE', '2025-10-28 05:26:32'),
('56', '5', 'color', 'Grey', '2025-10-28 05:26:32'),
('57', '94', 'model', 'SM-AXSD-14', '2025-10-31 21:35:10'),
('58', '94', 'storage', '128GB', '2025-10-31 21:35:10'),
('59', '94', 'ram', '8GB', '2025-10-31 21:35:10'),
('60', '94', 'network', '5G', '2025-10-31 21:35:10'),
('61', '94', 'color', 'Black', '2025-10-31 21:35:10'),
('62', '95', 'model', 'SN-AXSFD', '2025-10-31 22:29:12'),
('63', '95', 'storage', '256GB', '2025-10-31 22:29:12'),
('64', '95', 'ram', '6GB', '2025-10-31 22:29:12'),
('65', '95', 'network', '5G', '2025-10-31 22:29:12'),
('66', '95', 'color', 'Black', '2025-10-31 22:29:12'),
('72', '100', 'model', 'SM-BCTFD', '2025-11-01 06:27:27'),
('73', '100', 'storage', '128GB', '2025-11-01 06:27:27'),
('74', '100', 'ram', '6GB', '2025-11-01 06:27:27'),
('75', '100', 'network', '5G', '2025-11-01 06:27:27'),
('76', '100', 'color', 'Black', '2025-11-01 06:27:27');


-- Table: products
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` varchar(20) DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specs`)),
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `quantity` int(11) DEFAULT 0,
  `item_location` varchar(100) DEFAULT NULL,
  `available_for_swap` tinyint(1) DEFAULT 0,
  `status` enum('available','sold','swapped','out_of_stock') DEFAULT 'available',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sku` varchar(100) DEFAULT NULL,
  `model_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `weight` varchar(50) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sku` (`sku`),
  UNIQUE KEY `product_id` (`product_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_company` (`company_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_brand` (`brand_id`),
  KEY `idx_status` (`status`),
  KEY `idx_swap` (`available_for_swap`),
  KEY `idx_subcategory` (`subcategory_id`),
  KEY `idx_products_subcategory` (`subcategory_id`),
  KEY `idx_products_brand` (`brand_id`),
  KEY `idx_products_sku` (`sku`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_item_location` (`item_location`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_3` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  CONSTRAINT `products_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `products_ibfk_5` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` VALUES
('4', 'PID-004', '1', 'Samsung S33 Ultra', '1', '25', NULL, NULL, '5500.00', '8000.00', '36', NULL, '0', 'available', '6', '2025-10-25 14:09:33', '2025-10-31 10:32:15', 'SKU-20251025-7541', 'S20 Ultra', '', NULL, '', '', 'DailyCoins'),
('5', 'GALAXYA13364', '1', 'Galaxy A12', '1', '25', NULL, '{\"model\":\"SM-BCDE12\",\"storage\":\"128GB\",\"ram\":\"6GB\",\"network\":\"4G LTE\",\"color\":\"Grey\"}', '3230.00', '2300.00', '10', 'Shelf A1', '1', 'available', '6', '2025-10-27 22:40:47', '2025-10-31 16:25:28', NULL, 'A12', '', NULL, '', '', 'DailyCoins'),
('37', 'PID-037', '1', 'Apple Watch Series 9', '6', '45', NULL, NULL, '399.99', '250.00', '4', NULL, '1', 'available', '1', '2025-10-29 19:29:21', '2025-10-31 11:19:30', 'WEA-APP-001', NULL, NULL, NULL, NULL, NULL, NULL),
('38', 'PID-038', '1', 'Samsung Galaxy Watch 6', '6', '46', NULL, NULL, '299.99', '200.00', '5', NULL, '1', 'available', '1', '2025-10-29 19:29:21', '2025-10-31 11:19:30', 'WEA-SAM-001', NULL, NULL, NULL, NULL, NULL, NULL),
('39', 'PID-039', '1', 'Fitbit Versa 4', '6', '47', NULL, NULL, '199.99', '120.00', '8', NULL, '1', 'available', '1', '2025-10-29 19:29:21', '2025-10-31 11:19:30', 'WEA-FIT-001', NULL, NULL, NULL, NULL, NULL, NULL),
('40', 'PID-040', '1', 'Garmin Forerunner 255', '6', '48', NULL, NULL, '349.99', '220.00', '2', NULL, '1', 'available', '1', '2025-10-29 19:29:21', '2025-10-31 11:19:30', 'WEA-GAR-001', NULL, NULL, NULL, NULL, NULL, NULL),
('41', 'PID-041', '1', 'iPhone 15 Pro', '1', NULL, NULL, NULL, '999.99', '600.00', '0', NULL, '1', 'available', '1', '2025-10-29 18:41:09', '2025-10-31 11:19:30', 'ELC-TEC-001', NULL, NULL, NULL, NULL, NULL, NULL),
('42', 'PID-042', '1', 'MacBook Air M2', '1', NULL, NULL, NULL, '1299.99', '800.00', '0', NULL, '1', 'available', '1', '2025-10-29 18:41:09', '2025-10-31 11:19:30', 'ELC-TEC-002', NULL, NULL, NULL, NULL, NULL, NULL),
('43', 'PID-043', '1', 'Samsung Galaxy S24', '1', NULL, NULL, NULL, '899.99', '550.00', '0', NULL, '1', 'available', '1', '2025-10-29 18:41:09', '2025-10-31 11:19:30', 'ELC-TEC-003', NULL, NULL, NULL, NULL, NULL, NULL),
('94', 'GALAXYA14969', '1', 'Galaxy A16', '1', '25', NULL, NULL, '3454.00', '3245.00', '1', 'Shelf A1', '0', 'available', '6', '2025-10-31 21:35:10', '2025-11-01 07:28:56', 'SKU-20251031-1382', 'A12', '', NULL, '', '', 'DailyCoins'),
('95', 'GALAXYA17846', '1', 'Galaxy A19', '1', '25', NULL, NULL, '3454.00', '3245.00', '0', 'Shelf A1', '1', 'available', '6', '2025-10-31 22:29:12', '2025-10-31 23:50:00', 'SKU-20251031-2307', 'A12', '', NULL, '', '', 'DailyCoins'),
('99', NULL, '1', 'Samsung m22 nokia', '1', '2', NULL, NULL, '3500.00', '3500.00', '1', NULL, '0', 'available', '6', '2025-10-31 23:52:21', '2025-10-31 23:52:21', NULL, 'm22 nokia', NULL, NULL, NULL, NULL, NULL),
('100', 'GALAXYS27879', '1', 'Galaxy s22 Ultra', '1', '25', NULL, '{\"model\":\"SM-BCTFD\",\"storage\":\"128GB\",\"ram\":\"6GB\",\"network\":\"5G\",\"color\":\"Black\"}', '2500.00', '2300.00', '0', 'Shelf AC', '1', 'available', '6', '2025-11-01 06:26:25', '2025-11-01 06:28:48', NULL, 's22', '', NULL, '', '', 'DailyCoins'),
('101', NULL, '1', 'Samsung S10+', '1', '2', NULL, NULL, '1500.00', '1500.00', '0', NULL, '0', 'out_of_stock', '6', '2025-11-01 06:29:18', '2025-11-01 07:28:28', NULL, 'S10+', NULL, NULL, NULL, NULL, NULL);


-- Table: products_old_backup
DROP TABLE IF EXISTS `products_old_backup`;
CREATE TABLE `products_old_backup` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0.00,
  `cost` decimal(12,2) DEFAULT 0.00,
  `qty` int(11) DEFAULT 0,
  `available_for_swap` tinyint(1) DEFAULT 0,
  `status` enum('AVAILABLE','SOLD','OUT_OF_STOCK') DEFAULT 'AVAILABLE',
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_company_category` (`company_id`,`category`),
  KEY `idx_company_status` (`company_id`,`status`),
  KEY `idx_sku` (`sku`),
  KEY `created_by_user_id` (`created_by_user_id`),
  CONSTRAINT `products_old_backup_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_old_backup_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products_old_backup` VALUES
('8', '1', 'ELC-TEC-001', 'iPhone 15 Pro', 'Electronics', 'TechCorp', '999.99', '600.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('9', '1', 'ELC-TEC-002', 'MacBook Air M2', 'Electronics', 'TechCorp', '1299.99', '800.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('10', '1', 'ELC-TEC-003', 'Samsung Galaxy S24', 'Electronics', 'TechCorp', '899.99', '550.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('13', '1', 'ELC-TEC-004', 'Sony WH-1000XM4', 'Electronics', 'TechCorp', '349.99', '200.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('14', '1', 'ELC-TEC-005', 'iPad Pro 12.9', 'Electronics', 'TechCorp', '1099.99', '700.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('18', '1', 'ELC-TEC-006', 'Dell XPS 13', 'Electronics', 'TechCorp', '1199.99', '750.00', '3', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('19', '1', 'ELC-TEC-007', 'Apple Watch Series 9', 'Electronics', 'TechCorp', '399.99', '250.00', '4', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('28', '1', 'ELC-TEC-008', 'HP Pavilion Laptop', 'Electronics', 'TechCorp', '599.99', '350.00', '15', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('29', '1', 'ELC-TEC-009', 'JBL Bluetooth Speaker', 'Electronics', 'TechCorp', '79.99', '45.00', '22', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('30', '1', 'ELC-TEC-010', 'Canon EOS Camera', 'Electronics', 'TechCorp', '899.99', '550.00', '12', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('43', '1', 'ELC-TEC-011', 'USB-C Cable', 'Electronics', 'TechCorp', '19.99', '8.00', '45', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('44', '1', 'ELC-TEC-012', 'Wireless Mouse', 'Electronics', 'TechCorp', '29.99', '15.00', '60', '1', 'AVAILABLE', '1', '2025-10-29 18:41:09', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('58', '1', 'ELC-TEC-013', 'AA Batteries Pack', 'Electronics', 'TechCorp', '12.99', '6.00', '120', '1', 'AVAILABLE', '1', '2025-10-29 18:41:10', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('59', '1', 'ELC-TEC-014', 'Phone Case Clear', 'Electronics', 'TechCorp', '9.99', '4.00', '150', '1', 'AVAILABLE', '1', '2025-10-29 18:41:10', '2025-10-29 19:12:50', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('68', '1', 'MOB-APP-001', 'iPhone 15 Pro', 'Mobile', 'Apple', '999.99', '600.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Mobile'),
('69', '1', 'MOB-SAM-001', 'Samsung Galaxy S24', 'Mobile', 'Samsung', '899.99', '550.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Mobile'),
('70', '1', 'MOB-GOO-001', 'Google Pixel 8', 'Mobile', 'Google', '699.99', '450.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Mobile'),
('71', '1', 'MOB-ONE-001', 'OnePlus 12', 'Mobile', 'OnePlus', '799.99', '500.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Mobile'),
('72', '1', 'LAP-APP-001', 'MacBook Air M2', 'Computers', 'Apple', '1299.99', '800.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Computers'),
('73', '1', 'LAP-DEL-001', 'Dell XPS 13', 'Computers', 'Dell', '1199.99', '750.00', '3', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Computers'),
('74', '1', 'LAP-HP-001', 'HP Spectre x360', 'Computers', 'HP', '1099.99', '700.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Computers'),
('75', '1', 'LAP-LEN-001', 'Lenovo ThinkPad X1', 'Computers', 'Lenovo', '1399.99', '900.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Computers'),
('76', '1', 'TAB-APP-001', 'iPad Pro 12.9', 'Electronics', 'Apple', '1099.99', '700.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('77', '1', 'TAB-SAM-001', 'Samsung Galaxy Tab S9', 'Electronics', 'Samsung', '799.99', '500.00', '4', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('78', '1', 'TAB-MIC-001', 'Microsoft Surface Pro 9', 'Electronics', 'Microsoft', '999.99', '650.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Electronics'),
('79', '1', 'AUD-SON-001', 'Sony WH-1000XM4', 'Audio', 'Sony', '349.99', '200.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Audio'),
('80', '1', 'AUD-APP-001', 'AirPods Pro 2', 'Audio', 'Apple', '249.99', '150.00', '5', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Audio'),
('81', '1', 'AUD-BOS-001', 'Bose QuietComfort 45', 'Audio', 'Bose', '329.99', '200.00', '3', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Audio'),
('82', '1', 'AUD-JBL-001', 'JBL Charge 5', 'Audio', 'JBL', '149.99', '80.00', '8', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Audio'),
('83', '1', 'GAM-SON-001', 'PlayStation 5', 'Gaming', 'Sony', '499.99', '350.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gaming'),
('84', '1', 'GAM-MIC-001', 'Xbox Series X', 'Gaming', 'Microsoft', '499.99', '350.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gaming'),
('85', '1', 'GAM-NIN-001', 'Nintendo Switch OLED', 'Gaming', 'Nintendo', '349.99', '250.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gaming'),
('86', '1', 'GAM-VAL-001', 'Steam Deck', 'Gaming', 'Valve', '399.99', '300.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gaming'),
('87', '1', 'SMT-AMA-001', 'Amazon Echo Dot', 'Smart Home', 'Amazon', '49.99', '25.00', '12', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Smart+Home'),
('88', '1', 'SMT-GOO-001', 'Google Nest Hub', 'Smart Home', 'Google', '89.99', '50.00', '6', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Smart+Home'),
('89', '1', 'SMT-APP-001', 'Apple HomePod mini', 'Smart Home', 'Apple', '99.99', '60.00', '4', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Smart+Home'),
('90', '1', 'SMT-PHI-001', 'Philips Hue Starter Kit', 'Smart Home', 'Philips', '199.99', '120.00', '3', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Smart+Home'),
('91', '1', 'WEA-APP-001', 'Apple Watch Series 9', 'Wearables', 'Apple', '399.99', '250.00', '4', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Wearables'),
('92', '1', 'WEA-SAM-001', 'Samsung Galaxy Watch 6', 'Wearables', 'Samsung', '299.99', '200.00', '5', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Wearables'),
('93', '1', 'WEA-FIT-001', 'Fitbit Versa 4', 'Wearables', 'Fitbit', '199.99', '120.00', '8', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Wearables'),
('94', '1', 'WEA-GAR-001', 'Garmin Forerunner 255', 'Wearables', 'Garmin', '349.99', '220.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Wearables'),
('95', '1', 'ACC-APP-001', 'iPhone 15 Pro Case', 'Accessories', 'Apple', '49.99', '25.00', '15', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('96', '1', 'ACC-SAM-001', 'Samsung Galaxy S24 Case', 'Accessories', 'Samsung', '39.99', '20.00', '20', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('97', '1', 'ACC-APP-002', 'MacBook Air Sleeve', 'Accessories', 'Apple', '29.99', '15.00', '25', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('98', '1', 'ACC-UNI-001', 'USB-C Hub', 'Accessories', 'Universal', '79.99', '40.00', '10', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('99', '1', 'ACC-UNI-002', 'Wireless Charger', 'Accessories', 'Universal', '29.99', '15.00', '30', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('100', '1', 'ACC-UNI-003', 'Bluetooth Mouse', 'Accessories', 'Universal', '39.99', '20.00', '18', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('101', '1', 'ACC-UNI-004', 'Mechanical Keyboard', 'Accessories', 'Universal', '129.99', '70.00', '6', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('102', '1', 'ACC-UNI-005', '4K Monitor', 'Accessories', 'Universal', '299.99', '180.00', '4', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('103', '1', 'SOF-ADO-001', 'Adobe Creative Suite', 'Software', 'Adobe', '599.99', '300.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Software'),
('104', '1', 'SOF-MIC-001', 'Microsoft Office 365', 'Software', 'Microsoft', '99.99', '50.00', '10', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Software'),
('105', '1', 'SOF-APP-001', 'Final Cut Pro', 'Software', 'Apple', '299.99', '150.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Software'),
('106', '1', 'SOF-APP-002', 'Logic Pro', 'Software', 'Apple', '199.99', '100.00', '3', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Software'),
('107', '1', 'GAD-DJI-001', 'DJI Mini 3 Drone', 'Gadgets', 'DJI', '499.99', '300.00', '1', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gadgets'),
('108', '1', 'GAD-GOP-001', 'GoPro Hero 12', 'Gadgets', 'GoPro', '399.99', '250.00', '2', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gadgets'),
('109', '1', 'GAD-MET-001', 'Oculus Quest 3', 'Gadgets', 'Meta', '499.99', '350.00', '0', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gadgets'),
('110', '1', 'GAD-RAS-001', 'Raspberry Pi 4', 'Gadgets', 'Raspberry Pi', '75.99', '40.00', '8', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Gadgets'),
('111', '1', 'ACC-UNI-006', 'USB-C Cable', 'Accessories', 'Universal', '19.99', '8.00', '45', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('112', '1', 'ACC-APP-003', 'Lightning Cable', 'Accessories', 'Apple', '24.99', '12.00', '50', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('113', '1', 'ACC-UNI-007', 'MicroSD Card 128GB', 'Accessories', 'Universal', '29.99', '15.00', '40', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('114', '1', 'ACC-UNI-008', 'Power Bank 10000mAh', 'Accessories', 'Universal', '39.99', '20.00', '35', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('115', '1', 'ACC-UNI-009', 'Screen Protector Pack', 'Accessories', 'Universal', '14.99', '7.00', '60', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('116', '1', 'ACC-UNI-010', 'Laptop Stand', 'Accessories', 'Universal', '49.99', '25.00', '25', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('117', '1', 'ACC-UNI-011', 'Webcam 4K', 'Accessories', 'Universal', '99.99', '50.00', '15', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories'),
('118', '1', 'ACC-UNI-012', 'Gaming Headset', 'Accessories', 'Universal', '79.99', '40.00', '20', '1', 'AVAILABLE', '1', '2025-10-29 19:29:21', '2025-10-29 19:29:21', 'https://via.placeholder.com/300x300/4F46E5/FFFFFF?text=Accessories');


-- Table: repair_accessories
DROP TABLE IF EXISTS `repair_accessories`;
CREATE TABLE `repair_accessories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_repair` (`repair_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `repair_accessories_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `repairs_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: repairs
DROP TABLE IF EXISTS `repairs`;
CREATE TABLE `repairs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `unique_id` varchar(50) NOT NULL,
  `tracking_code` varchar(20) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `phone_description` varchar(255) NOT NULL,
  `imei` varchar(20) DEFAULT NULL,
  `issue_description` text NOT NULL,
  `repair_cost` decimal(10,2) DEFAULT 0.00,
  `parts_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `repair_status` enum('PENDING','IN_PROGRESS','COMPLETED','DELIVERED','CANCELLED') DEFAULT 'PENDING',
  `payment_status` enum('UNPAID','PARTIAL','PAID') DEFAULT 'UNPAID',
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `tracking_code` (`tracking_code`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_tracking` (`tracking_code`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`repair_status`),
  KEY `idx_payment` (`payment_status`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `repairs_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repairs_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `repairs_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: repairs_new
DROP TABLE IF EXISTS `repairs_new`;
CREATE TABLE `repairs_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `technician_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_contact` varchar(20) NOT NULL,
  `issue_description` text NOT NULL,
  `repair_cost` decimal(10,2) DEFAULT 0.00,
  `parts_cost` decimal(10,2) DEFAULT 0.00,
  `accessory_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','in_progress','completed','delivered','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `tracking_code` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_code` (`tracking_code`),
  KEY `idx_company` (`company_id`),
  KEY `idx_technician` (`technician_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_tracking` (`tracking_code`),
  CONSTRAINT `repairs_new_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repairs_new_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: restock_logs
DROP TABLE IF EXISTS `restock_logs`;
CREATE TABLE `restock_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `quantity_added` int(11) NOT NULL DEFAULT 0,
  `new_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `new_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `restock_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restock_logs_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `restock_logs` VALUES
('1', '4', '1', '34', '8000.00', '10000.00', '', '2025-10-28 04:43:15', '2025-10-28 04:43:15'),
('2', '4', '1', '1', '8000.00', '5500.00', '', '2025-10-31 10:32:16', '2025-10-31 10:32:16'),
('3', '95', '1', '1', '3245.00', '3454.00', '', '2025-10-31 23:48:48', '2025-10-31 23:48:48');


-- Table: subcategories
DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subcategory_category` (`name`,`category_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_name` (`name`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subcategories` VALUES
('1', '2', 'Charger', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('2', '2', 'Battery', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('3', '2', 'Earbud', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('4', '2', 'Screen Protector', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('5', '2', 'Case', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('6', '2', 'Power Bank', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('7', '2', 'Cable', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('8', '2', 'Adapter', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('9', '2', 'Headphone', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('10', '2', 'Bluetooth Speaker', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('11', '2', 'Car Mount', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('12', '2', 'Wireless Charger', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('13', '5', 'Display', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('14', '5', 'Motherboard', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('15', '5', 'Camera Module', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('16', '5', 'Charging Port', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('17', '5', 'Battery', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('18', '5', 'Speaker', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('19', '5', 'Microphone', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('20', '5', 'Vibration Motor', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('21', '5', 'Flex Cable', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('22', '5', 'Back Cover', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('23', '5', 'Frame', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('24', '5', 'Button', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('25', '6', 'Smartwatch Strap', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('26', '6', 'Charging Dock', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('27', '6', 'Screen Protector', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('28', '6', 'Case', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('29', '6', 'Band', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15'),
('30', '6', 'Charger', NULL, '1', '2025-10-24 16:46:15', '2025-10-24 16:46:15');


-- Table: swap_profit_links
DROP TABLE IF EXISTS `swap_profit_links`;
CREATE TABLE `swap_profit_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `swap_id` bigint(20) unsigned NOT NULL,
  `company_item_sale_id` bigint(20) unsigned DEFAULT NULL,
  `customer_item_sale_id` bigint(20) unsigned DEFAULT NULL,
  `company_product_cost` decimal(10,2) NOT NULL,
  `customer_phone_value` decimal(10,2) NOT NULL,
  `amount_added_by_customer` decimal(10,2) DEFAULT 0.00,
  `profit_estimate` decimal(10,2) NOT NULL,
  `final_profit` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','finalized') DEFAULT 'pending',
  `finalized_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_swap` (`swap_id`),
  KEY `idx_status` (`status`),
  KEY `idx_company_item_sale` (`company_item_sale_id`),
  KEY `idx_customer_item_sale` (`customer_item_sale_id`),
  CONSTRAINT `fk_company_item_sale` FOREIGN KEY (`company_item_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_item_sale` FOREIGN KEY (`customer_item_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_swap_profit_links_swap` FOREIGN KEY (`swap_id`) REFERENCES `swaps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `swap_profit_links` VALUES
('6', '11', NULL, NULL, '2417.80', '3500.00', '550.00', '1036.20', NULL, 'pending', NULL, '2025-10-31 23:50:00', '2025-10-31 23:50:00'),
('7', '12', NULL, NULL, '1750.00', '1500.00', '1250.00', '750.00', NULL, 'pending', NULL, '2025-11-01 06:28:48', '2025-11-01 06:28:48');


-- Table: swapped_items
DROP TABLE IF EXISTS `swapped_items`;
CREATE TABLE `swapped_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `swap_id` bigint(20) unsigned NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `imei` varchar(20) DEFAULT NULL,
  `condition` varchar(20) NOT NULL,
  `estimated_value` decimal(10,2) NOT NULL,
  `resell_price` decimal(10,2) NOT NULL,
  `status` enum('in_stock','sold') DEFAULT 'in_stock',
  `resold_on` datetime DEFAULT NULL,
  `inventory_product_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_swap` (`swap_id`),
  KEY `idx_status` (`status`),
  KEY `idx_brand_model` (`brand`,`model`),
  KEY `idx_imei` (`imei`),
  CONSTRAINT `fk_swapped_items_swap` FOREIGN KEY (`swap_id`) REFERENCES `swaps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `swapped_items` VALUES
('7', '11', 'Samsung', 'm22 nokia', NULL, 'used', '3500.00', '3500.00', 'in_stock', NULL, '99', '{\"storage\":\"256GB\",\"ram\":\"6GB\",\"network\":\"5G\"}', '2025-10-31 23:50:00', '2025-10-31 23:52:21'),
('8', '12', 'Samsung', 'S10+', NULL, 'new', '1500.00', '1500.00', 'sold', '2025-11-01 07:28:28', '101', '{\"storage\":\"128GB\",\"ram\":\"6GB\",\"network\":\"4G/5G\",\"color\":\"Green\"}', '2025-11-01 06:28:48', '2025-11-01 07:28:28');


-- Table: swaps
DROP TABLE IF EXISTS `swaps`;
CREATE TABLE `swaps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `unique_id` varchar(50) NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `new_phone_id` bigint(20) unsigned NOT NULL,
  `given_phone_description` text DEFAULT NULL,
  `given_phone_value` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL,
  `payment_method` enum('CASH','MOBILE_MONEY','CARD','BANK_TRANSFER') DEFAULT 'CASH',
  `swap_status` enum('PENDING','COMPLETED','CANCELLED') DEFAULT 'COMPLETED',
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `new_phone_id` (`new_phone_id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`swap_status`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `swaps_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `swaps_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `swaps_ibfk_3` FOREIGN KEY (`new_phone_id`) REFERENCES `phones` (`id`),
  CONSTRAINT `swaps_ibfk_4` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `swaps` VALUES
('11', '1', 'SWP-20259890', '2', '2', 'Samsung m22 nokia', '3500.00', '3454.00', 'CASH', 'COMPLETED', '15', NULL, '2025-10-31 23:50:00', '2025-10-31 23:50:00'),
('12', '1', 'SWP-20250990', '2', '3', 'Samsung S10+', '1500.00', '2500.00', 'CASH', 'COMPLETED', '15', NULL, '2025-11-01 06:28:48', '2025-11-01 06:28:48');


-- Table: system_settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` VALUES
('1', 'cloudinary_cloud_name', 'dlqdlxine', 'Cloudinary cloud name for image storage', '2025-10-28 16:27:31', '2025-11-02 08:12:42'),
('2', 'cloudinary_api_key', '994431582764464', 'Cloudinary API key', '2025-10-28 16:27:31', '2025-11-02 08:12:42'),
('3', 'cloudinary_api_secret', '4UYp-ibVybtVkHVLQnwAKoGwTCk', 'Cloudinary API secret', '2025-10-28 16:27:31', '2025-11-02 08:12:42'),
('4', 'sms_api_key', 'UUFqaGtpT0xKSVN3ZFpmU0phdVc', 'Arkasel SMS API key', '2025-10-28 16:27:31', '2025-10-31 15:56:35'),
('5', 'sms_sender_id', 'SellApp', 'SMS sender ID', '2025-10-28 16:27:31', '2025-10-31 15:56:35'),
('6', 'default_image_quality', 'auto', 'Default image quality for uploads', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('7', 'sms_purchase_enabled', '1', 'Enable SMS notifications for purchases', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('8', 'sms_repair_enabled', '1', 'Enable SMS notifications for repairs', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('9', 'sms_swap_enabled', '1', 'Enable SMS notifications for swaps', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('10', 'app_name', 'SellApp', 'Application name', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('11', 'app_version', '1.0.0', 'Application version', '2025-10-28 16:27:31', '2025-10-28 16:27:31'),
('21', 'paystack_secret_key', 'sk_live_63e189f3b4245e9e8a068d69f34e32b4b15581eb', NULL, '2025-11-02 07:46:18', '2025-11-02 08:01:33'),
('22', 'paystack_public_key', 'pk_live_725dfd82fe06abf116bdf7e3661e993ecee76cf7', NULL, '2025-11-02 07:46:18', '2025-11-02 08:01:33'),
('23', 'paystack_mode', 'live', NULL, '2025-11-02 07:46:18', '2025-11-02 08:01:33');


-- Table: transaction_items
DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned NOT NULL,
  `item_type` enum('phone','accessory','repair_service') NOT NULL,
  `item_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_item_type` (`item_type`),
  CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: transactions
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(40) NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('SALE','REPAIR','SWAP') NOT NULL,
  `description` text DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `balance_paid` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash','card','bank_transfer','other') DEFAULT 'cash',
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_unique_id` (`unique_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `unique_id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('system_admin','manager','salesperson','technician') DEFAULT 'salesperson',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_unique_id` (`unique_id`),
  KEY `idx_role` (`role`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES
('1', NULL, 'USRADMIN001', 'admin', 'admin@sellapp.com', '+233000000000', 'System Administrator', '$2y$10$ud5QWkPPUgSJvDjwHcWLXuq0SpDCr3QSfs4b/aYM2CN0krj1JIIUG', 'system_admin', '1', '2025-10-23 17:59:44', '2025-10-23 18:06:12'),
('6', '1', 'USR68FB744BEBF39', 'johnkay', 'johnkay@gmail.com', '0257940791', 'johnkay', '$2y$10$wa9rxs/yT8phr2JE6MCIYOb0Ew2OuCYvnbvfOFVIsr3OcJqUu4W8O', 'manager', '1', '2025-10-24 12:42:52', '2025-10-24 12:42:52'),
('10', '1', 'USRMGR001', 'manager1', 'manager@techmobile.gh', '+233244123456', 'John Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('11', '2', 'USRMGR002', 'manager2', 'manager@phonehub.com', '+233501234567', 'Jane Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('12', '2', 'USRSALES001', 'sales1', 'sales1@phonehub.com', '+233241111111', 'Michael Sales', '$2y$10$A9YY4pxAVf9tPP/SkT6CbuN1ENopF17.lC4TdWsKdubq6ec7aBh9.', 'salesperson', '1', '2025-10-24 12:58:38', '2025-10-25 14:49:31'),
('13', '2', 'USRTECH001', 'tech1', 'tech1@phonehub.com', '+233242222222', 'Sarah Tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('14', '2', 'USRSALES002', 'sales2', 'sales2@phonehub.com', '+233243333333', 'David Sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'salesperson', '0', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('15', '1', 'USR68FB783309847', 'corny', 'corny@sellapp.com', '0248069639', 'corny', '$2y$10$BhpCfJVUqmICdyKrfgHNPO55Nv6l.G310ikRYzRJlsj0Rk2xNgrfS', 'salesperson', '1', '2025-10-24 12:59:31', '2025-10-26 05:24:46');

