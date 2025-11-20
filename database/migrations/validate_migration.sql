-- WiFight ISP System - Phase 2 Migration Validation Script
-- Purpose: Verify that migration 001 was applied correctly

USE wifight_isp;

-- =============================================================================
-- VALIDATION REPORT
-- =============================================================================

SELECT '========================================' as '';
SELECT 'Phase 2 Migration Validation Report' as 'REPORT';
SELECT '========================================' as '';

-- =============================================================================
-- 1. Table Count Verification
-- =============================================================================

SELECT '' as '';
SELECT '1. TABLE COUNT VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    'Expected: 15 tables (11 original + 4 new)' as Status;

SELECT
    COUNT(*) as total_tables,
    CASE
        WHEN COUNT(*) >= 15 THEN '✅ PASS'
        ELSE '❌ FAIL'
    END as validation_status
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_type = 'BASE TABLE';

-- List all tables
SELECT
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_type = 'BASE TABLE'
ORDER BY table_name;

-- =============================================================================
-- 2. New Tables Verification
-- =============================================================================

SELECT '' as '';
SELECT '2. NEW TABLES VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    table_name,
    CASE
        WHEN table_name IN (
            'controller_health_logs',
            'api_rate_limits',
            'session_bandwidth_history',
            'controller_config_cache'
        ) THEN '✅ EXISTS'
        ELSE '⚠️ MISSING'
    END as status
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_name IN (
    'controller_health_logs',
    'api_rate_limits',
    'session_bandwidth_history',
    'controller_config_cache'
)
UNION ALL
SELECT
    'controller_health_logs' as table_name,
    '❌ MISSING' as status
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = 'wifight_isp'
    AND table_name = 'controller_health_logs'
)
UNION ALL
SELECT
    'api_rate_limits' as table_name,
    '❌ MISSING' as status
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = 'wifight_isp'
    AND table_name = 'api_rate_limits'
)
UNION ALL
SELECT
    'session_bandwidth_history' as table_name,
    '❌ MISSING' as status
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = 'wifight_isp'
    AND table_name = 'session_bandwidth_history'
)
UNION ALL
SELECT
    'controller_config_cache' as table_name,
    '❌ MISSING' as status
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = 'wifight_isp'
    AND table_name = 'controller_config_cache'
);

-- =============================================================================
-- 3. New Columns Verification
-- =============================================================================

SELECT '' as '';
SELECT '3. NEW COLUMNS VERIFICATION' as 'SECTION';
SELECT '---' as '';

-- Controllers table new columns
SELECT
    'controllers' as table_name,
    column_name,
    column_type,
    '✅ EXISTS' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'controllers'
AND column_name IN (
    'max_connections',
    'current_connections',
    'response_time_ms',
    'last_error',
    'error_count',
    'health_score',
    'firmware_version',
    'uptime_seconds',
    'features'
)
ORDER BY column_name;

-- Sessions table new columns
SELECT
    'sessions' as table_name,
    column_name,
    column_type,
    '✅ EXISTS' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'sessions'
AND column_name IN (
    'packets_in',
    'packets_out',
    'nas_ip_address',
    'nas_port_id',
    'location',
    'device_type',
    'browser',
    'last_activity'
)
ORDER BY column_name;

-- Plans table new columns
SELECT
    'plans' as table_name,
    column_name,
    column_type,
    '✅ EXISTS' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'plans'
AND column_name IN (
    'bandwidth_limit_type',
    'burst_enabled',
    'burst_threshold',
    'burst_time',
    'controller_config'
)
ORDER BY column_name;

-- Subscriptions table new columns
SELECT
    'subscriptions' as table_name,
    column_name,
    column_type,
    '✅ EXISTS' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'subscriptions'
AND column_name IN (
    'max_mac_addresses',
    'registered_macs',
    'concurrent_sessions',
    'last_used_mac',
    'last_session_date'
)
ORDER BY column_name;

-- Audit logs table new columns
SELECT
    'audit_logs' as table_name,
    column_name,
    column_type,
    '✅ EXISTS' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'audit_logs'
AND column_name IN (
    'controller_id',
    'response_time_ms',
    'request_data',
    'response_data'
)
ORDER BY column_name;

-- =============================================================================
-- 4. Index Verification
-- =============================================================================

SELECT '' as '';
SELECT '4. INDEX VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    table_name,
    index_name,
    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns,
    CASE
        WHEN non_unique = 0 THEN 'UNIQUE'
        ELSE 'NON-UNIQUE'
    END as index_type,
    '✅ EXISTS' as status
FROM information_schema.statistics
WHERE table_schema = 'wifight_isp'
AND index_name LIKE 'idx_%'
ORDER BY table_name, index_name;

-- Count indexes per table
SELECT
    table_name,
    COUNT(DISTINCT index_name) as index_count
FROM information_schema.statistics
WHERE table_schema = 'wifight_isp'
AND table_name IN (
    'controllers',
    'sessions',
    'plans',
    'subscriptions',
    'audit_logs'
)
GROUP BY table_name
ORDER BY table_name;

-- =============================================================================
-- 5. View Verification
-- =============================================================================

SELECT '' as '';
SELECT '5. VIEW VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    table_name as view_name,
    '✅ EXISTS' as status
FROM information_schema.views
WHERE table_schema = 'wifight_isp'
AND table_name IN (
    'controller_dashboard_view',
    'session_analytics_view',
    'bandwidth_usage_view',
    'active_sessions_view',
    'daily_revenue_view',
    'user_stats_view'
)
ORDER BY table_name;

-- Count views
SELECT
    COUNT(*) as total_views,
    CASE
        WHEN COUNT(*) >= 6 THEN '✅ PASS (Expected: 6)'
        ELSE '⚠️ WARNING'
    END as validation_status
FROM information_schema.views
WHERE table_schema = 'wifight_isp';

-- =============================================================================
-- 6. Stored Procedure Verification
-- =============================================================================

SELECT '' as '';
SELECT '6. STORED PROCEDURE VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    routine_name,
    routine_type,
    '✅ EXISTS' as status
FROM information_schema.routines
WHERE routine_schema = 'wifight_isp'
AND routine_type = 'PROCEDURE'
AND routine_name IN (
    'update_controller_health',
    'check_api_rate_limit',
    'record_session_bandwidth',
    'cleanup_expired_sessions',
    'expire_old_vouchers'
)
ORDER BY routine_name;

-- Count procedures
SELECT
    COUNT(*) as total_procedures,
    CASE
        WHEN COUNT(*) >= 5 THEN '✅ PASS (Expected: 5)'
        ELSE '⚠️ WARNING'
    END as validation_status
FROM information_schema.routines
WHERE routine_schema = 'wifight_isp'
AND routine_type = 'PROCEDURE';

-- =============================================================================
-- 7. Trigger Verification
-- =============================================================================

SELECT '' as '';
SELECT '7. TRIGGER VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    trigger_name,
    event_manipulation as event_type,
    event_object_table as table_name,
    action_timing,
    '✅ EXISTS' as status
FROM information_schema.triggers
WHERE trigger_schema = 'wifight_isp'
ORDER BY trigger_name;

-- Count triggers
SELECT
    COUNT(*) as total_triggers,
    CASE
        WHEN COUNT(*) >= 3 THEN '✅ PASS (Expected: 3)'
        ELSE '⚠️ WARNING'
    END as validation_status
FROM information_schema.triggers
WHERE trigger_schema = 'wifight_isp';

-- =============================================================================
-- 8. Event Scheduler Verification
-- =============================================================================

SELECT '' as '';
SELECT '8. EVENT SCHEDULER VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    event_name,
    status,
    interval_value,
    interval_field,
    '✅ EXISTS' as validation_status
FROM information_schema.events
WHERE event_schema = 'wifight_isp'
ORDER BY event_name;

-- Check if event scheduler is enabled
SELECT
    @@event_scheduler as event_scheduler_status,
    CASE
        WHEN @@event_scheduler = 'ON' THEN '✅ ENABLED'
        ELSE '⚠️ DISABLED - Run: SET GLOBAL event_scheduler = ON;'
    END as status;

-- =============================================================================
-- 9. Foreign Key Verification
-- =============================================================================

SELECT '' as '';
SELECT '9. FOREIGN KEY VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    constraint_name,
    table_name,
    referenced_table_name,
    '✅ EXISTS' as status
FROM information_schema.key_column_usage
WHERE table_schema = 'wifight_isp'
AND referenced_table_name IS NOT NULL
AND table_name IN (
    'controller_health_logs',
    'api_rate_limits',
    'session_bandwidth_history',
    'controller_config_cache'
)
ORDER BY table_name, constraint_name;

-- Count foreign keys
SELECT
    COUNT(*) as total_foreign_keys
FROM information_schema.key_column_usage
WHERE table_schema = 'wifight_isp'
AND referenced_table_name IS NOT NULL;

-- =============================================================================
-- 10. Data Type Verification
-- =============================================================================

SELECT '' as '';
SELECT '10. JSON COLUMN VERIFICATION' as 'SECTION';
SELECT '---' as '';

SELECT
    table_name,
    column_name,
    data_type,
    '✅ JSON TYPE' as status
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND data_type = 'json'
ORDER BY table_name, column_name;

-- =============================================================================
-- 11. Summary Report
-- =============================================================================

SELECT '' as '';
SELECT '========================================' as '';
SELECT '11. SUMMARY REPORT' as 'SECTION';
SELECT '========================================' as '';

SELECT
    'Total Tables' as metric,
    COUNT(*) as count,
    '15 expected' as expected
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_type = 'BASE TABLE'

UNION ALL

SELECT
    'Total Views',
    COUNT(*),
    '6 expected'
FROM information_schema.views
WHERE table_schema = 'wifight_isp'

UNION ALL

SELECT
    'Total Procedures',
    COUNT(*),
    '5 expected'
FROM information_schema.routines
WHERE routine_schema = 'wifight_isp'
AND routine_type = 'PROCEDURE'

UNION ALL

SELECT
    'Total Triggers',
    COUNT(*),
    '3 expected'
FROM information_schema.triggers
WHERE trigger_schema = 'wifight_isp'

UNION ALL

SELECT
    'Total Events',
    COUNT(*),
    '1 expected'
FROM information_schema.events
WHERE event_schema = 'wifight_isp'

UNION ALL

SELECT
    'Total Indexes',
    COUNT(DISTINCT index_name),
    '40+ expected'
FROM information_schema.statistics
WHERE table_schema = 'wifight_isp'

UNION ALL

SELECT
    'Total Foreign Keys',
    COUNT(*),
    '20+ expected'
FROM information_schema.key_column_usage
WHERE table_schema = 'wifight_isp'
AND referenced_table_name IS NOT NULL;

-- =============================================================================
-- 12. Performance Test Queries
-- =============================================================================

SELECT '' as '';
SELECT '12. PERFORMANCE TEST QUERIES' as 'SECTION';
SELECT '---' as '';

-- Test composite indexes
EXPLAIN SELECT * FROM sessions WHERE controller_id = 1 AND status = 'active';
EXPLAIN SELECT * FROM sessions WHERE user_id = 1 AND status = 'active';
EXPLAIN SELECT * FROM controllers WHERE type = 'mikrotik' AND status = 'online';

-- Test views
SELECT COUNT(*) as controllers FROM controller_dashboard_view;
SELECT COUNT(*) as analytics FROM session_analytics_view;
SELECT COUNT(*) as bandwidth FROM bandwidth_usage_view;

-- =============================================================================
-- 13. Final Validation Status
-- =============================================================================

SELECT '' as '';
SELECT '========================================' as '';
SELECT 'FINAL VALIDATION STATUS' as 'REPORT';
SELECT '========================================' as '';

SELECT
    CASE
        WHEN
            (SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = 'wifight_isp' AND table_type = 'BASE TABLE') >= 15
            AND
            (SELECT COUNT(*) FROM information_schema.views
             WHERE table_schema = 'wifight_isp') >= 6
            AND
            (SELECT COUNT(*) FROM information_schema.routines
             WHERE routine_schema = 'wifight_isp' AND routine_type = 'PROCEDURE') >= 5
            AND
            (SELECT COUNT(*) FROM information_schema.triggers
             WHERE trigger_schema = 'wifight_isp') >= 3
        THEN '✅ MIGRATION SUCCESSFUL - All components verified'
        ELSE '❌ MIGRATION INCOMPLETE - Please review errors above'
    END as final_status;

SELECT '========================================' as '';
SELECT 'Validation Complete' as '';
SELECT '========================================' as '';
