-- WiFight Database Schema
-- Version: 1.0.0
-- Database: wifight_db

-- Drop tables if they exist
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `vouchers`;
DROP TABLE IF EXISTS `plans`;
DROP TABLE IF EXISTS `controllers`;
DROP TABLE IF EXISTS `portal_settings`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `users`;

-- Create users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('admin','manager','staff','customer') DEFAULT 'customer',
  `phone` varchar(20) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_location` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create locations table
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `currency` varchar(3) DEFAULT 'USD',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create controllers table
CREATE TABLE `controllers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `port` int(11) DEFAULT 8043,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `site_id` varchar(100) DEFAULT 'default',
  `location_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','error') DEFAULT 'active',
  `version` varchar(50) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_location` (`location_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create plans table
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_hours` int(11) DEFAULT NULL,
  `data_limit_mb` int(11) DEFAULT NULL,
  `bandwidth_up` int(11) DEFAULT NULL COMMENT 'Kbps',
  `bandwidth_down` int(11) DEFAULT NULL COMMENT 'Kbps',
  `validity_days` int(11) DEFAULT 30,
  `status` enum('active','inactive') DEFAULT 'active',
  `location_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_location` (`location_id`),
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create vouchers table
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `batch_id` varchar(100) DEFAULT NULL,
  `status` enum('unused','used','expired') DEFAULT 'unused',
  `price` decimal(10,2) DEFAULT 0.00,
  `duration_hours` int(11) DEFAULT NULL,
  `data_limit_mb` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  `used_by` varchar(100) DEFAULT NULL COMMENT 'MAC address',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_batch` (`batch_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sessions table
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `controller_id` int(11) NOT NULL,
  `mac_address` varchar(17) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `end_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `data_used_mb` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','terminated','expired') DEFAULT 'active',
  `device_info` text,
  PRIMARY KEY (`id`),
  KEY `idx_controller` (`controller_id`),
  KEY `idx_mac` (`mac_address`),
  KEY `idx_status` (`status`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_voucher` (`voucher_id`),
  FOREIGN KEY (`controller_id`) REFERENCES `controllers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payments table
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `voucher_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` enum('stripe','paypal','mpesa','cash','bank_transfer') NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `gateway_response` text,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_plan` (`plan_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create portal_settings table
CREATE TABLE `portal_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) DEFAULT NULL,
  `portal_title` varchar(255) DEFAULT 'WiFight Portal',
  `welcome_message` text,
  `logo_url` varchar(500) DEFAULT NULL,
  `background_url` varchar(500) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#4F46E5',
  `secondary_color` varchar(7) DEFAULT '#10B981',
  `enable_social_login` tinyint(1) DEFAULT 1,
  `enable_vouchers` tinyint(1) DEFAULT 1,
  `enable_payments` tinyint(1) DEFAULT 1,
  `terms_url` varchar(500) DEFAULT NULL,
  `privacy_url` varchar(500) DEFAULT NULL,
  `support_email` varchar(255) DEFAULT NULL,
  `support_phone` varchar(20) DEFAULT NULL,
  `facebook_app_id` varchar(100) DEFAULT NULL,
  `google_client_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_location` (`location_id`),
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default location
INSERT INTO `locations` (`name`, `address`, `city`, `country`, `timezone`, `currency`) VALUES
('Main Location', '123 Main Street', 'Kampala', 'Uganda', 'Africa/Kampala', 'UGX');

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`email`, `password`, `full_name`, `role`, `location_id`, `status`) VALUES
('admin@wifight.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 1, 'active');

-- Insert sample plans
INSERT INTO `plans` (`name`, `description`, `price`, `duration_hours`, `data_limit_mb`, `bandwidth_up`, `bandwidth_down`, `location_id`) VALUES
('1 Hour Basic', 'Perfect for quick browsing', 1.00, 1, 500, 2048, 4096, 1),
('6 Hours Standard', 'Great for half-day access', 5.00, 6, 2048, 4096, 8192, 1),
('24 Hours Premium', 'Full day unlimited access', 10.00, 24, NULL, 8192, 16384, 1),
('7 Days Extended', 'One week of premium access', 30.00, 168, NULL, 8192, 16384, 1),
('30 Days Ultimate', 'Monthly unlimited access', 100.00, 720, NULL, 10240, 20480, 1);

-- Insert default portal settings
INSERT INTO `portal_settings` (`location_id`, `portal_title`, `welcome_message`, `primary_color`, `secondary_color`) VALUES
(1, 'WiFight Portal', 'Welcome to our WiFi network. Choose your preferred access method to get connected.', '#4F46E5', '#10B981');

-- Create indexes for performance
CREATE INDEX idx_sessions_dates ON sessions(start_time, end_time);
CREATE INDEX idx_vouchers_expires ON vouchers(expires_at);
CREATE INDEX idx_payments_created ON payments(created_at);
CREATE INDEX idx_users_created ON users(created_at);