<?php
/**
 * WiFight ISP System - Performance API
 *
 * System performance and monitoring endpoints
 */

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../services/monitoring/PerformanceMonitor.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

$auth = new Auth();
$monitor = new PerformanceMonitor();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$action = isset($parts[3]) ? $parts[3] : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'metrics') {
                // GET /api/v1/performance/metrics - Get system metrics
                handleGetMetrics();
            } elseif ($action === 'health') {
                // GET /api/v1/performance/health - Health check
                handleHealthCheck();
            } elseif ($action === 'cache') {
                // GET /api/v1/performance/cache - Cache stats
                handleCacheStats();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'POST':
            if ($action === 'cache' && isset($parts[4]) && $parts[4] === 'clear') {
                // POST /api/v1/performance/cache/clear - Clear cache
                handleClearCache();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Internal server error', 500);
}

function handleGetMetrics() {
    global $auth, $monitor;

    $user = $auth->requireRole(['admin']);
    $metrics = $monitor->getSystemMetrics();

    Response::success('System metrics retrieved', $metrics);
}

function handleHealthCheck() {
    global $monitor;

    $health = $monitor->healthCheck();
    $statusCode = $health['status'] === 'healthy' ? 200 : 503;

    http_response_code($statusCode);
    Response::success('Health check completed', $health);
}

function handleCacheStats() {
    global $auth, $monitor;

    $user = $auth->requireRole(['admin']);
    $stats = $monitor->getCacheMetrics();

    Response::success('Cache statistics retrieved', $stats);
}

function handleClearCache() {
    global $auth;

    $user = $auth->requireRole(['admin']);

    require_once __DIR__ . '/../../services/cache/CacheManager.php';
    $cache = new CacheManager();
    $cache->flush();

    Response::success('Cache cleared successfully');
}
