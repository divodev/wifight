# WiFight ISP System - Database Quick Reference Guide

**Phase 2: Controller Integration**
**Version:** 1.1
**Last Updated:** 2025-11-18

---

## Table of Contents
1. [Migration Commands](#migration-commands)
2. [New Tables Reference](#new-tables-reference)
3. [Stored Procedures Usage](#stored-procedures-usage)
4. [Common Queries](#common-queries)
5. [Performance Tips](#performance-tips)
6. [Troubleshooting](#troubleshooting)

---

## Migration Commands

### Apply Migration
```bash
# Backup first!
mysqldump -u root -p wifight_isp > backup_$(date +%Y%m%d_%H%M%S).sql

# Apply migration
mysql -u root -p wifight_isp < database/migrations/001_phase2_controller_optimizations.sql

# Validate migration
mysql -u root -p wifight_isp < database/migrations/validate_migration.sql
```

### Rollback (if needed)
```bash
# Restore from backup
mysql -u root -p wifight_isp < backup_YYYYMMDD_HHMMSS.sql
```

---

## New Tables Reference

### 1. controller_health_logs
**Purpose:** Track controller performance over time

**Key Fields:**
- `controller_id` - Which controller
- `status` - online, offline, degraded, error
- `response_time_ms` - API response time
- `active_sessions` - Current session count
- `check_time` - When this was recorded

**Usage:**
```sql
-- Get last 24 hours of health data
SELECT * FROM controller_health_logs
WHERE controller_id = 1
AND check_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY check_time DESC;

-- Average response time per hour
SELECT
    DATE_FORMAT(check_time, '%Y-%m-%d %H:00') as hour,
    AVG(response_time_ms) as avg_response_time,
    COUNT(*) as checks
FROM controller_health_logs
WHERE controller_id = 1
GROUP BY hour;
```

---

### 2. api_rate_limits
**Purpose:** Prevent API throttling by vendors

**Key Fields:**
- `controller_id` - Which controller
- `endpoint` - API endpoint (e.g., '/clients', '/sessions')
- `request_count` - Requests in current window
- `max_requests` - Limit per window
- `blocked_until` - When block expires (if rate limited)

**Usage:**
```sql
-- Check if endpoint is rate limited
SELECT
    blocked_until,
    CASE
        WHEN blocked_until > NOW() THEN 'BLOCKED'
        ELSE 'AVAILABLE'
    END as status
FROM api_rate_limits
WHERE controller_id = 1
AND endpoint = '/api/clients'
ORDER BY window_start DESC
LIMIT 1;

-- Get rate limit stats
SELECT
    endpoint,
    SUM(request_count) as total_requests,
    COUNT(*) as windows
FROM api_rate_limits
WHERE controller_id = 1
AND window_start > DATE_SUB(NOW(), INTERVAL 1 DAY)
GROUP BY endpoint;
```

---

### 3. session_bandwidth_history
**Purpose:** Time-series bandwidth data for analytics

**Key Fields:**
- `session_id` - Which session
- `recorded_at` - Timestamp
- `bytes_in/bytes_out` - Data transfer
- `download_speed_mbps/upload_speed_mbps` - Instantaneous speeds

**Usage:**
```sql
-- Get bandwidth history for a session
SELECT
    recorded_at,
    bytes_in,
    bytes_out,
    download_speed_mbps,
    upload_speed_mbps
FROM session_bandwidth_history
WHERE session_id = 123
ORDER BY recorded_at DESC
LIMIT 100;

-- Calculate average speeds
SELECT
    AVG(download_speed_mbps) as avg_download,
    AVG(upload_speed_mbps) as avg_upload,
    MAX(download_speed_mbps) as peak_download,
    MAX(upload_speed_mbps) as peak_upload
FROM session_bandwidth_history
WHERE session_id = 123;
```

---

### 4. controller_config_cache
**Purpose:** Cache controller configurations to reduce API calls

**Key Fields:**
- `controller_id` - Which controller
- `config_type` - Type (e.g., 'radius_profiles', 'ip_pools')
- `config_key` - Specific config name
- `config_value` - JSON or text value
- `expires_at` - Cache expiration

**Usage:**
```sql
-- Get cached config (check expiration)
SELECT config_value
FROM controller_config_cache
WHERE controller_id = 1
AND config_type = 'radius_profiles'
AND config_key = 'default'
AND (expires_at IS NULL OR expires_at > NOW())
AND is_valid = TRUE
LIMIT 1;

-- Update cache
INSERT INTO controller_config_cache (
    controller_id,
    config_type,
    config_key,
    config_value,
    expires_at
) VALUES (
    1,
    'radius_profiles',
    'default',
    '{"profile_id": "5", "bandwidth": "10M/10M"}',
    DATE_ADD(NOW(), INTERVAL 5 MINUTE)
) ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    cached_at = NOW(),
    expires_at = VALUES(expires_at),
    is_valid = TRUE;

-- Invalidate cache
UPDATE controller_config_cache
SET is_valid = FALSE
WHERE controller_id = 1
AND config_type = 'radius_profiles';
```

---

## Stored Procedures Usage

### 1. update_controller_health()
**Purpose:** Update controller health with automatic scoring

```php
// PHP Usage
$stmt = $pdo->prepare("CALL update_controller_health(?, ?, ?, ?)");
$stmt->execute([
    $controller_id,
    'online',       // status
    150,            // response_time_ms
    25              // active_sessions
]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Health Score: " . $result['health_score'];
```

```sql
-- SQL Usage
CALL update_controller_health(1, 'online', 150, 25);

-- Check result
SELECT health_score, error_count, status
FROM controllers
WHERE id = 1;
```

---

### 2. check_api_rate_limit()
**Purpose:** Check if API call is allowed

```php
// PHP Usage
$stmt = $pdo->prepare("CALL check_api_rate_limit(?, ?, @allowed)");
$stmt->execute([$controller_id, '/api/clients']);

// Get output parameter
$result = $pdo->query("SELECT @allowed as allowed")->fetch();

if ($result['allowed']) {
    // Make API call
} else {
    // Rate limited - wait or use cached data
}
```

```sql
-- SQL Usage
CALL check_api_rate_limit(1, '/api/clients', @allowed);
SELECT @allowed;

-- If 0 (FALSE), API call is blocked
-- If 1 (TRUE), API call is allowed
```

---

### 3. record_session_bandwidth()
**Purpose:** Update session bandwidth with history

```php
// PHP Usage - Called from controller polling
$stmt = $pdo->prepare("CALL record_session_bandwidth(?, ?, ?, ?, ?)");
$stmt->execute([
    $session_id,
    $bytes_in_delta,    // New bytes since last check
    $bytes_out_delta,
    $packets_in_delta,
    $packets_out_delta
]);
```

```sql
-- SQL Usage
CALL record_session_bandwidth(
    123,            -- session_id
    104857600,      -- 100MB in
    52428800,       -- 50MB out
    100000,         -- packets in
    50000           -- packets out
);

-- Check updated session
SELECT bytes_in, bytes_out, last_activity
FROM sessions
WHERE id = 123;
```

---

## Common Queries

### Controller Management

```sql
-- Get all online controllers with load
SELECT
    id,
    name,
    type,
    current_connections,
    max_connections,
    ROUND((current_connections / max_connections * 100), 2) as load_percent,
    health_score,
    response_time_ms
FROM controllers
WHERE status = 'online'
ORDER BY load_percent ASC;

-- Find best controller for new session (lowest load, best health)
SELECT id, name, type
FROM controllers
WHERE status = 'online'
AND current_connections < max_connections * 0.9
ORDER BY
    health_score DESC,
    (current_connections / max_connections) ASC
LIMIT 1;

-- Get controller statistics
SELECT * FROM controller_dashboard_view
WHERE id = 1;
```

---

### Session Management

```sql
-- Get active sessions for a controller
SELECT
    s.id,
    s.mac_address,
    s.ip_address,
    u.username,
    p.name as plan_name,
    TIMESTAMPDIFF(SECOND, s.start_time, NOW()) as uptime_seconds,
    s.bytes_in + s.bytes_out as total_bytes
FROM sessions s
JOIN users u ON s.user_id = u.id
LEFT JOIN plans p ON s.plan_id = p.id
WHERE s.controller_id = 1
AND s.status = 'active'
ORDER BY s.start_time DESC;

-- Get user's active session
SELECT * FROM sessions
WHERE user_id = 123
AND status = 'active'
LIMIT 1;

-- Find sessions over data limit
SELECT
    s.id,
    u.username,
    p.name as plan_name,
    p.data_limit,
    (s.bytes_in + s.bytes_out) as used,
    ROUND(((s.bytes_in + s.bytes_out) / p.data_limit * 100), 2) as usage_percent
FROM sessions s
JOIN users u ON s.user_id = u.id
JOIN plans p ON s.plan_id = p.id
WHERE s.status = 'active'
AND p.data_limit IS NOT NULL
AND (s.bytes_in + s.bytes_out) > p.data_limit
ORDER BY usage_percent DESC;
```

---

### Analytics Queries

```sql
-- Daily session statistics
SELECT * FROM session_analytics_view
WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY session_date DESC, controller_type;

-- User bandwidth usage
SELECT * FROM bandwidth_usage_view
WHERE user_id = 123;

-- Top bandwidth consumers (last 30 days)
SELECT
    u.username,
    SUM(s.bytes_in + s.bytes_out) as total_bandwidth,
    COUNT(DISTINCT s.id) as session_count,
    ROUND(AVG(s.duration), 0) as avg_duration_seconds
FROM sessions s
JOIN users u ON s.user_id = u.id
WHERE s.start_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id
ORDER BY total_bandwidth DESC
LIMIT 20;

-- Controller performance comparison
SELECT
    c.name,
    c.type,
    COUNT(DISTINCT s.id) as total_sessions,
    AVG(h.response_time_ms) as avg_response_time,
    AVG(h.active_sessions) as avg_concurrent_sessions,
    SUM(CASE WHEN h.status = 'offline' THEN 1 ELSE 0 END) as downtime_incidents
FROM controllers c
LEFT JOIN controller_health_logs h ON c.id = h.controller_id
    AND h.check_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
LEFT JOIN sessions s ON c.id = s.controller_id
    AND s.start_time > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY c.id
ORDER BY c.name;
```

---

## Performance Tips

### 1. Use Prepared Statements
```php
// Good - Uses prepared statement
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE user_id = ? AND status = ?");
$stmt->execute([$user_id, 'active']);

// Bad - SQL injection risk, no query cache
$result = $pdo->query("SELECT * FROM sessions WHERE user_id = $user_id AND status = 'active'");
```

### 2. Use Indexes Efficiently
```sql
-- Good - Uses idx_controller_status composite index
SELECT * FROM sessions
WHERE controller_id = 1 AND status = 'active';

-- Bad - Full table scan
SELECT * FROM sessions
WHERE status = 'active' OR controller_id = 1;

-- Check if query uses indexes
EXPLAIN SELECT * FROM sessions
WHERE controller_id = 1 AND status = 'active';
```

### 3. Use Views for Complex Queries
```sql
-- Good - Pre-optimized view
SELECT * FROM controller_dashboard_view WHERE id = 1;

-- Bad - Manual joins every time
SELECT c.*, COUNT(s.id) as active_sessions, ...
FROM controllers c
LEFT JOIN sessions s ON ...
-- (complex query)
```

### 4. Batch Updates
```php
// Good - Single transaction for multiple updates
$pdo->beginTransaction();
try {
    foreach ($sessions as $session) {
        $stmt->execute([$session['bytes_in'], $session['id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

// Bad - Individual transactions
foreach ($sessions as $session) {
    $stmt->execute([$session['bytes_in'], $session['id']]);
}
```

### 5. Use Caching
```php
// Check cache first
$cache_key = "controller_config:{$controller_id}:radius_profiles";
$config = $redis->get($cache_key);

if (!$config) {
    // Not in Redis, check database cache
    $stmt = $pdo->prepare("
        SELECT config_value FROM controller_config_cache
        WHERE controller_id = ? AND config_type = ?
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$controller_id, 'radius_profiles']);
    $config = $stmt->fetchColumn();

    // Cache in Redis for 60 seconds
    $redis->setex($cache_key, 60, $config);
}
```

---

## Troubleshooting

### Migration Issues

**Problem:** Migration fails with "Duplicate column" error

**Solution:**
```sql
-- Check if columns already exist
SELECT column_name
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'controllers'
AND column_name = 'health_score';

-- If exists, skip that ALTER statement
```

---

**Problem:** Foreign key constraint fails

**Solution:**
```sql
-- Check if referenced table exists
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_name = 'controllers';

-- Check if referenced column exists
SELECT column_name
FROM information_schema.columns
WHERE table_schema = 'wifight_isp'
AND table_name = 'controllers'
AND column_name = 'id';
```

---

**Problem:** Stored procedure syntax error

**Solution:**
```sql
-- Check MySQL version (needs 5.7+)
SELECT VERSION();

-- Check delimiter setting
DELIMITER //
-- procedure definition
//
DELIMITER ;
```

---

### Performance Issues

**Problem:** Slow session queries

**Solution:**
```sql
-- Check if indexes are being used
EXPLAIN SELECT * FROM sessions
WHERE controller_id = 1 AND status = 'active';

-- Should show "Using index" or "Using where; Using index"
-- If "Using filesort" or "Using temporary", index not optimal

-- Check index statistics
SHOW INDEX FROM sessions;

-- Rebuild indexes if needed
ANALYZE TABLE sessions;
```

---

**Problem:** High database load

**Solution:**
```sql
-- Find slow queries
SELECT
    query_time,
    lock_time,
    rows_examined,
    rows_sent,
    sql_text
FROM mysql.slow_log
ORDER BY query_time DESC
LIMIT 10;

-- Check current connections
SHOW PROCESSLIST;

-- Check table sizes
SELECT
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
ORDER BY (data_length + index_length) DESC;
```

---

**Problem:** bandwidth_history table growing too large

**Solution:**
```sql
-- Check table size
SELECT
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'wifight_isp'
AND table_name = 'session_bandwidth_history';

-- Delete old records (older than 90 days)
DELETE FROM session_bandwidth_history
WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
LIMIT 10000;

-- Run multiple times until done

-- Or use event scheduler (already set up)
SELECT * FROM information_schema.events
WHERE event_schema = 'wifight_isp'
AND event_name = 'cleanup_old_health_logs';
```

---

### Controller Health Issues

**Problem:** Controller health score always 0

**Solution:**
```sql
-- Check error count
SELECT id, name, error_count, health_score, last_error
FROM controllers;

-- Reset error count manually
UPDATE controllers
SET error_count = 0,
    health_score = 100.00
WHERE id = 1;

-- Or use procedure to update properly
CALL update_controller_health(1, 'online', 100, 10);
```

---

**Problem:** Rate limiting blocking all requests

**Solution:**
```sql
-- Check rate limit status
SELECT *
FROM api_rate_limits
WHERE controller_id = 1
AND blocked_until > NOW();

-- Clear rate limit blocks
UPDATE api_rate_limits
SET blocked_until = NULL,
    request_count = 0
WHERE controller_id = 1;

-- Or delete old rate limit records
DELETE FROM api_rate_limits
WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

## Quick Commands Cheat Sheet

```bash
# Backup database
mysqldump -u root -p wifight_isp > backup.sql

# Backup single table
mysqldump -u root -p wifight_isp sessions > sessions_backup.sql

# Restore database
mysql -u root -p wifight_isp < backup.sql

# Import migration
mysql -u root -p wifight_isp < database/migrations/001_phase2_controller_optimizations.sql

# Run validation
mysql -u root -p wifight_isp < database/migrations/validate_migration.sql

# Check table structure
mysql -u root -p wifight_isp -e "DESCRIBE sessions;"

# Check indexes
mysql -u root -p wifight_isp -e "SHOW INDEX FROM sessions;"

# Check database size
mysql -u root -p wifight_isp -e "
    SELECT
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
    FROM information_schema.tables
    WHERE table_schema = 'wifight_isp'
    ORDER BY size_mb DESC;
"
```

---

## Need Help?

- **Database Schema:** See `database/schema.sql`
- **Full Analysis:** See `database/PHASE2_DATABASE_ANALYSIS.md`
- **Migration File:** See `database/migrations/001_phase2_controller_optimizations.sql`
- **Validation:** Run `database/migrations/validate_migration.sql`
- **Phase 2 Plan:** See `.claude/plans/Phase2-Controllers.md`

---

**Last Updated:** 2025-11-18
**Database Agent:** Available in `.claude/agents/`
