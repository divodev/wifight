-- WiFight ISP System - Initial Seed Data
-- Version: 1.0
-- Description: Initial data for system setup including admin user, sample plans, and default settings

USE wifight_isp;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- SEED DATA: USERS
-- =============================================================================
-- Admin User
-- Email: admin@wifight.local
-- Password: admin123 (bcrypt hashed)
-- Note: MUST BE CHANGED IN PRODUCTION
INSERT INTO users (username, email, password_hash, full_name, phone, role, status, balance, two_factor_enabled) VALUES
('admin', 'admin@wifight.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1234567890', 'admin', 'active', 0.00, FALSE),
('reseller1', 'reseller@wifight.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo Reseller', '+1234567891', 'reseller', 'active', 1000.00, FALSE),
('testuser', 'user@wifight.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', '+1234567892', 'user', 'active', 50.00, FALSE);

-- =============================================================================
-- SEED DATA: PLANS
-- =============================================================================
-- Sample Internet Plans
INSERT INTO plans (name, description, bandwidth_up, bandwidth_down, duration_days, price, currency, status, max_devices, data_limit_gb, is_unlimited) VALUES
-- Basic Plans
('Basic 1Mbps', 'Entry-level internet plan with 1Mbps speed', 1024, 1024, 30, 9.99, 'USD', 'active', 2, NULL, TRUE),
('Standard 5Mbps', 'Standard internet plan with 5Mbps speed', 5120, 5120, 30, 19.99, 'USD', 'active', 3, NULL, TRUE),
('Premium 10Mbps', 'Premium internet plan with 10Mbps speed', 10240, 10240, 30, 34.99, 'USD', 'active', 5, NULL, TRUE),
('Ultimate 20Mbps', 'Ultimate internet plan with 20Mbps speed', 20480, 20480, 30, 59.99, 'USD', 'active', 10, NULL, TRUE),

-- Data-Limited Plans
('Student 5Mbps', 'Student plan with 50GB data cap', 5120, 5120, 30, 14.99, 'USD', 'active', 2, 50, FALSE),
('Mobile 3Mbps', 'Mobile-optimized plan with 20GB data', 3072, 3072, 7, 5.99, 'USD', 'active', 1, 20, FALSE),

-- Business Plans
('Business 50Mbps', 'Business-grade internet with priority support', 51200, 51200, 30, 149.99, 'USD', 'active', 20, NULL, TRUE),
('Enterprise 100Mbps', 'Enterprise solution with dedicated support', 102400, 102400, 30, 299.99, 'USD', 'active', 50, NULL, TRUE),

-- Daily/Hourly Plans
('Daily Pass', '24-hour unlimited internet access', 10240, 10240, 1, 2.99, 'USD', 'active', 1, NULL, TRUE),
('Weekend Special', '3-day weekend package', 10240, 10240, 3, 7.99, 'USD', 'active', 3, NULL, TRUE);

-- =============================================================================
-- SEED DATA: SYSTEM SETTINGS
-- =============================================================================
-- Core System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
-- Application Settings
('app_name', 'WiFight ISP', 'string', 'Application name displayed throughout the system', TRUE),
('app_version', '1.0.0', 'string', 'Current application version', TRUE),
('app_timezone', 'UTC', 'string', 'Default system timezone', FALSE),
('app_locale', 'en_US', 'string', 'Default system locale', FALSE),
('app_maintenance_mode', 'false', 'boolean', 'Enable/disable maintenance mode', FALSE),

-- Authentication Settings
('jwt_expiration', '3600', 'integer', 'JWT access token expiration in seconds (1 hour)', FALSE),
('jwt_refresh_expiration', '604800', 'integer', 'JWT refresh token expiration in seconds (7 days)', FALSE),
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout', FALSE),
('lockout_duration', '900', 'integer', 'Account lockout duration in seconds (15 minutes)', FALSE),
('password_min_length', '8', 'integer', 'Minimum password length requirement', FALSE),
('require_2fa_admin', 'false', 'boolean', 'Require 2FA for admin accounts', FALSE),

-- Session Settings
('session_timeout', '86400', 'integer', 'Default session timeout in seconds (24 hours)', FALSE),
('max_concurrent_sessions', '3', 'integer', 'Maximum concurrent sessions per user', FALSE),
('session_cleanup_interval', '300', 'integer', 'Session cleanup interval in seconds (5 minutes)', FALSE),

-- Payment Settings
('currency', 'USD', 'string', 'Default currency', TRUE),
('payment_methods', 'stripe,paypal,manual', 'string', 'Enabled payment methods (comma-separated)', TRUE),
('tax_rate', '0.00', 'decimal', 'Default tax rate percentage', TRUE),
('allow_negative_balance', 'false', 'boolean', 'Allow users to have negative balance', FALSE),
('low_balance_threshold', '5.00', 'decimal', 'Threshold for low balance notifications', FALSE),

-- Email Settings
('smtp_enabled', 'false', 'boolean', 'Enable SMTP email sending', FALSE),
('smtp_host', 'localhost', 'string', 'SMTP server host', FALSE),
('smtp_port', '587', 'integer', 'SMTP server port', FALSE),
('smtp_encryption', 'tls', 'string', 'SMTP encryption method (tls/ssl)', FALSE),
('email_from_address', 'noreply@wifight.local', 'string', 'Default from email address', FALSE),
('email_from_name', 'WiFight ISP', 'string', 'Default from name', FALSE),

-- Notification Settings
('notifications_enabled', 'true', 'boolean', 'Enable system notifications', TRUE),
('notify_session_start', 'true', 'boolean', 'Notify users when session starts', TRUE),
('notify_session_end', 'true', 'boolean', 'Notify users when session ends', TRUE),
('notify_payment_received', 'true', 'boolean', 'Notify users of successful payments', TRUE),
('notify_low_balance', 'true', 'boolean', 'Notify users of low balance', TRUE),
('notify_plan_expiry', 'true', 'boolean', 'Notify users before plan expiration', TRUE),

-- Voucher Settings
('voucher_code_length', '12', 'integer', 'Length of generated voucher codes', FALSE),
('voucher_code_prefix', 'WF-', 'string', 'Prefix for voucher codes', TRUE),
('voucher_default_validity', '365', 'integer', 'Default voucher validity in days', FALSE),

-- RADIUS Settings
('radius_enabled', 'false', 'boolean', 'Enable RADIUS authentication', FALSE),
('radius_server', '127.0.0.1', 'string', 'RADIUS server IP address', FALSE),
('radius_port', '1812', 'integer', 'RADIUS server port', FALSE),
('radius_accounting_port', '1813', 'integer', 'RADIUS accounting port', FALSE),
('radius_secret', 'testing123', 'string', 'RADIUS shared secret', FALSE),

-- Controller Settings
('controller_sync_interval', '60', 'integer', 'Controller sync interval in seconds', FALSE),
('controller_timeout', '30', 'integer', 'Controller connection timeout in seconds', FALSE),
('controller_max_retries', '3', 'integer', 'Maximum connection retry attempts', FALSE),

-- Performance Settings
('cache_enabled', 'false', 'boolean', 'Enable Redis caching', FALSE),
('cache_ttl', '300', 'integer', 'Default cache TTL in seconds', FALSE),
('api_rate_limit', '60', 'integer', 'API requests per minute per user', FALSE),
('enable_query_log', 'false', 'boolean', 'Enable database query logging', FALSE),

-- Security Settings
('password_expiry_days', '0', 'integer', 'Password expiration in days (0 = never)', FALSE),
('require_password_change', 'false', 'boolean', 'Require password change on first login', FALSE),
('allowed_ip_ranges', '*', 'string', 'Allowed IP ranges for admin access (* = all)', FALSE),
('enable_audit_log', 'true', 'boolean', 'Enable audit logging', FALSE),
('session_ip_validation', 'false', 'boolean', 'Validate session IP address', FALSE);

-- =============================================================================
-- SEED DATA: CONTROLLERS (Sample)
-- =============================================================================
-- Sample MikroTik Controller (for testing)
INSERT INTO controllers (name, type, host, port, username, password, api_key, status, description) VALUES
('Demo MikroTik RB4011', 'mikrotik', '192.168.88.1', 8728, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'inactive', 'Demo MikroTik router for testing - NOT ACTIVE'),
('Demo Omada Controller', 'omada', '192.168.1.100', 8043, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'demo-site-id', 'inactive', 'Demo Omada controller for testing - NOT ACTIVE');

-- =============================================================================
-- SEED DATA: VOUCHERS (Sample Batch)
-- =============================================================================
-- Sample voucher batch for testing
-- Vouchers: WF-TEST001 through WF-TEST010
-- Each provides 7-day access with 5Mbps speed
INSERT INTO vouchers (code, plan_id, valid_from, valid_until, status, max_uses, current_uses, batch_id) VALUES
('WF-TEST001', 2, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-001'),
('WF-TEST002', 2, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-001'),
('WF-TEST003', 2, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-001'),
('WF-TEST004', 2, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-001'),
('WF-TEST005', 2, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-001'),
('WF-TEST006', 3, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-002'),
('WF-TEST007', 3, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-002'),
('WF-TEST008', 3, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-002'),
('WF-TEST009', 3, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-002'),
('WF-TEST010', 3, NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'active', 1, 0, 'DEMO-BATCH-002');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Display summary
SELECT 'Seed data installation completed successfully!' AS status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_plans FROM plans;
SELECT COUNT(*) AS total_settings FROM system_settings;
SELECT COUNT(*) AS total_vouchers FROM vouchers;
