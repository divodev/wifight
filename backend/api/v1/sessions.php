<?php
/**
 * WiFight ISP System - Sessions API
 *
 * Endpoints for managing user sessions
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
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
$validator = new Validator();
$logger = new Logger();

// Get request method and parse route
$method = $_SERVER['REQUEST_METHOD');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));

// Extract session ID if present
$sessionId = isset($parts[3]) && is_numeric($parts[3]) ? (int)$parts[3] : null;
$action = isset($parts[4]) ? $parts[4] : null;

// Log request
$logger->logRequest($method, $requestUri);

try {
    // Route handlers
    switch ($method) {
        case 'GET':
            if ($sessionId && $action === 'usage') {
                // GET /api/v1/sessions/{id}/usage - Get session usage statistics
                handleGetSessionUsage($sessionId);
            } elseif ($sessionId) {
                // GET /api/v1/sessions/{id} - Get single session
                handleGetSession($sessionId);
            } elseif ($action === 'active') {
                // GET /api/v1/sessions/active - Get all active sessions
                handleGetActiveSessions();
            } else {
                // GET /api/v1/sessions - List sessions with filters
                handleListSessions();
            }
            break;

        case 'POST':
            if (!$sessionId) {
                // POST /api/v1/sessions - Create new session
                handleCreateSession();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if ($sessionId && $action === 'end') {
                // PUT /api/v1/sessions/{id}/end - End session
                handleEndSession($sessionId);
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'DELETE':
            if ($sessionId) {
                // DELETE /api/v1/sessions/{id} - Force end session
                handleEndSession($sessionId);
            } else {
                Response::error('Session ID required', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    $logger->error('Sessions API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

// =============================================================================
// HANDLER FUNCTIONS
// =============================================================================

/**
 * List sessions with pagination and filtering
 */
function handleListSessions() {
    global $auth, $db;

    $user = $auth->requireAuth();

    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $status = $_GET['status'] ?? null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $controllerId = isset($_GET['controller_id']) ? (int)$_GET['controller_id'] : null;

    // Validate pagination
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query based on user role
    $where = [];
    $params = [];

    // Regular users can only see their own sessions
    if ($user['role'] === 'user') {
        $where[] = 's.user_id = ?';
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'reseller') {
        // Resellers can see sessions for their users
        $where[] = 'EXISTS (SELECT 1 FROM users u WHERE u.id = s.user_id AND u.created_by = ?)';
        $params[] = $user['user_id'];
    }
    // Admins can see all sessions

    if ($status) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }

    if ($userId && $user['role'] !== 'user') {
        $where[] = 's.user_id = ?';
        $params[] = $userId;
    }

    if ($controllerId) {
        $where[] = 's.controller_id = ?';
        $params[] = $controllerId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM sessions s $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get sessions
    $stmt = $db->prepare("
        SELECT s.id, s.user_id, u.username, u.email, s.controller_id, c.name as controller_name,
               s.plan_id, p.name as plan_name, s.mac_address, s.ip_address,
               s.start_time, s.end_time, s.bytes_in, s.bytes_out, s.status
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN controllers c ON s.controller_id = c.id
        LEFT JOIN plans p ON s.plan_id = p.id
        $whereClause
        ORDER BY s.start_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($sessions as &$session) {
        $session['id'] = (int)$session['id'];
        $session['user_id'] = (int)$session['user_id'];
        $session['controller_id'] = (int)$session['controller_id'];
        $session['plan_id'] = $session['plan_id'] ? (int)$session['plan_id'] : null;
        $session['bytes_in'] = (int)$session['bytes_in'];
        $session['bytes_out'] = (int)$session['bytes_out'];
        $session['total_bytes'] = (int)$session['bytes_in'] + (int)$session['bytes_out'];

        // Calculate duration
        if ($session['end_time']) {
            $start = new DateTime($session['start_time']);
            $end = new DateTime($session['end_time']);
            $session['duration_seconds'] = $end->getTimestamp() - $start->getTimestamp();
        } else {
            $start = new DateTime($session['start_time']);
            $now = new DateTime();
            $session['duration_seconds'] = $now->getTimestamp() - $start->getTimestamp();
        }
    }

    Response::paginated($sessions, $totalRows, $page, $limit);
}

/**
 * Get active sessions
 */
function handleGetActiveSessions() {
    global $auth, $db;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Build query based on role
    $where = ['s.status = ?'];
    $params = ['active'];

    if ($user['role'] === 'reseller') {
        $where[] = 'EXISTS (SELECT 1 FROM users u WHERE u.id = s.user_id AND u.created_by = ?)';
        $params[] = $user['user_id'];
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT s.id, s.user_id, u.username, s.controller_id, c.name as controller_name,
               s.mac_address, s.ip_address, s.start_time, s.bytes_in, s.bytes_out,
               p.name as plan_name
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN controllers c ON s.controller_id = c.id
        LEFT JOIN plans p ON s.plan_id = p.id
        $whereClause
        ORDER BY s.start_time DESC
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($sessions as &$session) {
        $session['id'] = (int)$session['id'];
        $session['user_id'] = (int)$session['user_id'];
        $session['controller_id'] = (int)$session['controller_id'];
        $session['bytes_in'] = (int)$session['bytes_in'];
        $session['bytes_out'] = (int)$session['bytes_out'];

        $start = new DateTime($session['start_time']);
        $now = new DateTime();
        $session['duration_seconds'] = $now->getTimestamp() - $start->getTimestamp();
    }

    Response::success('Active sessions retrieved successfully', [
        'total_active' => count($sessions),
        'sessions' => $sessions
    ]);
}

/**
 * Get single session details
 */
function handleGetSession($sessionId) {
    global $auth, $db;

    $user = $auth->requireAuth();

    $stmt = $db->prepare('
        SELECT s.*, u.username, u.email, c.name as controller_name, p.name as plan_name
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN controllers c ON s.controller_id = c.id
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE s.id = ?
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('Session not found', 404);
        return;
    }

    // Check access permissions
    if ($user['role'] === 'user' && $session['user_id'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Format response
    $session['id'] = (int)$session['id'];
    $session['user_id'] = (int)$session['user_id'];
    $session['controller_id'] = (int)$session['controller_id'];
    $session['plan_id'] = $session['plan_id'] ? (int)$session['plan_id'] : null;
    $session['bytes_in'] = (int)$session['bytes_in'];
    $session['bytes_out'] = (int)$session['bytes_out'];
    $session['total_bytes'] = (int)$session['bytes_in'] + (int)$session['bytes_out'];

    // Calculate duration
    if ($session['end_time']) {
        $start = new DateTime($session['start_time']);
        $end = new DateTime($session['end_time']);
        $session['duration_seconds'] = $end->getTimestamp() - $start->getTimestamp();
    } else {
        $start = new DateTime($session['start_time']);
        $now = new DateTime();
        $session['duration_seconds'] = $now->getTimestamp() - $start->getTimestamp();
    }

    Response::success('Session retrieved successfully', $session);
}

/**
 * Create new session
 */
function handleCreateSession() {
    global $auth, $db, $validator, $logger;

    $user = $auth->requireAuth();

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Define validation rules
    $rules = [
        'controller_id' => ['required' => true, 'numeric' => true],
        'plan_id' => ['required' => true, 'numeric' => true],
        'mac_address' => ['required' => true, 'mac' => true],
        'ip_address' => ['ip' => true]
    ];

    // Validate input
    $errors = $validator->validate($input, $rules);
    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    // Use stored procedure to create session
    try {
        $stmt = $db->prepare('CALL sp_create_session(?, ?, ?, ?, ?, @session_id, @error_code, @error_message)');
        $stmt->execute([
            $user['user_id'],
            $input['controller_id'],
            $input['plan_id'],
            $input['mac_address'],
            $input['ip_address'] ?? null
        ]);

        // Get output variables
        $result = $db->query('SELECT @session_id as session_id, @error_code as error_code, @error_message as error_message')->fetch(PDO::FETCH_ASSOC);

        if ($result['error_code']) {
            Response::error($result['error_message'], 400, ['error_code' => $result['error_code']]);
            return;
        }

        $logger->info('Session created', [
            'session_id' => $result['session_id'],
            'user_id' => $user['user_id']
        ]);

        Response::success('Session created successfully', [
            'session_id' => (int)$result['session_id']
        ], 201);

    } catch (PDOException $e) {
        $logger->error('Session creation failed: ' . $e->getMessage());
        Response::error('Failed to create session', 500);
    }
}

/**
 * End session
 */
function handleEndSession($sessionId) {
    global $auth, $db, $logger;

    $user = $auth->requireAuth();

    // Get session
    $stmt = $db->prepare('SELECT user_id, status FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('Session not found', 404);
        return;
    }

    // Check permissions
    if ($user['role'] === 'user' && $session['user_id'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    if ($session['status'] !== 'active') {
        Response::error('Session is not active', 400);
        return;
    }

    // Get request body for usage data
    $input = json_decode(file_get_contents('php://input'), true);
    $bytesIn = isset($input['bytes_in']) ? (int)$input['bytes_in'] : 0;
    $bytesOut = isset($input['bytes_out']) ? (int)$input['bytes_out'] : 0;

    try {
        $stmt = $db->prepare('CALL sp_end_session(?, ?, ?, @success, @error_message)');
        $stmt->execute([$sessionId, $bytesIn, $bytesOut]);

        $result = $db->query('SELECT @success as success, @error_message as error_message')->fetch(PDO::FETCH_ASSOC);

        if (!$result['success']) {
            Response::error($result['error_message'], 400);
            return;
        }

        $logger->info('Session ended', ['session_id' => $sessionId, 'user_id' => $user['user_id']]);

        Response::success('Session ended successfully');

    } catch (PDOException $e) {
        $logger->error('Session end failed: ' . $e->getMessage());
        Response::error('Failed to end session', 500);
    }
}

/**
 * Get session usage statistics
 */
function handleGetSessionUsage($sessionId) {
    global $auth, $db;

    $user = $auth->requireAuth();

    $stmt = $db->prepare('
        SELECT s.*, p.name as plan_name, p.data_limit_gb
        FROM sessions s
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE s.id = ?
    ');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        Response::error('Session not found', 404);
        return;
    }

    // Check permissions
    if ($user['role'] === 'user' && $session['user_id'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    $bytesIn = (int)$session['bytes_in'];
    $bytesOut = (int)$session['bytes_out'];
    $totalBytes = $bytesIn + $bytesOut;
    $totalGB = $totalBytes / (1024 * 1024 * 1024);

    $usage = [
        'session_id' => (int)$session['id'],
        'bytes_in' => $bytesIn,
        'bytes_out' => $bytesOut,
        'total_bytes' => $totalBytes,
        'total_mb' => round($totalBytes / (1024 * 1024), 2),
        'total_gb' => round($totalGB, 2),
        'start_time' => $session['start_time'],
        'end_time' => $session['end_time'],
        'status' => $session['status']
    ];

    // Calculate duration
    if ($session['end_time']) {
        $start = new DateTime($session['start_time']);
        $end = new DateTime($session['end_time']);
        $duration = $end->getTimestamp() - $start->getTimestamp();
    } else {
        $start = new DateTime($session['start_time']);
        $now = new DateTime();
        $duration = $now->getTimestamp() - $start->getTimestamp();
    }

    $usage['duration_seconds'] = $duration;
    $usage['duration_minutes'] = round($duration / 60, 2);
    $usage['duration_hours'] = round($duration / 3600, 2);

    // Data limit check
    if ($session['data_limit_gb']) {
        $usage['data_limit_gb'] = (float)$session['data_limit_gb'];
        $usage['data_used_percent'] = round(($totalGB / (float)$session['data_limit_gb']) * 100, 2);
    }

    Response::success('Session usage retrieved successfully', $usage);
}
