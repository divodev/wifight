<?php
/**
 * WiFight ISP System - RADIUS Management API
 *
 * Endpoints for managing RADIUS server
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/RateLimit.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

// Apply security headers
$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

// Apply rate limiting
$rateLimit = new RateLimit();
$rateLimit->middleware('api');

// Initialize utilities
$db = Database::getInstance()->getConnection();
$auth = new Auth();
$logger = new Logger();

// Get request method and parse route
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));

// Extract action
$action = isset($parts[3]) ? $parts[3] : null;

// Log request
$logger->logRequest($method, $requestUri);

try {
    // Route handlers
    switch ($method) {
        case 'GET':
            if ($action === 'status') {
                // GET /api/v1/radius/status - Get RADIUS server status
                handleGetStatus();
            } elseif ($action === 'sessions') {
                // GET /api/v1/radius/sessions - Get active RADIUS sessions
                handleGetSessions();
            } elseif ($action === 'stats') {
                // GET /api/v1/radius/stats - Get RADIUS statistics
                handleGetStats();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'POST':
            if ($action === 'disconnect') {
                // POST /api/v1/radius/disconnect - Disconnect user session
                handleDisconnect();
            } elseif ($action === 'coa') {
                // POST /api/v1/radius/coa - Send Change of Authorization
                handleCoA();
            } elseif ($action === 'test-auth') {
                // POST /api/v1/radius/test-auth - Test authentication
                handleTestAuth();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    $logger->error('RADIUS API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

// =============================================================================
// HANDLER FUNCTIONS
// =============================================================================

/**
 * Get RADIUS server status
 */
function handleGetStatus() {
    global $auth, $logger;

    // Only admin can check RADIUS status
    $user = $auth->requireRole(['admin']);

    $status = [
        'server' => 'unknown',
        'message' => 'RADIUS status check not fully implemented',
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Try to check if RADIUS process is running (Linux only)
    if (PHP_OS_FAMILY === 'Linux') {
        $output = [];
        $returnVar = 0;
        exec('systemctl is-active freeradius 2>/dev/null', $output, $returnVar);

        if ($returnVar === 0 && isset($output[0]) && $output[0] === 'active') {
            $status['server'] = 'running';
            $status['message'] = 'RADIUS server is active';
        } else {
            $status['server'] = 'stopped';
            $status['message'] = 'RADIUS server is not running';
        }
    }

    $logger->info('RADIUS status checked', ['user_id' => $user['user_id'], 'status' => $status['server']]);

    Response::success('RADIUS status retrieved', $status);
}

/**
 * Get active RADIUS sessions
 */
function handleGetSessions() {
    global $auth, $db;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Get active sessions from radius_accounting
    $stmt = $db->prepare('
        SELECT ra.id, ra.user_id, u.username, ra.session_id, ra.nas_ip_address,
               ra.calling_station_id, ra.bytes_in, ra.bytes_out, ra.event_timestamp,
               TIMESTAMPDIFF(SECOND, ra.event_timestamp, NOW()) as duration_seconds
        FROM radius_accounting ra
        JOIN users u ON ra.user_id = u.id
        WHERE ra.event_type = ?
        ORDER BY ra.event_timestamp DESC
        LIMIT 100
    ');
    $stmt->execute(['Start']);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($sessions as &$session) {
        $session['id'] = (int)$session['id'];
        $session['user_id'] = (int)$session['user_id'];
        $session['bytes_in'] = (int)$session['bytes_in'];
        $session['bytes_out'] = (int)$session['bytes_out'];
        $session['duration_seconds'] = (int)$session['duration_seconds'];
        $session['total_mb'] = round(((int)$session['bytes_in'] + (int)$session['bytes_out']) / (1024 * 1024), 2);
    }

    Response::success('Active RADIUS sessions retrieved', [
        'total_sessions' => count($sessions),
        'sessions' => $sessions
    ]);
}

/**
 * Get RADIUS statistics
 */
function handleGetStats() {
    global $auth, $db;

    $user = $auth->requireRole(['admin']);

    // Get statistics from last 24 hours
    $stats = [];

    // Total authentication attempts
    $stmt = $db->prepare('
        SELECT
            COUNT(*) as total_attempts,
            SUM(CASE WHEN event_type = "Start" THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN event_type = "Failed" THEN 1 ELSE 0 END) as failed
        FROM radius_accounting
        WHERE event_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ');
    $stmt->execute();
    $authStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['authentication'] = [
        'total_attempts' => (int)$authStats['total_attempts'],
        'successful' => (int)$authStats['successful'],
        'failed' => (int)$authStats['failed'],
        'success_rate' => $authStats['total_attempts'] > 0
            ? round(($authStats['successful'] / $authStats['total_attempts']) * 100, 2)
            : 0
    ];

    // Bandwidth statistics
    $stmt = $db->prepare('
        SELECT
            SUM(bytes_in) as total_download,
            SUM(bytes_out) as total_upload,
            COUNT(DISTINCT user_id) as unique_users
        FROM radius_accounting
        WHERE event_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ');
    $stmt->execute();
    $bandwidthStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['bandwidth'] = [
        'total_download_bytes' => (int)$bandwidthStats['total_download'],
        'total_upload_bytes' => (int)$bandwidthStats['total_upload'],
        'total_download_gb' => round($bandwidthStats['total_download'] / (1024 * 1024 * 1024), 2),
        'total_upload_gb' => round($bandwidthStats['total_upload'] / (1024 * 1024 * 1024), 2),
        'unique_users' => (int)$bandwidthStats['unique_users']
    ];

    // Current active sessions
    $stmt = $db->prepare('
        SELECT COUNT(*) as active_count
        FROM sessions
        WHERE status = ?
    ');
    $stmt->execute(['active']);
    $stats['active_sessions'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

    $stats['period'] = 'last_24_hours';
    $stats['timestamp'] = date('Y-m-d H:i:s');

    Response::success('RADIUS statistics retrieved', $stats);
}

/**
 * Disconnect user session via RADIUS CoA
 */
function handleDisconnect() {
    global $auth, $db, $validator, $logger;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Validate input - need either session_id, user_id, or mac_address
    if (empty($input['session_id']) && empty($input['user_id']) && empty($input['mac_address'])) {
        Response::error('session_id, user_id, or mac_address required', 400);
        return;
    }

    // Find the session
    $where = [];
    $params = [];

    if (!empty($input['session_id'])) {
        $where[] = 'id = ?';
        $params[] = $input['session_id'];
    } elseif (!empty($input['user_id'])) {
        $where[] = 'user_id = ?';
        $params[] = $input['user_id'];
    } elseif (!empty($input['mac_address'])) {
        $where[] = 'mac_address = ?';
        $params[] = $input['mac_address'];
    }

    $where[] = 'status = ?';
    $params[] = 'active';

    $stmt = $db->prepare('
        SELECT id, user_id, mac_address, ip_address, controller_id
        FROM sessions
        WHERE ' . implode(' AND ', $where) . '
        LIMIT 1
    ');
    $stmt->execute($params);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('No active session found', 404);
        return;
    }

    // Check permissions for resellers
    if ($user['role'] === 'reseller') {
        $stmt = $db->prepare('SELECT created_by FROM users WHERE id = ?');
        $stmt->execute([$session['user_id']]);
        $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sessionUser['created_by'] != $user['user_id']) {
            Response::error('Access denied', 403);
            return;
        }
    }

    // TODO: Send actual RADIUS Disconnect-Request (CoA)
    // This would use radclient or a PHP RADIUS library
    // For now, just end the session in database

    try {
        $stmt = $db->prepare('
            UPDATE sessions
            SET status = ?, end_time = NOW()
            WHERE id = ?
        ');
        $stmt->execute(['disconnected', $session['id']]);

        $logger->info('Session disconnected via RADIUS', [
            'session_id' => $session['id'],
            'user_id' => $session['user_id'],
            'admin_id' => $user['user_id']
        ]);

        Response::success('Session disconnect request sent', [
            'session_id' => (int)$session['id'],
            'message' => 'Session marked as disconnected. Full CoA implementation pending.'
        ]);

    } catch (PDOException $e) {
        $logger->error('Session disconnect failed: ' . $e->getMessage());
        Response::error('Failed to disconnect session', 500);
    }
}

/**
 * Send Change of Authorization (CoA) request
 */
function handleCoA() {
    global $auth, $logger;

    $user = $auth->requireRole(['admin']);

    $logger->info('CoA request attempted', ['user_id' => $user['user_id']]);

    Response::success('CoA request processed', [
        'message' => 'CoA functionality not yet fully implemented',
        'note' => 'Will be available when RADIUS server is configured'
    ]);
}

/**
 * Test RADIUS authentication
 */
function handleTestAuth() {
    global $auth, $logger;

    $user = $auth->requireRole(['admin']);

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['username']) || empty($input['password'])) {
        Response::error('username and password required', 400);
        return;
    }

    $logger->info('RADIUS auth test requested', [
        'username' => $input['username'],
        'admin_id' => $user['user_id']
    ]);

    // TODO: Actually test RADIUS authentication using radclient or PHP RADIUS library

    Response::success('Authentication test completed', [
        'username' => $input['username'],
        'result' => 'Test not yet implemented',
        'message' => 'Full RADIUS testing will be available when server is configured'
    ]);
}
