<?php
/**
 * WiFight ISP System - Performance Monitor
 *
 * Monitors system performance and generates metrics
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../cache/CacheManager.php';

class PerformanceMonitor {
    private $db;
    private $logger;
    private $cache;
    private $startTime;
    private $startMemory;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
        $this->cache = new CacheManager();
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'server' => $this->getServerMetrics(),
            'application' => $this->getApplicationMetrics()
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics() {
        try {
            // Connection count
            $stmt = $this->db->query('SHOW STATUS LIKE "Threads_connected"');
            $threadsConnected = (int)$stmt->fetch(PDO::FETCH_ASSOC)['Value'];

            // Query statistics
            $stmt = $this->db->query('SHOW GLOBAL STATUS LIKE "Questions"');
            $questions = (int)$stmt->fetch(PDO::FETCH_ASSOC)['Value'];

            // Slow queries
            $stmt = $this->db->query('SHOW GLOBAL STATUS LIKE "Slow_queries"');
            $slowQueries = (int)$stmt->fetch(PDO::FETCH_ASSOC)['Value'];

            // Table sizes
            $stmt = $this->db->query('
                SELECT table_schema,
                       SUM(data_length + index_length) / 1024 / 1024 as size_mb
                FROM information_schema.tables
                WHERE table_schema = "wifight_isp"
                GROUP BY table_schema
            ');
            $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'connections' => $threadsConnected,
                'queries_total' => $questions,
                'slow_queries' => $slowQueries,
                'database_size_mb' => round((float)$dbSize['size_mb'], 2),
                'status' => 'connected'
            ];

        } catch (Exception $e) {
            $this->logger->error('Database metrics error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics() {
        try {
            return $this->cache->getStats();
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get server metrics
     */
    private function getServerMetrics() {
        $load = sys_getloadavg();

        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'memory_used' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'load_average' => [
                '1_min' => $load[0],
                '5_min' => $load[1],
                '15_min' => $load[2]
            ],
            'disk_free' => round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB',
            'disk_total' => round(disk_total_space('.') / 1024 / 1024 / 1024, 2) . ' GB'
        ];
    }

    /**
     * Get application metrics
     */
    private function getApplicationMetrics() {
        try {
            // Active users
            $stmt = $this->db->query('SELECT COUNT(*) as count FROM users WHERE status = "active"');
            $activeUsers = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Active sessions
            $stmt = $this->db->query('SELECT COUNT(*) as count FROM sessions WHERE status = "active"');
            $activeSessions = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Active subscriptions
            $stmt = $this->db->query('SELECT COUNT(*) as count FROM subscriptions WHERE status = "active"');
            $activeSubscriptions = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Today's revenue
            $stmt = $this->db->query('
                SELECT COALESCE(SUM(amount), 0) as revenue
                FROM payments
                WHERE status = "completed"
                AND DATE(created_at) = CURDATE()
            ');
            $todayRevenue = (float)$stmt->fetch(PDO::FETCH_ASSOC)['revenue'];

            return [
                'active_users' => $activeUsers,
                'active_sessions' => $activeSessions,
                'active_subscriptions' => $activeSubscriptions,
                'today_revenue' => $todayRevenue,
                'uptime' => $this->getUptime()
            ];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get application uptime
     */
    private function getUptime() {
        $uptimeFile = __DIR__ . '/../../../storage/uptime.txt';

        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());
        }

        $startTime = (int)file_get_contents($uptimeFile);
        $uptime = time() - $startTime;

        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }

    /**
     * Log slow query
     */
    public function logSlowQuery(string $query, float $executionTime) {
        if ($executionTime > 1.0) { // Log queries slower than 1 second
            $this->logger->warning('Slow query detected', [
                'execution_time' => $executionTime,
                'query' => substr($query, 0, 200)
            ]);
        }
    }

    /**
     * Record API request timing
     */
    public function recordRequestTiming(string $endpoint, float $duration) {
        $key = 'api:timing:' . md5($endpoint);
        $this->cache->increment($key . ':count');

        // Store last 10 request times
        $times = $this->cache->get($key . ':times') ?? [];
        array_unshift($times, $duration);
        $times = array_slice($times, 0, 10);
        $this->cache->set($key . ':times', $times, 3600);
    }

    /**
     * Get API performance stats
     */
    public function getAPIStats() {
        // This is a simplified version
        // In production, you'd use a proper time-series database or APM tool

        return [
            'total_requests' => $this->cache->get('api:total_requests') ?? 0,
            'average_response_time' => $this->cache->get('api:avg_response_time') ?? 0,
            'error_rate' => $this->cache->get('api:error_rate') ?? 0
        ];
    }

    /**
     * Health check
     */
    public function healthCheck() {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        // Database check
        try {
            $this->db->query('SELECT 1');
            $health['checks']['database'] = 'ok';
        } catch (Exception $e) {
            $health['checks']['database'] = 'failed';
            $health['status'] = 'unhealthy';
        }

        // Cache check
        try {
            $this->cache->set('health_check', true, 10);
            $health['checks']['cache'] = 'ok';
        } catch (Exception $e) {
            $health['checks']['cache'] = 'failed';
        }

        // Storage check
        $storageDir = __DIR__ . '/../../../storage/';
        $health['checks']['storage'] = is_writable($storageDir) ? 'ok' : 'failed';

        return $health;
    }

    /**
     * Get request execution stats
     */
    public function getExecutionStats() {
        return [
            'execution_time' => round((microtime(true) - $this->startTime) * 1000, 2) . ' ms',
            'memory_used' => round((memory_get_usage() - $this->startMemory) / 1024, 2) . ' KB',
            'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB'
        ];
    }
}
