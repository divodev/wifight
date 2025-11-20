-- WiFight ISP System - Phase 2: Controller Integration Optimizations
-- Migration: 001_phase2_controller_optimizations.sql
-- Created: 2025-11-18
-- Purpose: Optimize database for multi-vendor controller integration

USE wifight_isp;

-- =============================================================================
-- 1. CONTROLLER ENHANCEMENTS
-- =============================================================================

-- Add connection pooling and health monitoring fields
ALTER TABLE controllers
ADD COLUMN max_connections INT DEFAULT 100 COMMENT 'Maximum concurrent connections',
ADD COLUMN current_connections INT DEFAULT 0 COMMENT 'Current active connections',
ADD COLUMN response_time_ms INT COMMENT 'Average API response time in milliseconds',
ADD COLUMN last_error TEXT COMMENT 'Last error message',
ADD COLUMN error_count INT DEFAULT 0 COMMENT 'Consecutive error count',
ADD COLUMN health_score DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Controller health score (0-100)',
ADD COLUMN firmware_version VARCHAR(50) COMMENT 'Controller firmware version',
ADD COLUMN uptime_seconds BIGINT COMMENT 'Controller uptime in seconds',
ADD COLUMN features JSON COMMENT 'Controller-specific feature flags';

-- Add composite index for type and status lookups
CREATE INDEX idx_type_status ON controllers(type, status);

-- Add index for health monitoring queries
CREATE INDEX idx_health_check ON controllers(status, last_check);

-- =============================================================================
-- 2. SESSIONS ENHANCEMENTS
-- =============================================================================

-- Add bandwidth tracking and session metrics
ALTER TABLE sessions
ADD COLUMN packets_in BIGINT DEFAULT 0 COMMENT 'Incoming packets count',
ADD COLUMN packets_out BIGINT DEFAULT 0 COMMENT 'Outgoing packets count',
ADD COLUMN nas_ip_address VARCHAR(45) COMMENT 'Network Access Server IP',
ADD COLUMN nas_port_id VARCHAR(100) COMMENT 'NAS Port identifier',
ADD COLUMN location VARCHAR(100) COMMENT 'Physical location/AP name',
ADD COLUMN device_type VARCHAR(50) COMMENT 'Device type (mobile, desktop, etc)',
ADD COLUMN browser VARCHAR(100) COMMENT 'User browser/client info',
ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last session activity time';

-- Composite index for active session queries by controller
CREATE INDEX idx_controller_status ON sessions(controller_id, status);

-- Composite index for user active sessions
CREATE INDEX idx_user_status ON sessions(user_id, status);

-- Composite index for date range queries
CREATE INDEX idx_start_end_time ON sessions(start_time, end_time);

-- Index for MAC address lookups with status
CREATE INDEX idx_mac_status ON sessions(mac_address, status);

-- Index for session expiration cleanup
CREATE INDEX idx_status_start ON sessions(status, start_time);

-- =============================================================================
-- 3. NEW TABLE: CONTROLLER HEALTH LOGS
-- =============================================================================

CREATE TABLE controller_health_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    controller_id INT NOT NULL,
    check_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('online', 'offline', 'degraded', 'error') NOT NULL,
    response_time_ms INT,
    cpu_usage DECIMAL(5,2) COMMENT 'CPU usage percentage',
    memory_usage DECIMAL(5,2) COMMENT 'Memory usage percentage',
    active_sessions INT DEFAULT 0,
    bandwidth_usage_mbps DECIMAL(10,2) COMMENT 'Current bandwidth usage',
    error_message TEXT,
    metadata JSON COMMENT 'Additional metrics',
    INDEX idx_controller_time (controller_id, check_time),
    INDEX idx_check_time (check_time),
    INDEX idx_status (status),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Controller health monitoring history';

-- =============================================================================
-- 4. NEW TABLE: API RATE LIMITING
-- =============================================================================

CREATE TABLE api_rate_limits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    controller_id INT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_duration INT DEFAULT 60 COMMENT 'Time window in seconds',
    max_requests INT DEFAULT 100 COMMENT 'Maximum requests per window',
    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL COMMENT 'Rate limit block expiry',
    INDEX idx_controller_endpoint (controller_id, endpoint),
    INDEX idx_window_start (window_start),
    INDEX idx_blocked_until (blocked_until),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_controller_endpoint (controller_id, endpoint, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='API rate limiting tracking per controller';

-- =============================================================================
-- 5. NEW TABLE: SESSION BANDWIDTH HISTORY
-- =============================================================================

CREATE TABLE session_bandwidth_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    packets_in BIGINT DEFAULT 0,
    packets_out BIGINT DEFAULT 0,
    download_speed_mbps DECIMAL(10,2) COMMENT 'Instantaneous download speed',
    upload_speed_mbps DECIMAL(10,2) COMMENT 'Instantaneous upload speed',
    INDEX idx_session_time (session_id, recorded_at),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historical bandwidth tracking for sessions';

-- Partition by month for efficient archival (optional, requires manual setup)
-- ALTER TABLE session_bandwidth_history PARTITION BY RANGE (UNIX_TIMESTAMP(recorded_at)) (
--     PARTITION p_current VALUES LESS THAN MAXVALUE
-- );

-- =============================================================================
-- 6. NEW TABLE: CONTROLLER CONFIGURATIONS CACHE
-- =============================================================================

CREATE TABLE controller_config_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    controller_id INT NOT NULL,
    config_type VARCHAR(50) NOT NULL COMMENT 'e.g., radius_profiles, ip_pools, bandwidth_limits',
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NOT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_valid BOOLEAN DEFAULT TRUE,
    INDEX idx_controller_type (controller_id, config_type),
    INDEX idx_expires (expires_at),
    INDEX idx_valid (is_valid),
    FOREIGN KEY (controller_id) REFERENCES controllers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_config (controller_id, config_type, config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cache for controller configurations to reduce API calls';

-- =============================================================================
-- 7. ENHANCED PLANS TABLE
-- =============================================================================

-- Add controller-specific configuration
ALTER TABLE plans
ADD COLUMN bandwidth_limit_type ENUM('simple', 'pcq', 'queue_tree') DEFAULT 'simple' COMMENT 'MikroTik: Queue type',
ADD COLUMN burst_enabled BOOLEAN DEFAULT FALSE COMMENT 'Enable burst speed',
ADD COLUMN burst_threshold DECIMAL(5,2) COMMENT 'Burst threshold percentage',
ADD COLUMN burst_time INT COMMENT 'Burst time in seconds',
ADD COLUMN controller_config JSON COMMENT 'Controller-specific settings';

-- Index for active plans by controller
CREATE INDEX idx_controller_status ON plans(controller_id, status);

-- =============================================================================
-- 8. ENHANCED SUBSCRIPTIONS TABLE
-- =============================================================================

-- Add MAC address tracking and session limits
ALTER TABLE subscriptions
ADD COLUMN max_mac_addresses INT DEFAULT 1 COMMENT 'Maximum devices allowed',
ADD COLUMN registered_macs JSON COMMENT 'Array of registered MAC addresses',
ADD COLUMN concurrent_sessions INT DEFAULT 1 COMMENT 'Maximum concurrent sessions',
ADD COLUMN last_used_mac VARCHAR(17) COMMENT 'Last MAC address used',
ADD COLUMN last_session_date TIMESTAMP NULL COMMENT 'Last session timestamp';

-- Composite index for active subscriptions by user
CREATE INDEX idx_user_status ON subscriptions(user_id, status);

-- Index for expiration checks
CREATE INDEX idx_status_end_date ON subscriptions(status, end_date);

-- =============================================================================
-- 9. ENHANCED AUDIT LOGS
-- =============================================================================

-- Add controller action tracking
ALTER TABLE audit_logs
ADD COLUMN controller_id INT COMMENT 'Related controller ID',
ADD COLUMN response_time_ms INT COMMENT 'API response time',
ADD COLUMN request_data JSON COMMENT 'Request payload',
ADD COLUMN response_data JSON COMMENT 'Response payload';

-- Index for controller audit trail
CREATE INDEX idx_controller_id ON audit_logs(controller_id);

-- Composite index for controller actions
CREATE INDEX idx_controller_action ON audit_logs(controller_id, action);

-- =============================================================================
-- 10. NEW VIEW: CONTROLLER DASHBOARD VIEW
-- =============================================================================

CREATE OR REPLACE VIEW controller_dashboard_view AS
SELECT
    c.id,
    c.name,
    c.type,
    c.status,
    c.current_connections,
    c.max_connections,
    ROUND((c.current_connections / c.max_connections * 100), 2) as connection_usage_percent,
    c.response_time_ms,
    c.health_score,
    c.firmware_version,
    c.last_check,
    COUNT(DISTINCT s.id) as active_sessions,
    COUNT(DISTINCT p.id) as assigned_plans,
    COALESCE(SUM(s.bytes_in + s.bytes_out), 0) as total_bandwidth_bytes,
    c.error_count,
    c.last_error
FROM controllers c
LEFT JOIN sessions s ON c.id = s.controller_id AND s.status = 'active'
LEFT JOIN plans p ON c.id = p.controller_id AND p.status = 'active'
GROUP BY c.id;

-- =============================================================================
-- 11. NEW VIEW: SESSION ANALYTICS VIEW
-- =============================================================================

CREATE OR REPLACE VIEW session_analytics_view AS
SELECT
    DATE(s.start_time) as session_date,
    c.type as controller_type,
    c.name as controller_name,
    COUNT(DISTINCT s.id) as total_sessions,
    COUNT(DISTINCT s.user_id) as unique_users,
    AVG(s.duration) as avg_duration_seconds,
    SUM(s.bytes_in + s.bytes_out) as total_bandwidth_bytes,
    AVG(s.bytes_in + s.bytes_out) as avg_bandwidth_per_session,
    COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_sessions,
    COUNT(CASE WHEN s.status = 'expired' THEN 1 END) as expired_sessions,
    COUNT(CASE WHEN s.status = 'terminated' THEN 1 END) as terminated_sessions
FROM sessions s
JOIN controllers c ON s.controller_id = c.id
GROUP BY DATE(s.start_time), c.id
ORDER BY session_date DESC, controller_name;

-- =============================================================================
-- 12. NEW VIEW: BANDWIDTH USAGE VIEW
-- =============================================================================

CREATE OR REPLACE VIEW bandwidth_usage_view AS
SELECT
    u.id as user_id,
    u.username,
    u.email,
    p.name as plan_name,
    p.data_limit,
    COUNT(DISTINCT s.id) as total_sessions,
    COALESCE(SUM(s.bytes_in), 0) as total_bytes_in,
    COALESCE(SUM(s.bytes_out), 0) as total_bytes_out,
    COALESCE(SUM(s.bytes_in + s.bytes_out), 0) as total_bandwidth_used,
    CASE
        WHEN p.data_limit IS NOT NULL THEN
            ROUND((SUM(s.bytes_in + s.bytes_out) / p.data_limit * 100), 2)
        ELSE NULL
    END as usage_percentage,
    MAX(s.start_time) as last_session_time
FROM users u
LEFT JOIN subscriptions sub ON u.id = sub.user_id AND sub.status = 'active'
LEFT JOIN plans p ON sub.plan_id = p.id
LEFT JOIN sessions s ON u.id = s.user_id
GROUP BY u.id, p.id;

-- =============================================================================
-- 13. STORED PROCEDURE: Update Controller Health
-- =============================================================================

DELIMITER //
CREATE PROCEDURE update_controller_health(
    IN p_controller_id INT,
    IN p_status VARCHAR(20),
    IN p_response_time INT,
    IN p_active_sessions INT
)
BEGIN
    DECLARE v_error_count INT;
    DECLARE v_health_score DECIMAL(5,2);

    -- Get current error count
    SELECT error_count INTO v_error_count
    FROM controllers
    WHERE id = p_controller_id;

    -- Update error count
    IF p_status = 'online' THEN
        SET v_error_count = 0;
    ELSE
        SET v_error_count = v_error_count + 1;
    END IF;

    -- Calculate health score
    SET v_health_score = 100;

    -- Deduct for errors
    SET v_health_score = v_health_score - (v_error_count * 10);

    -- Deduct for slow response
    IF p_response_time > 1000 THEN
        SET v_health_score = v_health_score - 20;
    ELSEIF p_response_time > 500 THEN
        SET v_health_score = v_health_score - 10;
    END IF;

    -- Ensure score doesn't go below 0
    IF v_health_score < 0 THEN
        SET v_health_score = 0;
    END IF;

    -- Update controller
    UPDATE controllers
    SET
        status = p_status,
        last_check = NOW(),
        response_time_ms = p_response_time,
        current_connections = p_active_sessions,
        error_count = v_error_count,
        health_score = v_health_score
    WHERE id = p_controller_id;

    -- Log health check
    INSERT INTO controller_health_logs (
        controller_id,
        status,
        response_time_ms,
        active_sessions
    ) VALUES (
        p_controller_id,
        p_status,
        p_response_time,
        p_active_sessions
    );

    SELECT v_health_score as health_score;
END//
DELIMITER ;

-- =============================================================================
-- 14. STORED PROCEDURE: Check API Rate Limit
-- =============================================================================

DELIMITER //
CREATE PROCEDURE check_api_rate_limit(
    IN p_controller_id INT,
    IN p_endpoint VARCHAR(255),
    OUT p_allowed BOOLEAN
)
BEGIN
    DECLARE v_request_count INT;
    DECLARE v_max_requests INT;
    DECLARE v_window_start TIMESTAMP;
    DECLARE v_blocked_until TIMESTAMP;

    -- Check if currently blocked
    SELECT blocked_until INTO v_blocked_until
    FROM api_rate_limits
    WHERE controller_id = p_controller_id
    AND endpoint = p_endpoint
    ORDER BY window_start DESC
    LIMIT 1;

    IF v_blocked_until IS NOT NULL AND v_blocked_until > NOW() THEN
        SET p_allowed = FALSE;
    ELSE
        -- Get or create rate limit record
        INSERT INTO api_rate_limits (
            controller_id,
            endpoint,
            request_count,
            window_start
        ) VALUES (
            p_controller_id,
            p_endpoint,
            1,
            NOW()
        ) ON DUPLICATE KEY UPDATE
            request_count = request_count + 1,
            last_request = NOW();

        -- Check if limit exceeded
        SELECT request_count, max_requests
        INTO v_request_count, v_max_requests
        FROM api_rate_limits
        WHERE controller_id = p_controller_id
        AND endpoint = p_endpoint
        AND window_start >= DATE_SUB(NOW(), INTERVAL window_duration SECOND)
        ORDER BY window_start DESC
        LIMIT 1;

        IF v_request_count > v_max_requests THEN
            SET p_allowed = FALSE;
            -- Block for 60 seconds
            UPDATE api_rate_limits
            SET blocked_until = DATE_ADD(NOW(), INTERVAL 60 SECOND)
            WHERE controller_id = p_controller_id
            AND endpoint = p_endpoint;
        ELSE
            SET p_allowed = TRUE;
        END IF;
    END IF;
END//
DELIMITER ;

-- =============================================================================
-- 15. STORED PROCEDURE: Record Session Bandwidth
-- =============================================================================

DELIMITER //
CREATE PROCEDURE record_session_bandwidth(
    IN p_session_id INT,
    IN p_bytes_in BIGINT,
    IN p_bytes_out BIGINT,
    IN p_packets_in BIGINT,
    IN p_packets_out BIGINT
)
BEGIN
    -- Update session totals
    UPDATE sessions
    SET
        bytes_in = bytes_in + p_bytes_in,
        bytes_out = bytes_out + p_bytes_out,
        packets_in = packets_in + p_packets_in,
        packets_out = packets_out + p_packets_out,
        last_activity = NOW()
    WHERE id = p_session_id;

    -- Record in history (for analytics)
    INSERT INTO session_bandwidth_history (
        session_id,
        bytes_in,
        bytes_out,
        packets_in,
        packets_out
    ) VALUES (
        p_session_id,
        p_bytes_in,
        p_bytes_out,
        p_packets_in,
        p_packets_out
    );
END//
DELIMITER ;

-- =============================================================================
-- 16. TRIGGER: Validate Subscription MAC Limit
-- =============================================================================

DELIMITER //
CREATE TRIGGER validate_subscription_mac
BEFORE INSERT ON sessions
FOR EACH ROW
BEGIN
    DECLARE v_max_macs INT;
    DECLARE v_registered_macs JSON;
    DECLARE v_mac_count INT;
    DECLARE v_subscription_id INT;

    -- Get active subscription for user
    SELECT id, max_mac_addresses, registered_macs
    INTO v_subscription_id, v_max_macs, v_registered_macs
    FROM subscriptions
    WHERE user_id = NEW.user_id
    AND status = 'active'
    AND CURDATE() BETWEEN start_date AND end_date
    LIMIT 1;

    IF v_subscription_id IS NOT NULL THEN
        -- Count registered MACs
        SET v_mac_count = JSON_LENGTH(COALESCE(v_registered_macs, '[]'));

        -- Check if MAC is already registered
        IF NOT JSON_CONTAINS(COALESCE(v_registered_macs, '[]'), JSON_QUOTE(NEW.mac_address)) THEN
            -- Check if we've reached the limit
            IF v_mac_count >= v_max_macs THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Maximum MAC address limit reached for subscription';
            ELSE
                -- Register new MAC
                UPDATE subscriptions
                SET registered_macs = JSON_ARRAY_APPEND(
                    COALESCE(registered_macs, '[]'),
                    '$',
                    NEW.mac_address
                ),
                last_used_mac = NEW.mac_address,
                last_session_date = NOW()
                WHERE id = v_subscription_id;
            END IF;
        END IF;
    END IF;
END//
DELIMITER ;

-- =============================================================================
-- 17. EVENT: Cleanup Old Health Logs (runs daily)
-- =============================================================================

SET GLOBAL event_scheduler = ON;

DELIMITER //
CREATE EVENT cleanup_old_health_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Delete health logs older than 30 days
    DELETE FROM controller_health_logs
    WHERE check_time < DATE_SUB(NOW(), INTERVAL 30 DAY);

    -- Delete bandwidth history older than 90 days
    DELETE FROM session_bandwidth_history
    WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    -- Reset rate limit windows older than 1 hour
    DELETE FROM api_rate_limits
    WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END//
DELIMITER ;

-- =============================================================================
-- 18. OPTIMIZATION: Add JSON Indexes (MySQL 8.0+)
-- =============================================================================

-- Virtual columns for JSON indexing (improves query performance)
ALTER TABLE controllers
ADD COLUMN controller_type_extracted VARCHAR(20) AS (JSON_UNQUOTE(JSON_EXTRACT(config, '$.type'))) VIRTUAL,
ADD INDEX idx_config_type (controller_type_extracted);

-- =============================================================================
-- Migration Complete
-- =============================================================================

SELECT
    'Phase 2 Controller Integration Migration Complete!' as status,
    COUNT(*) as new_tables_created
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_name IN (
    'controller_health_logs',
    'api_rate_limits',
    'session_bandwidth_history',
    'controller_config_cache'
);
