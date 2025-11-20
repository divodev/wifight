-- WiFight ISP System - Database Schema
-- Version: 1.0
-- Created: 2024-11-18

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS wifight_isp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wifight_isp;

-- Drop tables if they exist (for fresh install)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS radius_accounting;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS controllers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS system_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Users Table
-- =============================================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'reseller', 'user') DEFAULT 'user',
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(32),
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Controllers Table (Multi-Vendor Support)
-- =============================================================================
CREATE TABLE controllers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('mikrotik', 'omada', 'ruijie', 'meraki', 'cisco') NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT,
    username VARCHAR(100),
    password VARCHAR(255),
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    network_id VARCHAR(100),
    site_id VARCHAR(100),
    status ENUM('online', 'offline', 'error') DEFAULT 'offline',
    last_check TIMESTAMP NULL,
    config JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Plans Table
-- =============================================================================
CREATE TABLE plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    download_speed INT NOT NULL COMMENT 'Mbps',
    upload_speed INT NOT NULL COMMENT 'Mbps',
    duration INT NOT NULL COMMENT 'Days',
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    data_limit BIGINT COMMENT 'Bytes, NULL for unlimited',
    simultaneous_users INT DEFAULT 1,
    controller_id INT,
    radius_profile VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_controller_id (controller_id),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- User Sessions Table
-- =============================================================================
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT,
    controller_id INT NOT NULL,
    mac_address VARCHAR(17) NOT NULL,
    ip_address VARCHAR(45),
    session_id VARCHAR(100),
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration INT COMMENT 'Seconds',
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    status ENUM('active', 'expired', 'terminated', 'suspended') DEFAULT 'active',
    termination_reason VARCHAR(255),
    INDEX idx_user_id (user_id),
    INDEX idx_mac_address (mac_address),
    INDEX idx_status (status),
    INDEX idx_controller_id (controller_id),
    INDEX idx_start_time (start_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL,
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Subscriptions Table (Recurring Billing)
-- =============================================================================
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    controller_id INT,
    mac_address VARCHAR(17),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Payments Table
-- =============================================================================
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    gateway VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Vouchers Table (Prepaid Codes)
-- =============================================================================
CREATE TABLE vouchers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    plan_id INT NOT NULL,
    batch_id VARCHAR(50),
    created_by INT,
    used_by INT,
    status ENUM('available', 'used', 'expired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_batch_id (batch_id),
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- RADIUS Accounting Table
-- =============================================================================
CREATE TABLE radius_accounting (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(100) NOT NULL,
    user_id INT,
    mac_address VARCHAR(17) NOT NULL,
    nas_ip_address VARCHAR(45),
    acct_start_time TIMESTAMP NULL,
    acct_stop_time TIMESTAMP NULL,
    acct_session_time INT,
    acct_input_octets BIGINT DEFAULT 0,
    acct_output_octets BIGINT DEFAULT 0,
    acct_terminate_cause VARCHAR(50),
    INDEX idx_session_id (session_id),
    INDEX idx_mac_address (mac_address),
    INDEX idx_start_time (acct_start_time),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Audit Logs Table
-- =============================================================================
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Notifications Table
-- =============================================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- System Settings Table
-- =============================================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Insert Default Data
-- =============================================================================

-- Default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES
('admin', 'admin@wifight.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');

-- Default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'WiFight ISP System', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Application version'),
('maintenance_mode', 'false', 'boolean', 'Maintenance mode status'),
('registration_enabled', 'true', 'boolean', 'Allow new user registration'),
('default_currency', 'USD', 'string', 'Default currency code'),
('timezone', 'UTC', 'string', 'System timezone');

-- Sample plan (Optional)
INSERT INTO plans (name, description, download_speed, upload_speed, duration, price, status) VALUES
('Basic 5Mbps', '5Mbps internet plan for 30 days', 5, 5, 30, 10.00, 'active');

-- =============================================================================
-- Database Triggers
-- =============================================================================

-- Trigger to log user updates
DELIMITER //
CREATE TRIGGER user_update_log
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (NEW.id, 'user_updated', 'user', NEW.id, JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status));
END//
DELIMITER ;

-- Trigger to update session duration on end
DELIMITER //
CREATE TRIGGER session_end_duration
BEFORE UPDATE ON sessions
FOR EACH ROW
BEGIN
    IF NEW.end_time IS NOT NULL AND OLD.end_time IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END//
DELIMITER ;

-- =============================================================================
-- Views for Reporting
-- =============================================================================

-- Active sessions view
CREATE OR REPLACE VIEW active_sessions_view AS
SELECT
    s.id,
    s.mac_address,
    s.ip_address,
    u.username,
    u.email,
    p.name as plan_name,
    c.name as controller_name,
    c.type as controller_type,
    s.start_time,
    s.bytes_in,
    s.bytes_out,
    TIMESTAMPDIFF(SECOND, s.start_time, NOW()) as uptime_seconds
FROM sessions s
JOIN users u ON s.user_id = u.id
LEFT JOIN plans p ON s.plan_id = p.id
JOIN controllers c ON s.controller_id = c.id
WHERE s.status = 'active';

-- Daily revenue view
CREATE OR REPLACE VIEW daily_revenue_view AS
SELECT
    DATE(payment_date) as date,
    COUNT(*) as transaction_count,
    SUM(amount) as total_revenue,
    AVG(amount) as average_transaction,
    currency
FROM payments
WHERE status = 'completed'
GROUP BY DATE(payment_date), currency
ORDER BY date DESC;

-- User statistics view
CREATE OR REPLACE VIEW user_stats_view AS
SELECT
    u.id,
    u.username,
    u.email,
    u.role,
    u.balance,
    COUNT(DISTINCT s.id) as total_sessions,
    SUM(s.bytes_in + s.bytes_out) as total_data_used,
    COUNT(DISTINCT p.id) as total_payments,
    SUM(p.amount) as total_spent
FROM users u
LEFT JOIN sessions s ON u.id = s.user_id
LEFT JOIN payments p ON u.id = p.user_id AND p.status = 'completed'
GROUP BY u.id;

-- =============================================================================
-- Stored Procedures
-- =============================================================================

-- Procedure to cleanup expired sessions
DELIMITER //
CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    UPDATE sessions
    SET status = 'expired',
        end_time = NOW(),
        termination_reason = 'Automatic cleanup - session expired'
    WHERE status = 'active'
    AND start_time < DATE_SUB(NOW(), INTERVAL 7 DAY);

    SELECT ROW_COUNT() as sessions_cleaned;
END//
DELIMITER ;

-- Procedure to expire old vouchers
DELIMITER //
CREATE PROCEDURE expire_old_vouchers()
BEGIN
    UPDATE vouchers
    SET status = 'expired'
    WHERE status = 'available'
    AND expires_at < NOW();

    SELECT ROW_COUNT() as vouchers_expired;
END//
DELIMITER ;

-- =============================================================================
-- Initial Setup Complete
-- =============================================================================
SELECT 'WiFight ISP System database schema created successfully!' as status;
