-- WiFight ISP System - Stored Procedures
-- Version: 1.0
-- Description: Stored procedures for common database operations

USE wifight_isp;

DELIMITER $$

-- =============================================================================
-- PROCEDURE: sp_create_session
-- Description: Create a new user session with validation
-- Parameters:
--   - p_user_id: User ID
--   - p_controller_id: Controller ID
--   - p_plan_id: Plan ID
--   - p_mac_address: MAC address of user device
--   - p_ip_address: IP address assigned to user
-- Returns: Session ID or error code
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_create_session$$
CREATE PROCEDURE sp_create_session(
    IN p_user_id INT,
    IN p_controller_id INT,
    IN p_plan_id INT,
    IN p_mac_address VARCHAR(17),
    IN p_ip_address VARCHAR(45),
    OUT p_session_id INT,
    OUT p_error_code VARCHAR(50),
    OUT p_error_message VARCHAR(255)
)
BEGIN
    DECLARE v_max_devices INT;
    DECLARE v_active_sessions INT;
    DECLARE v_user_status VARCHAR(20);
    DECLARE v_user_balance DECIMAL(10,2);
    DECLARE v_plan_price DECIMAL(10,2);
    DECLARE v_controller_status VARCHAR(20);

    -- Initialize output variables
    SET p_session_id = NULL;
    SET p_error_code = NULL;
    SET p_error_message = NULL;

    -- Start transaction
    START TRANSACTION;

    -- Check if user exists and is active
    SELECT status, balance INTO v_user_status, v_user_balance
    FROM users WHERE id = p_user_id;

    IF v_user_status IS NULL THEN
        SET p_error_code = 'USER_NOT_FOUND';
        SET p_error_message = 'User does not exist';
        ROLLBACK;
    ELSEIF v_user_status != 'active' THEN
        SET p_error_code = 'USER_INACTIVE';
        SET p_error_message = 'User account is not active';
        ROLLBACK;
    ELSE
        -- Check controller status
        SELECT status INTO v_controller_status
        FROM controllers WHERE id = p_controller_id;

        IF v_controller_status != 'active' THEN
            SET p_error_code = 'CONTROLLER_INACTIVE';
            SET p_error_message = 'Controller is not active';
            ROLLBACK;
        ELSE
            -- Check plan price and user balance
            SELECT price INTO v_plan_price
            FROM plans WHERE id = p_plan_id AND status = 'active';

            IF v_plan_price IS NULL THEN
                SET p_error_code = 'PLAN_NOT_FOUND';
                SET p_error_message = 'Plan does not exist or is inactive';
                ROLLBACK;
            ELSEIF v_user_balance < v_plan_price THEN
                SET p_error_code = 'INSUFFICIENT_BALANCE';
                SET p_error_message = 'Insufficient balance to start session';
                ROLLBACK;
            ELSE
                -- Check concurrent session limit
                SELECT max_devices INTO v_max_devices
                FROM plans WHERE id = p_plan_id;

                SELECT COUNT(*) INTO v_active_sessions
                FROM sessions
                WHERE user_id = p_user_id AND status = 'active';

                IF v_active_sessions >= v_max_devices THEN
                    SET p_error_code = 'MAX_SESSIONS_REACHED';
                    SET p_error_message = 'Maximum concurrent sessions reached';
                    ROLLBACK;
                ELSE
                    -- Create session
                    INSERT INTO sessions (user_id, controller_id, plan_id, mac_address, ip_address, status, start_time)
                    VALUES (p_user_id, p_controller_id, p_plan_id, p_mac_address, p_ip_address, 'active', NOW());

                    SET p_session_id = LAST_INSERT_ID();

                    -- Deduct plan price from user balance
                    UPDATE users
                    SET balance = balance - v_plan_price
                    WHERE id = p_user_id;

                    -- Log audit
                    INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address)
                    VALUES (p_user_id, 'SESSION_STARTED', 'sessions', p_session_id, p_ip_address);

                    COMMIT;
                END IF;
            END IF;
        END IF;
    END IF;
END$$

-- =============================================================================
-- PROCEDURE: sp_end_session
-- Description: End an active session and calculate usage
-- Parameters:
--   - p_session_id: Session ID to end
--   - p_bytes_in: Total bytes received
--   - p_bytes_out: Total bytes transmitted
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_end_session$$
CREATE PROCEDURE sp_end_session(
    IN p_session_id INT,
    IN p_bytes_in BIGINT,
    IN p_bytes_out BIGINT,
    OUT p_success BOOLEAN,
    OUT p_error_message VARCHAR(255)
)
BEGIN
    DECLARE v_session_status VARCHAR(20);
    DECLARE v_user_id INT;

    SET p_success = FALSE;
    SET p_error_message = NULL;

    START TRANSACTION;

    -- Check if session exists and is active
    SELECT status, user_id INTO v_session_status, v_user_id
    FROM sessions WHERE id = p_session_id;

    IF v_session_status IS NULL THEN
        SET p_error_message = 'Session not found';
        ROLLBACK;
    ELSEIF v_session_status != 'active' THEN
        SET p_error_message = 'Session is not active';
        ROLLBACK;
    ELSE
        -- Update session
        UPDATE sessions
        SET status = 'completed',
            end_time = NOW(),
            bytes_in = p_bytes_in,
            bytes_out = p_bytes_out
        WHERE id = p_session_id;

        -- Log audit
        INSERT INTO audit_logs (user_id, action, table_name, record_id)
        VALUES (v_user_id, 'SESSION_ENDED', 'sessions', p_session_id);

        SET p_success = TRUE;
        COMMIT;
    END IF;
END$$

-- =============================================================================
-- PROCEDURE: sp_cleanup_expired_sessions
-- Description: Clean up expired and stale sessions
-- Returns: Number of sessions cleaned up
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_cleanup_expired_sessions$$
CREATE PROCEDURE sp_cleanup_expired_sessions(
    OUT p_cleaned_count INT
)
BEGIN
    DECLARE v_timeout INT;

    -- Get session timeout from settings
    SELECT CAST(setting_value AS SIGNED) INTO v_timeout
    FROM system_settings WHERE setting_key = 'session_timeout';

    IF v_timeout IS NULL THEN
        SET v_timeout = 86400; -- Default 24 hours
    END IF;

    START TRANSACTION;

    -- Update expired sessions
    UPDATE sessions
    SET status = 'expired',
        end_time = NOW()
    WHERE status = 'active'
    AND TIMESTAMPDIFF(SECOND, start_time, NOW()) > v_timeout;

    SET p_cleaned_count = ROW_COUNT();

    -- Log cleanup action
    INSERT INTO audit_logs (user_id, action, table_name, details)
    VALUES (NULL, 'SESSION_CLEANUP', 'sessions', CONCAT(p_cleaned_count, ' sessions expired'));

    COMMIT;
END$$

-- =============================================================================
-- PROCEDURE: sp_generate_voucher_batch
-- Description: Generate a batch of vouchers for a specific plan
-- Parameters:
--   - p_plan_id: Plan ID
--   - p_count: Number of vouchers to generate
--   - p_validity_days: Voucher validity in days
--   - p_batch_id: Batch identifier
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_generate_voucher_batch$$
CREATE PROCEDURE sp_generate_voucher_batch(
    IN p_plan_id INT,
    IN p_count INT,
    IN p_validity_days INT,
    IN p_batch_id VARCHAR(50),
    OUT p_generated_count INT
)
BEGIN
    DECLARE v_counter INT DEFAULT 0;
    DECLARE v_code VARCHAR(50);
    DECLARE v_prefix VARCHAR(10);
    DECLARE v_code_length INT;

    SET p_generated_count = 0;

    -- Get voucher settings
    SELECT setting_value INTO v_prefix
    FROM system_settings WHERE setting_key = 'voucher_code_prefix';

    SELECT CAST(setting_value AS SIGNED) INTO v_code_length
    FROM system_settings WHERE setting_key = 'voucher_code_length';

    IF v_prefix IS NULL THEN SET v_prefix = 'WF-'; END IF;
    IF v_code_length IS NULL THEN SET v_code_length = 12; END IF;

    START TRANSACTION;

    WHILE v_counter < p_count DO
        -- Generate unique voucher code
        SET v_code = CONCAT(
            v_prefix,
            UPPER(SUBSTRING(MD5(CONCAT(UUID(), RAND())), 1, v_code_length - LENGTH(v_prefix)))
        );

        -- Insert voucher
        INSERT INTO vouchers (code, plan_id, valid_from, valid_until, status, max_uses, current_uses, batch_id)
        VALUES (
            v_code,
            p_plan_id,
            NOW(),
            DATE_ADD(NOW(), INTERVAL p_validity_days DAY),
            'active',
            1,
            0,
            p_batch_id
        );

        SET v_counter = v_counter + 1;
        SET p_generated_count = p_generated_count + 1;
    END WHILE;

    -- Log batch generation
    INSERT INTO audit_logs (user_id, action, table_name, details)
    VALUES (NULL, 'VOUCHER_BATCH_GENERATED', 'vouchers', CONCAT('Generated ', p_generated_count, ' vouchers in batch ', p_batch_id));

    COMMIT;
END$$

-- =============================================================================
-- PROCEDURE: sp_calculate_user_balance
-- Description: Calculate and update user balance based on payments and usage
-- Parameters:
--   - p_user_id: User ID
-- Returns: Current balance
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_calculate_user_balance$$
CREATE PROCEDURE sp_calculate_user_balance(
    IN p_user_id INT,
    OUT p_balance DECIMAL(10,2)
)
BEGIN
    DECLARE v_total_payments DECIMAL(10,2);
    DECLARE v_total_spent DECIMAL(10,2);

    -- Calculate total payments
    SELECT COALESCE(SUM(amount), 0) INTO v_total_payments
    FROM payments
    WHERE user_id = p_user_id AND status = 'completed';

    -- Calculate total spent (sessions)
    SELECT COALESCE(SUM(p.price), 0) INTO v_total_spent
    FROM sessions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.user_id = p_user_id AND s.status IN ('active', 'completed');

    -- Calculate balance
    SET p_balance = v_total_payments - v_total_spent;

    -- Update user balance
    UPDATE users
    SET balance = p_balance
    WHERE id = p_user_id;
END$$

-- =============================================================================
-- PROCEDURE: sp_get_revenue_report
-- Description: Generate revenue report for a date range
-- Parameters:
--   - p_start_date: Start date
--   - p_end_date: End date
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_get_revenue_report$$
CREATE PROCEDURE sp_get_revenue_report(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT
        DATE(created_at) as report_date,
        payment_method,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue,
        SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_revenue,
        AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_transaction
    FROM payments
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(created_at), payment_method
    ORDER BY report_date DESC, payment_method;

    -- Summary
    SELECT
        'TOTAL' as period,
        COUNT(*) as total_transactions,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue,
        SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_revenue
    FROM payments
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date;
END$$

-- =============================================================================
-- PROCEDURE: sp_get_user_statistics
-- Description: Get comprehensive statistics for a user
-- Parameters:
--   - p_user_id: User ID
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_get_user_statistics$$
CREATE PROCEDURE sp_get_user_statistics(
    IN p_user_id INT
)
BEGIN
    -- User session statistics
    SELECT
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sessions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_sessions,
        SUM(bytes_in) as total_bytes_in,
        SUM(bytes_out) as total_bytes_out,
        SUM(bytes_in + bytes_out) as total_bandwidth_used,
        AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_session_duration_minutes
    FROM sessions
    WHERE user_id = p_user_id;

    -- User payment statistics
    SELECT
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_payments,
        AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment_amount,
        MAX(created_at) as last_payment_date
    FROM payments
    WHERE user_id = p_user_id;

    -- User active subscriptions
    SELECT
        COUNT(*) as active_subscriptions,
        MIN(next_billing_date) as next_billing_date
    FROM subscriptions
    WHERE user_id = p_user_id AND status = 'active';
END$$

-- =============================================================================
-- PROCEDURE: sp_apply_voucher
-- Description: Apply a voucher code to a user account
-- Parameters:
--   - p_user_id: User ID
--   - p_voucher_code: Voucher code
-- =============================================================================
DROP PROCEDURE IF EXISTS sp_apply_voucher$$
CREATE PROCEDURE sp_apply_voucher(
    IN p_user_id INT,
    IN p_voucher_code VARCHAR(50),
    OUT p_success BOOLEAN,
    OUT p_error_message VARCHAR(255),
    OUT p_subscription_id INT
)
BEGIN
    DECLARE v_voucher_id INT;
    DECLARE v_plan_id INT;
    DECLARE v_status VARCHAR(20);
    DECLARE v_valid_from DATETIME;
    DECLARE v_valid_until DATETIME;
    DECLARE v_max_uses INT;
    DECLARE v_current_uses INT;
    DECLARE v_plan_duration INT;

    SET p_success = FALSE;
    SET p_error_message = NULL;
    SET p_subscription_id = NULL;

    START TRANSACTION;

    -- Get voucher details
    SELECT id, plan_id, status, valid_from, valid_until, max_uses, current_uses
    INTO v_voucher_id, v_plan_id, v_status, v_valid_from, v_valid_until, v_max_uses, v_current_uses
    FROM vouchers
    WHERE code = p_voucher_code;

    IF v_voucher_id IS NULL THEN
        SET p_error_message = 'Invalid voucher code';
        ROLLBACK;
    ELSEIF v_status != 'active' THEN
        SET p_error_message = 'Voucher is not active';
        ROLLBACK;
    ELSEIF NOW() < v_valid_from THEN
        SET p_error_message = 'Voucher is not yet valid';
        ROLLBACK;
    ELSEIF NOW() > v_valid_until THEN
        SET p_error_message = 'Voucher has expired';
        ROLLBACK;
    ELSEIF v_current_uses >= v_max_uses THEN
        SET p_error_message = 'Voucher has reached maximum uses';
        ROLLBACK;
    ELSE
        -- Get plan duration
        SELECT duration_days INTO v_plan_duration
        FROM plans WHERE id = v_plan_id;

        -- Create subscription
        INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date, billing_cycle, payment_method)
        VALUES (
            p_user_id,
            v_plan_id,
            'active',
            NOW(),
            DATE_ADD(NOW(), INTERVAL v_plan_duration DAY),
            'one-time',
            'voucher'
        );

        SET p_subscription_id = LAST_INSERT_ID();

        -- Update voucher usage
        UPDATE vouchers
        SET current_uses = current_uses + 1,
            status = CASE WHEN current_uses + 1 >= max_uses THEN 'used' ELSE 'active' END
        WHERE id = v_voucher_id;

        -- Log audit
        INSERT INTO audit_logs (user_id, action, table_name, record_id, details)
        VALUES (p_user_id, 'VOUCHER_APPLIED', 'vouchers', v_voucher_id, CONCAT('Voucher ', p_voucher_code, ' applied'));

        SET p_success = TRUE;
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- Display summary
SELECT 'Stored procedures created successfully!' AS status;
SELECT routine_name, routine_type
FROM information_schema.routines
WHERE routine_schema = 'wifight_isp'
ORDER BY routine_name;
