<?php
/**
 * WiFight ISP System - Health Check Endpoint
 *
 * GET /api/v1/health
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$response = new Response();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response->error('Method not allowed', 405);
}

try {
    // Test database connection
    $db = new Database();
    $dbConnected = $db->testConnection();

    // Get system stats
    $stats = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => getenv('APP_VERSION') ?: '1.0.0',
        'checks' => [
            'database' => $dbConnected ? 'ok' : 'error',
            'storage' => is_writable(__DIR__ . '/../../../storage') ? 'ok' : 'error'
        ],
        'uptime' => [
            'server' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
        ]
    ];

    // Overall health status
    $allHealthy = !in_array('error', $stats['checks']);

    if ($allHealthy) {
        $response->success($stats, 'System is healthy');
    } else {
        $response->error('System health check failed', 503, $stats);
    }

} catch (Exception $e) {
    $response->serverError('Health check failed: ' . $e->getMessage());
}
