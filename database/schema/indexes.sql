-- WiFight ISP System - Performance Indexes
-- Version: 1.0
-- Description: Additional performance indexes for optimizing common queries
-- Note: Basic indexes are already defined in schema.sql. This file contains advanced composite and covering indexes.

USE wifight_isp;

-- =============================================================================
-- USERS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for authentication queries (email + status + role)
CREATE INDEX idx_users_auth_lookup ON users(email, status, role);

-- Composite index for user listing with filtering
CREATE INDEX idx_users_role_status ON users(role, status, created_at DESC);

-- Index for balance queries and low balance alerts
CREATE INDEX idx_users_balance ON users(balance, status) WHERE status = 'active';

-- Index for last login tracking
CREATE INDEX idx_users_last_login ON users(last_login DESC) WHERE last_login IS NOT NULL;

-- Index for 2FA enabled users
CREATE INDEX idx_users_2fa ON users(two_factor_enabled, role) WHERE two_factor_enabled = TRUE;

-- =============================================================================
-- CONTROLLERS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for active controllers by type
CREATE INDEX idx_controllers_type_status ON controllers(type, status, name);

-- Index for controller health monitoring
CREATE INDEX idx_controllers_health ON controllers(status, last_sync) WHERE status = 'active';

-- Index for controller sync operations
CREATE INDEX idx_controllers_sync ON controllers(last_sync ASC) WHERE status = 'active';

-- =============================================================================
-- PLANS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for active plans listing
CREATE INDEX idx_plans_active ON plans(status, price ASC, bandwidth_down DESC) WHERE status = 'active';

-- Index for plan search by bandwidth
CREATE INDEX idx_plans_bandwidth ON plans(bandwidth_down, bandwidth_up, status);

-- Index for pricing queries
CREATE INDEX idx_plans_pricing ON plans(price ASC, currency, status) WHERE status = 'active';

-- Index for unlimited plans
CREATE INDEX idx_plans_unlimited ON plans(is_unlimited, status, price);

-- =============================================================================
-- SESSIONS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for active sessions by user
CREATE INDEX idx_sessions_user_active ON sessions(user_id, status, start_time DESC);

-- Index for session monitoring and cleanup
CREATE INDEX idx_sessions_status_time ON sessions(status, start_time DESC, end_time DESC);

-- Index for MAC address lookups (important for authentication)
CREATE INDEX idx_sessions_mac ON sessions(mac_address, status);

-- Index for controller sessions
CREATE INDEX idx_sessions_controller ON sessions(controller_id, status, start_time DESC);

-- Index for session cleanup operations
CREATE INDEX idx_sessions_cleanup ON sessions(end_time ASC) WHERE status = 'active';

-- Covering index for session statistics
CREATE INDEX idx_sessions_stats ON sessions(user_id, status, bytes_in, bytes_out, start_time);

-- Index for concurrent session limits
CREATE INDEX idx_sessions_concurrent ON sessions(user_id, status, start_time) WHERE status = 'active';

-- =============================================================================
-- SUBSCRIPTIONS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for active subscriptions
CREATE INDEX idx_subscriptions_active ON subscriptions(user_id, status, next_billing_date);

-- Index for subscription billing
CREATE INDEX idx_subscriptions_billing ON subscriptions(next_billing_date ASC, status) WHERE status = 'active';

-- Index for subscription by plan
CREATE INDEX idx_subscriptions_plan ON subscriptions(plan_id, status, created_at DESC);

-- Index for expiring subscriptions
CREATE INDEX idx_subscriptions_expiring ON subscriptions(end_date ASC, status) WHERE status = 'active' AND end_date IS NOT NULL;

-- Index for subscription status changes
CREATE INDEX idx_subscriptions_status_date ON subscriptions(status, created_at DESC, end_date ASC);

-- =============================================================================
-- PAYMENTS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for user payment history
CREATE INDEX idx_payments_user_history ON payments(user_id, created_at DESC, status);

-- Index for payment processing
CREATE INDEX idx_payments_status_date ON payments(status, created_at DESC);

-- Index for payment method analysis
CREATE INDEX idx_payments_method ON payments(payment_method, status, created_at DESC);

-- Index for transaction ID lookups
CREATE INDEX idx_payments_transaction ON payments(transaction_id);

-- Index for revenue reporting
CREATE INDEX idx_payments_revenue ON payments(status, created_at DESC, amount) WHERE status = 'completed';

-- Index for pending payments
CREATE INDEX idx_payments_pending ON payments(status, created_at ASC) WHERE status = 'pending';

-- =============================================================================
-- VOUCHERS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for voucher redemption
CREATE INDEX idx_vouchers_redemption ON vouchers(code, status, valid_from, valid_until);

-- Index for batch operations
CREATE INDEX idx_vouchers_batch ON vouchers(batch_id, status, created_at DESC);

-- Index for plan vouchers
CREATE INDEX idx_vouchers_plan ON vouchers(plan_id, status) WHERE status = 'active';

-- Index for voucher expiry management
CREATE INDEX idx_vouchers_expiry ON vouchers(valid_until ASC, status) WHERE status = 'active';

-- Index for usage tracking
CREATE INDEX idx_vouchers_usage ON vouchers(status, current_uses, max_uses);

-- =============================================================================
-- RADIUS_ACCOUNTING TABLE - Performance Indexes
-- =============================================================================

-- Composite index for user accounting data
CREATE INDEX idx_radius_user_time ON radius_accounting(user_id, event_timestamp DESC);

-- Index for session accounting
CREATE INDEX idx_radius_session ON radius_accounting(session_id, event_timestamp DESC);

-- Index for event type queries
CREATE INDEX idx_radius_event ON radius_accounting(event_type, event_timestamp DESC);

-- Index for data usage reporting
CREATE INDEX idx_radius_usage ON radius_accounting(user_id, bytes_in, bytes_out, event_timestamp DESC);

-- Index for accounting cleanup
CREATE INDEX idx_radius_cleanup ON radius_accounting(event_timestamp ASC);

-- Covering index for bandwidth analysis
CREATE INDEX idx_radius_bandwidth ON radius_accounting(user_id, event_timestamp DESC, bytes_in, bytes_out);

-- =============================================================================
-- AUDIT_LOGS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for user activity audit
CREATE INDEX idx_audit_user_action ON audit_logs(user_id, action, created_at DESC);

-- Index for action type filtering
CREATE INDEX idx_audit_action_time ON audit_logs(action, created_at DESC);

-- Index for IP address tracking
CREATE INDEX idx_audit_ip ON audit_logs(ip_address, created_at DESC);

-- Index for table auditing
CREATE INDEX idx_audit_table ON audit_logs(table_name, action, created_at DESC);

-- Index for audit log cleanup
CREATE INDEX idx_audit_cleanup ON audit_logs(created_at ASC);

-- =============================================================================
-- NOTIFICATIONS TABLE - Performance Indexes
-- =============================================================================

-- Composite index for user notifications
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at DESC);

-- Index for unread notifications
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read, created_at DESC) WHERE is_read = FALSE;

-- Index for notification type analysis
CREATE INDEX idx_notifications_type ON notifications(type, created_at DESC);

-- Index for notification cleanup
CREATE INDEX idx_notifications_cleanup ON notifications(is_read, created_at ASC) WHERE is_read = TRUE;

-- =============================================================================
-- SYSTEM_SETTINGS TABLE - Performance Indexes
-- =============================================================================

-- Index for public settings (for frontend)
CREATE INDEX idx_settings_public ON system_settings(is_public, setting_key) WHERE is_public = TRUE;

-- Index for setting type
CREATE INDEX idx_settings_type ON system_settings(setting_type, setting_key);

-- Covering index for settings cache
CREATE INDEX idx_settings_cache ON system_settings(setting_key, setting_value, is_public);

-- =============================================================================
-- FULL-TEXT INDEXES
-- =============================================================================

-- Full-text search for user information
ALTER TABLE users ADD FULLTEXT INDEX ft_users_search (username, email, full_name);

-- Full-text search for plans
ALTER TABLE plans ADD FULLTEXT INDEX ft_plans_search (name, description);

-- Full-text search for audit logs
ALTER TABLE audit_logs ADD FULLTEXT INDEX ft_audit_search (action, details);

-- =============================================================================
-- SPATIAL INDEXES (for future geo-location features)
-- =============================================================================

-- Note: Requires geometry columns to be added first
-- Placeholder for future implementation
-- ALTER TABLE controllers ADD COLUMN location POINT;
-- CREATE SPATIAL INDEX idx_controllers_location ON controllers(location);

-- =============================================================================
-- INDEX MAINTENANCE RECOMMENDATIONS
-- =============================================================================

-- Run these commands periodically (weekly/monthly) to maintain index performance:

-- ANALYZE TABLE users, controllers, plans, sessions, subscriptions, payments, vouchers, radius_accounting, audit_logs, notifications, system_settings;
-- OPTIMIZE TABLE users, controllers, plans, sessions, subscriptions, payments, vouchers, radius_accounting, audit_logs, notifications, system_settings;

-- Monitor index usage with:
-- SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'wifight_isp';
-- SELECT * FROM sys.schema_index_statistics WHERE table_schema = 'wifight_isp' ORDER BY rows_selected DESC;

-- Check index fragmentation:
-- SELECT table_name, index_name, data_free, table_rows
-- FROM information_schema.tables
-- WHERE table_schema = 'wifight_isp' AND data_free > 0;

SELECT 'Performance indexes created successfully!' AS status;
