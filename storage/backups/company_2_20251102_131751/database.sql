-- Company Backup SQL
-- Company ID: 2
-- Created: 2025-11-02 13:17:51


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
('11', '2', 'USRMGR002', 'manager2', 'manager@phonehub.com', '+233501234567', 'Jane Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('12', '2', 'USRSALES001', 'sales1', 'sales1@phonehub.com', '+233241111111', 'Michael Sales', '$2y$10$A9YY4pxAVf9tPP/SkT6CbuN1ENopF17.lC4TdWsKdubq6ec7aBh9.', 'salesperson', '1', '2025-10-24 12:58:38', '2025-10-25 14:49:31'),
('13', '2', 'USRTECH001', 'tech1', 'tech1@phonehub.com', '+233242222222', 'Sarah Tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', '1', '2025-10-24 12:58:38', '2025-10-24 12:58:38'),
('14', '2', 'USRSALES002', 'sales2', 'sales2@phonehub.com', '+233243333333', 'David Sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'salesperson', '0', '2025-10-24 12:58:38', '2025-10-24 12:58:38');


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
('2', '2', '5', '0', '5', 'active', 'SellApp', '0', '2025-11-02 07:07:19', '2025-11-02 07:11:00');


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

