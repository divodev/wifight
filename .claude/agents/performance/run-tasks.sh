#!/bin/bash

echo "==================================="
echo "WiFight Performance Agent"
echo "==================================="

CONFIG_FILE=".claude/agents/performance/agent-config.json"

# Task 1: Redis Caching Layer
echo "Task 1: Implementing Redis caching..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-001" \
  --context="backend/config/database.php" \
  --output="backend/services/cache/" \
  --prompt="Create a comprehensive Redis caching layer for WiFight including:

1. RedisCache.php - Main cache class with methods:
   - set(key, value, ttl)
   - get(key)
   - delete(key)
   - flush()
   - exists(key)
   - increment(key)
   - tags support for grouped cache invalidation

2. CacheManager.php - High-level cache manager:
   - Cache controller sessions
   - Cache user sessions
   - Cache plan data
   - Cache API responses
   - Automatic cache invalidation on updates

3. Configuration in config/cache.php:
   - Redis connection settings
   - Cache prefixes
   - TTL defaults by cache type
   - Cache serialization options

Use predis/predis library. Implement cache stampede prevention with locks. Add cache warming for frequently accessed data."

# Task 2: Database Query Optimization
echo "Task 2: Optimizing database queries..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-002" \
  --context="database/schema/complete-schema.sql" \
  --output="database/optimization/" \
  --prompt="Analyze and optimize WiFight database for high performance:

1. Create optimized-indexes.sql:
   - Add composite indexes for common query patterns
   - Create covering indexes for frequently selected columns
   - Add indexes for JOIN operations
   - Full-text indexes for search functionality

2. Create query-optimization.sql:
   - Optimize slow queries identified in the system
   - Add database views for complex reports
   - Create materialized views for analytics

3. Create partitioning-strategy.sql:
   - Partition sessions table by month
   - Partition radius_accounting by month
   - Partition audit_logs by month

4. Create maintenance-procedures.sql:
   - ANALYZE TABLE procedures
   - Index optimization procedures
   - Partition maintenance procedures

Include detailed comments explaining each optimization."

# Task 3: PHP OpCache Configuration
echo "Task 3: Configuring PHP OpCache..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-003" \
  --output="docker/php/php.ini" \
  --prompt="Create optimized PHP configuration for production ISP system:

1. OpCache settings for maximum performance
2. APCu configuration for user data caching
3. Memory limits appropriate for high traffic
4. File upload limits for admin operations
5. Session configuration with Redis
6. Realpath cache optimization
7. JIT compilation (PHP 8.0+)

Include detailed comments for each setting and recommended values for 10,000+ concurrent users."

# Task 4: Connection Pooling
echo "Task 4: Implementing connection pooling..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-004" \
  --context="backend/config/database.php" \
  --output="backend/config/database.php" \
  --prompt="Enhance database.php with connection pooling for high concurrency:

1. Implement persistent connections
2. Add connection pool manager
3. Configure max connections
4. Add connection health checks
5. Implement connection retry logic
6. Add query timeout settings
7. Configure transaction isolation levels
8. Add connection statistics tracking

Support both MySQL and Redis connection pooling."

# Task 5: Load Balancer Configuration
echo "Task 5: Creating load balancer configuration..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-005" \
  --output="docker/nginx/" \
  --prompt="Create Nginx load balancer configuration for WiFight:

1. load-balancer.conf:
   - Upstream backend servers
   - Load balancing algorithm (least connections)
   - Health checks
   - Session persistence (ip_hash for captive portal)
   - Failover configuration
   - Connection limits

2. caching.conf:
   - Static asset caching
   - API response caching
   - Cache purging endpoints
   - Cache bypass rules

3. compression.conf:
   - Gzip compression for API responses
   - Brotli compression (if available)
   - MIME types to compress

4. ssl.conf:
   - SSL termination
   - HTTP/2 configuration
   - SSL session caching

Include performance tuning parameters for 10,000+ concurrent connections."

# Task 6: API Response Caching
echo "Task 6: Implementing API response caching..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-006" \
  --context="backend/services/cache/RedisCache.php" \
  --output="backend/middleware/CacheMiddleware.php" \
  --prompt="Create intelligent API response caching middleware:

1. CacheMiddleware.php:
   - Cache GET requests automatically
   - Skip caching for authenticated endpoints (configurable)
   - ETag support for cache validation
   - Conditional requests (If-None-Match)
   - Cache-Control header management
   - Vary header support
   - Cache key generation from request parameters

2. Cache invalidation strategies:
   - Invalidate on POST/PUT/DELETE to related resources
   - Tag-based invalidation
   - Time-based expiration
   - Manual purge endpoints

3. Cacheable endpoints configuration:
   - List cacheable endpoints with TTLs
   - Cache warming on application start

Include middleware integration instructions."

# Task 7: Performance Monitoring
echo "Task 7: Setting up performance monitoring..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-007" \
  --output="backend/services/monitoring/" \
  --prompt="Create comprehensive performance monitoring system:

1. PerformanceMonitor.php:
   - Track API response times
   - Monitor database query times
   - Measure cache hit/miss ratios
   - Track memory usage
   - Monitor CPU usage
   - Track concurrent connections

2. MetricsCollector.php:
   - Collect metrics in real-time
   - Store metrics in time-series format
   - Aggregate metrics (1min, 5min, 1hour)

3. AlertManager.php:
   - Define performance thresholds
   - Send alerts on threshold violations
   - Alert channels (email, Slack, webhook)

4. Dashboard endpoint:
   - GET /api/v1/monitoring/metrics
   - Real-time performance data
   - Historical trends

Integrate with Prometheus format for exporters."

# Task 8: Load Testing Scripts
echo "Task 8: Creating load testing scripts..."
claude-code task \
  --agent="performance-agent" \
  --config="$CONFIG_FILE" \
  --task="perf-008" \
  --output="tests/performance/" \
  --prompt="Create comprehensive load testing suite:

1. apache-bench-tests.sh:
   - Test API endpoints with ab
   - Various concurrency levels
   - Generate performance reports

2. jmeter-test-plan.jmx:
   - Full user journey testing
   - Login → Browse plans → Subscribe → Session
   - Ramp-up scenarios
   - Think time simulation

3. locust-load-test.py:
   - Distributed load testing
   - Real user behavior simulation
   - Performance metrics collection

4. stress-test-suite.sh:
   - Gradual load increase
   - Peak load testing
   - Sustained load testing
   - Failure scenario testing

Include documentation on interpreting results and performance baselines."

echo "==================================="
echo "Performance Agent tasks completed!"
echo "==================================="