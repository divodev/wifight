-- WiFight ISP System - Phase 2 Optimization Migration
-- Version: 1.1
-- Created: 2024-11-18
-- Purpose: Optimize database for controller integration and session management

USE wifight_isp;

-- =============================================================================
-- 1. ADD COMPOSITE INDEXES FOR COMMON QUERIES
-- =============================================================================

-- Sessions: Common query pattern - active sessions by controller
ALTER TABLE sessions
ADD INDEX idx_controller_status (controller_id, status);

-- Sessions: User active sessions lookup
ALTER TABLE sessions
ADD INDEX idx_user_status (user_id, status);

-- Sessions: MAC address with status for connection checks
ALTER TABLE sessions
ADD INDEX idx_mac_status (mac_address, status);

-- Controllers: Type and status for load balancing
ALTER TABLE controllers
ADD INDEX idx_type_status (type, status);

-- Plans: Controller and status for plan assignment
ALTER TABLE plans
ADD INDEX idx_controller_status (controller_id, status);

-- Subscriptions: User and status with end date for expiration checks
ALTER TABLE subscriptions
ADD INDEX idx_user_status_end (user_id, status, end_date);

-- Audit logs: Entity type and ID for entity history
ALTER TABLE audit_logs
ADD INDEX idx_entity (entity_type, entity_id);

-- Payments: User, status, and date for revenue queries
ALTER TABLE payments
ADD INDEX idx_user_status_date (user_id, status, payment_date);

-- =============================================================================
-- 2. ADD MISSING COLUMNS FOR CONTROLLER MANAGEMENT
-- =============================================================================

-- Add response time tracking for controller health monitoring
ALTER TABLE controllers
ADD COLUMN avg_response_time INT DEFAULT 0 COMMENT 'Average response time in milliseconds',
ADD COLUMN last_response_time INT COMMENT 'Last response time in milliseconds',
ADD COLUMN connection_failures INT DEFAULT 0 COMMENT 'Count of consecutive connection failures',
ADD COLUMN last_error TEXT COMMENT 'Last error message from controller';

-- Add bandwidth limits to sessions for better tracking
ALTER TABLE sessions
ADD COLUMN bandwidth_limit_up INT COMMENT 'Upload bandwidth limit in Kbps',
ADD COLUMN bandwidth_limit_down INT COMMENT 'Download bandwidth limit in Kbps',
ADD COLUMN data_limit_bytes BIGINT COMMENT 'Session data limit in bytes',
ADD COLUMN nas_ip_address VARCHAR(45) COMMENT 'NAS IP address for RADIUS';

-- Add controller-specific metadata to sessions
ALTER TABLE sessions
ADD COLUMN controller_session_id VARCHAR(100) COMMENT 'Controller-specific session ID',
ADD INDEX idx_controller_session (controller_session_id);

-- =============================================================================
-- 3. CREATE CONTROLLER HEALTH MONITORING TABLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS controller_health_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    controller_id INT NOT NULL,
    status ENUM('online', 'offline', 'degraded', 'error') NOT NULL,
    response_time INT COMMENT 'Response time in milliseconds',
    active_sessions INT DEFAULT 0,
    cpu_usage DECIMAL(5,2) COMMENT 'CPU usage percentage',
    memory_usage DECIMAL(5,2) COMMENT 'Memory usage percentage',
    error_message TEXT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_controller_id (controller_id),
    INDEX idx_checked_at (checked_at),
    INDEX idx_status (status),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. CREATE API RATE LIMITING TABLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS api_rate_limits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_user_endpoint (user_id, endpoint),
    INDEX idx_ip_endpoint (ip_address, endpoint),
    INDEX idx_window_start (window_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. CREATE SESSION BANDWIDTH TRACKING TABLE (For detailed analytics)
-- =============================================================================

CREATE TABLE IF NOT EXISTS session_bandwidth_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    packets_in BIGINT DEFAULT 0,
    packets_out BIGINT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(recorded_at)) (
    PARTITION p_old VALUES LESS THAN (UNIX_TIMESTAMP('2024-01-01')),
    PARTITION p_2024 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
    PARTITION p_2025 VALUES LESS THAN (UNIX_TIMESTAMP('2026-01-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- =============================================================================
-- 6. CREATE ADDITIONAL VIEWS FOR CONTROLLER MANAGEMENT
-- =============================================================================

-- Controller health status view
CREATE OR REPLACE VIEW controller_health_view AS
SELECT
    c.id,
    c.name,
    c.type,
    c.status,
    c.avg_response_time,
    c.last_check,
    c.connection_failures,
    COUNT(DISTINCT s.id) as active_sessions,
    SUM(s.bytes_in + s.bytes_out) as total_traffic,
    CASE
        WHEN c.connection_failures >= 3 THEN 'critical'
        WHEN c.connection_failures >= 1 THEN 'warning'
        WHEN c.avg_response_time > 1000 THEN 'slow'
        WHEN c.status = 'online' THEN 'healthy'
        ELSE 'unknown'
    END as health_status
FROM controllers c
LEFT JOIN sessions s ON c.id = s.controller_id AND s.status = 'active'
GROUP BY c.id;

-- Session analytics view
CREATE OR REPLACE VIEW session_analytics_view AS
SELECT
    DATE(s.start_time) as date,
    c.name as controller_name,
    c.type as controller_type,
    COUNT(DISTINCT s.id) as total_sessions,
    COUNT(DISTINCT s.user_id) as unique_users,
    SUM(s.bytes_in + s.bytes_out) as total_bytes,
    AVG(s.duration) as avg_duration,
    COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_sessions,
    COUNT(CASE WHEN s.status = 'expired' THEN 1 END) as expired_sessions,
    COUNT(CASE WHEN s.status = 'terminated' THEN 1 END) as terminated_sessions
FROM sessions s
JOIN controllers c ON s.controller_id = c.id
GROUP BY DATE(s.start_time), c.id;

-- Plan usage statistics view
CREATE OR REPLACE VIEW plan_usage_view AS
SELECT
    p.id,
    p.name as plan_name,
    p.price,
    p.download_speed,
    p.upload_speed,
    COUNT(DISTINCT s.user_id) as total_users,
    COUNT(DISTINCT s.id) as total_sessions,
    SUM(s.bytes_in + s.bytes_out) as total_bandwidth,
    COUNT(DISTINCT sub.id) as active_subscriptions,
    SUM(pay.amount) as total_revenue
FROM plans p
LEFT JOIN sessions s ON p.id = s.plan_id
LEFT JOIN subscriptions sub ON p.id = sub.plan_id AND sub.status = 'active'
LEFT JOIN payments pay ON sub.id = pay.subscription_id AND pay.status = 'completed'
WHERE p.status = 'active'
GROUP BY p.id;

-- Revenue by controller view
CREATE OR REPLACE VIEW revenue_by_controller_view AS
SELECT
    c.id as controller_id,
    c.name as controller_name,
    c.type as controller_type,
    COUNT(DISTINCT s.user_id) as total_users,
    COUNT(DISTINCT s.id) as total_sessions,
    SUM(p.amount) as total_revenue,
    DATE(p.payment_date) as date
FROM controllers c
LEFT JOIN sessions s ON c.id = s.controller_id
LEFT JOIN subscriptions sub ON s.user_id = sub.user_id
LEFT JOIN payments p ON sub.id = p.subscription_id AND p.status = 'completed'
WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY c.id, DATE(p.payment_date)
ORDER BY date DESC;

-- =============================================================================
-- 7. CREATE STORED PROCEDURES FOR CONTROLLER MANAGEMENT
-- =============================================================================

-- Procedure to update controller health status
DELIMITER //
CREATE PROCEDURE update_controller_health(
    IN p_controller_id INT,
    IN p_status VARCHAR(20),
    IN p_response_time INT,
    IN p_error_message TEXT
)
BEGIN
    DECLARE v_failure_count INT;

    -- Get current failure count
    SELECT connection_failures INTO v_failure_count
    FROM controllers
    WHERE id = p_controller_id;

    -- Update failure count
    IF p_status = 'online' THEN
        SET v_failure_count = 0;
    ELSEIF p_status IN ('offline', 'error') THEN
        SET v_failure_count = v_failure_count + 1;
    END IF;

    -- Update controller
    UPDATE controllers
    SET
        status = p_status,
        last_check = NOW(),
        last_response_time = p_response_time,
        avg_response_time = IF(avg_response_time = 0, p_response_time,
            (avg_response_time * 0.8) + (p_response_time * 0.2)),
        connection_failures = v_failure_count,
        last_error = p_error_message
    WHERE id = p_controller_id;

    -- Log health check
    INSERT INTO controller_health_log (controller_id, status, response_time, error_message)
    VALUES (p_controller_id, p_status, p_response_time, p_error_message);

    -- Alert if controller is down for 3+ failures
    IF v_failure_count >= 3 THEN
        INSERT INTO notifications (user_id, type, title, message)
        SELECT
            u.id,
            'controller_alert',
            'Controller Down',
            CONCAT('Controller ', (SELECT name FROM controllers WHERE id = p_controller_id), ' has been offline for multiple checks')
        FROM users u
        WHERE u.role = 'admin';
    END IF;
END//
DELIMITER ;

-- Procedure to get active sessions by controller
DELIMITER //
CREATE PROCEDURE get_controller_sessions(
    IN p_controller_id INT
)
BEGIN
    SELECT
        s.id,
        s.user_id,
        u.username,
        s.mac_address,
        s.ip_address,
        s.start_time,
        s.bytes_in,
        s.bytes_out,
        s.status,
        p.name as plan_name,
        TIMESTAMPDIFF(SECOND, s.start_time, NOW()) as uptime_seconds
    FROM sessions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE s.controller_id = p_controller_id
    AND s.status = 'active'
    ORDER BY s.start_time DESC;
END//
DELIMITER ;

-- Procedure to terminate sessions by controller
DELIMITER //
CREATE PROCEDURE terminate_controller_sessions(
    IN p_controller_id INT,
    IN p_reason VARCHAR(255)
)
BEGIN
    UPDATE sessions
    SET
        status = 'terminated',
        end_time = NOW(),
        termination_reason = p_reason
    WHERE controller_id = p_controller_id
    AND status = 'active';

    SELECT ROW_COUNT() as sessions_terminated;
END//
DELIMITER ;

-- Procedure to cleanup old bandwidth logs
DELIMITER //
CREATE PROCEDURE cleanup_bandwidth_logs()
BEGIN
    -- Delete logs older than 90 days
    DELETE FROM session_bandwidth_log
    WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    SELECT ROW_COUNT() as logs_deleted;
END//
DELIMITER ;

-- Procedure to get controller load balancing info
DELIMITER //
CREATE PROCEDURE get_controller_load_balancing()
BEGIN
    SELECT
        c.id,
        c.name,
        c.type,
        c.status,
        c.avg_response_time,
        COUNT(DISTINCT s.id) as active_sessions,
        c.connection_failures,
        CASE
            WHEN c.status != 'online' THEN 0
            WHEN c.connection_failures > 0 THEN 25
            WHEN COUNT(DISTINCT s.id) > 100 THEN 50
            WHEN c.avg_response_time > 500 THEN 75
            ELSE 100
        END as health_score
    FROM controllers c
    LEFT JOIN sessions s ON c.id = s.controller_id AND s.status = 'active'
    GROUP BY c.id
    ORDER BY health_score DESC, active_sessions ASC;
END//
DELIMITER ;

-- =============================================================================
-- 8. CREATE TRIGGERS FOR CONTROLLER MANAGEMENT
-- =============================================================================

-- Trigger to log controller status changes
DELIMITER //
CREATE TRIGGER controller_status_change
AFTER UPDATE ON controllers
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (
            NULL,
            'controller_status_changed',
            'controller',
            NEW.id,
            JSON_OBJECT(
                'old_status', OLD.status,
                'new_status', NEW.status,
                'controller_name', NEW.name,
                'controller_type', NEW.type
            )
        );
    END IF;
END//
DELIMITER ;

-- Trigger to log session creation
DELIMITER //
CREATE TRIGGER session_created_log
AFTER INSERT ON sessions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (
        NEW.user_id,
        'session_created',
        'session',
        NEW.id,
        JSON_OBJECT(
            'controller_id', NEW.controller_id,
            'plan_id', NEW.plan_id,
            'mac_address', NEW.mac_address
        )
    );
END//
DELIMITER ;

-- =============================================================================
-- 9. ADD INDEXES FOR JSON COLUMN QUERIES (MySQL 8.0+)
-- =============================================================================

-- Create virtual columns for frequently queried JSON fields in controllers
ALTER TABLE controllers
ADD COLUMN config_timeout INT GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(config, '$.timeout'))) VIRTUAL,
ADD INDEX idx_config_timeout (config_timeout);

-- =============================================================================
-- 10. OPTIMIZE EXISTING TABLES
-- =============================================================================

-- Analyze tables to update statistics
ANALYZE TABLE users, controllers, plans, sessions, subscriptions, payments, vouchers;

-- Optimize tables
OPTIMIZE TABLE users, controllers, plans, sessions, subscriptions, payments, vouchers;

-- =============================================================================
-- Migration Complete
-- =============================================================================

SELECT 'Phase 2 database optimization complete!' as status,
       'Added composite indexes, health monitoring, rate limiting, and analytics views' as details;
