<?php
/**
 * WiFight ISP System - Controllers API
 *
 * Endpoints for managing network controllers (MikroTik, Omada, Ruijie, Meraki)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/Encryption.php';
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
$encryption = new Encryption();

// Get request method and parse route
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));

// Extract controller ID if present (e.g., /api/v1/controllers/123)
$controllerId = isset($parts[3]) && is_numeric($parts[3]) ? (int)$parts[3] : null;
$action = isset($parts[4]) ? $parts[4] : null;

// Log request
$logger->logRequest($method, $requestUri);

try {
    // Route handlers
    switch ($method) {
        case 'GET':
            if ($controllerId && $action === 'sessions') {
                // GET /api/v1/controllers/{id}/sessions - Get active sessions on controller
                handleGetControllerSessions($controllerId);
            } elseif ($controllerId && $action === 'test') {
                // GET /api/v1/controllers/{id}/test - Test controller connection
                handleTestController($controllerId);
            } elseif ($controllerId) {
                // GET /api/v1/controllers/{id} - Get single controller
                handleGetController($controllerId);
            } else {
                // GET /api/v1/controllers - List all controllers
                handleListControllers();
            }
            break;

        case 'POST':
            if ($controllerId && $action === 'test') {
                // POST /api/v1/controllers/{id}/test - Test controller connection
                handleTestController($controllerId);
            } elseif (!$controllerId) {
                // POST /api/v1/controllers - Create new controller
                handleCreateController();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if ($controllerId) {
                // PUT /api/v1/controllers/{id} - Update controller
                handleUpdateController($controllerId);
            } else {
                Response::error('Controller ID required', 400);
            }
            break;

        case 'DELETE':
            if ($controllerId) {
                // DELETE /api/v1/controllers/{id} - Delete controller
                handleDeleteController($controllerId);
            } else {
                Response::error('Controller ID required', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    $logger->error('Controllers API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

// =============================================================================
// HANDLER FUNCTIONS
// =============================================================================

/**
 * List all controllers with pagination and filtering
 */
function handleListControllers() {
    global $auth, $db, $validator;

    // Require authentication - admin or reseller
    $user = $auth->requireRole(['admin', 'reseller']);

    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $type = $_GET['type'] ?? null;
    $status = $_GET['status'] ?? null;

    // Validate pagination
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $where = [];
    $params = [];

    // Filter by user role
    if ($user['role'] === 'reseller') {
        $where[] = 'created_by = ?';
        $params[] = $user['user_id'];
    }

    if ($type) {
        $where[] = 'type = ?';
        $params[] = $type;
    }

    if ($status) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM controllers $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get controllers
    $stmt = $db->prepare("
        SELECT id, name, type, host, port, status, description, last_sync, created_at, updated_at
        FROM controllers
        $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($controllers as &$controller) {
        $controller['id'] = (int)$controller['id'];
        $controller['port'] = (int)$controller['port'];
    }

    Response::paginated($controllers, $totalRows, $page, $limit);
}

/**
 * Get single controller details
 */
function handleGetController($controllerId) {
    global $auth, $db;

    $user = $auth->requireRole(['admin', 'reseller']);

    $stmt = $db->prepare('
        SELECT id, name, type, host, port, username, status, description, site_id,
               network_id, last_sync, created_by, created_at, updated_at
        FROM controllers
        WHERE id = ?
    ');
    $stmt->execute([$controllerId]);
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$controller) {
        Response::error('Controller not found', 404);
        return;
    }

    // Check ownership for resellers
    if ($user['role'] === 'reseller' && $controller['created_by'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Format response (hide sensitive data)
    $controller['id'] = (int)$controller['id'];
    $controller['port'] = (int)$controller['port'];
    unset($controller['password']); // Never send password
    unset($controller['api_key']); // Never send API key

    // Get session statistics
    $statsStmt = $db->prepare('
        SELECT
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_sessions
        FROM sessions
        WHERE controller_id = ?
    ');
    $statsStmt->execute([$controllerId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $controller['stats'] = [
        'total_sessions' => (int)$stats['total_sessions'],
        'active_sessions' => (int)$stats['active_sessions']
    ];

    Response::success('Controller retrieved successfully', $controller);
}

/**
 * Create new controller
 */
function handleCreateController() {
    global $auth, $db, $validator, $logger, $encryption;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Define validation rules
    $rules = [
        'name' => ['required' => true, 'max' => 100],
        'type' => ['required' => true, 'in' => ['mikrotik', 'omada', 'ruijie', 'meraki']],
        'host' => ['required' => true, 'max' => 255],
        'port' => ['numeric' => true],
        'username' => ['max' => 100],
        'password' => ['max' => 255],
        'api_key' => ['max' => 255],
        'description' => ['max' => 500]
    ];

    // Validate input
    $errors = $validator->validate($input, $rules);
    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    // Type-specific validation
    $type = $input['type'];
    if (in_array($type, ['mikrotik', 'omada']) && empty($input['username'])) {
        Response::error('Username is required for ' . ucfirst($type), 400);
        return;
    }

    if (in_array($type, ['ruijie', 'meraki']) && empty($input['api_key'])) {
        Response::error('API key is required for ' . ucfirst($type), 400);
        return;
    }

    // Encrypt sensitive data
    $encryptedPassword = !empty($input['password']) ? $encryption->encrypt($input['password']) : null;
    $encryptedApiKey = !empty($input['api_key']) ? $encryption->encrypt($input['api_key']) : null;

    // Insert controller
    try {
        $stmt = $db->prepare('
            INSERT INTO controllers (name, type, host, port, username, password, api_key, site_id, network_id, status, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $input['name'],
            $input['type'],
            $input['host'],
            $input['port'] ?? 8728,
            $input['username'] ?? null,
            $encryptedPassword,
            $encryptedApiKey,
            $input['site_id'] ?? null,
            $input['network_id'] ?? null,
            'inactive', // Start as inactive until tested
            $input['description'] ?? null,
            $user['user_id']
        ]);

        $controllerId = $db->lastInsertId();

        $logger->info('Controller created', ['controller_id' => $controllerId, 'user_id' => $user['user_id']]);

        Response::success('Controller created successfully', [
            'id' => (int)$controllerId,
            'name' => $input['name'],
            'type' => $input['type'],
            'status' => 'inactive'
        ], 201);

    } catch (PDOException $e) {
        $logger->error('Controller creation failed: ' . $e->getMessage());
        Response::error('Failed to create controller', 500);
    }
}

/**
 * Update controller
 */
function handleUpdateController($controllerId) {
    global $auth, $db, $validator, $logger, $encryption;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Check controller exists and ownership
    $stmt = $db->prepare('SELECT created_by FROM controllers WHERE id = ?');
    $stmt->execute([$controllerId]);
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$controller) {
        Response::error('Controller not found', 404);
        return;
    }

    if ($user['role'] === 'reseller' && $controller['created_by'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Build update query dynamically
    $updates = [];
    $params = [];

    $allowedFields = ['name', 'host', 'port', 'username', 'password', 'api_key', 'site_id', 'network_id', 'status', 'description'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if ($field === 'password' && !empty($input[$field])) {
                $updates[] = 'password = ?';
                $params[] = $encryption->encrypt($input[$field]);
            } elseif ($field === 'api_key' && !empty($input[$field])) {
                $updates[] = 'api_key = ?';
                $params[] = $encryption->encrypt($input[$field]);
            } else {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
    }

    if (empty($updates)) {
        Response::error('No fields to update', 400);
        return;
    }

    $updates[] = 'updated_at = NOW()';
    $params[] = $controllerId;

    try {
        $stmt = $db->prepare('UPDATE controllers SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        $logger->info('Controller updated', ['controller_id' => $controllerId, 'user_id' => $user['user_id']]);

        Response::success('Controller updated successfully');

    } catch (PDOException $e) {
        $logger->error('Controller update failed: ' . $e->getMessage());
        Response::error('Failed to update controller', 500);
    }
}

/**
 * Delete controller
 */
function handleDeleteController($controllerId) {
    global $auth, $db, $logger;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Check controller exists and ownership
    $stmt = $db->prepare('SELECT created_by FROM controllers WHERE id = ?');
    $stmt->execute([$controllerId]);
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$controller) {
        Response::error('Controller not found', 404);
        return;
    }

    if ($user['role'] === 'reseller' && $controller['created_by'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Check for active sessions
    $stmt = $db->prepare('SELECT COUNT(*) as active_count FROM sessions WHERE controller_id = ? AND status = ?');
    $stmt->execute([$controllerId, 'active']);
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

    if ($activeCount > 0) {
        Response::error("Cannot delete controller with $activeCount active sessions", 400);
        return;
    }

    try {
        $stmt = $db->prepare('DELETE FROM controllers WHERE id = ?');
        $stmt->execute([$controllerId]);

        $logger->info('Controller deleted', ['controller_id' => $controllerId, 'user_id' => $user['user_id']]);

        Response::success('Controller deleted successfully');

    } catch (PDOException $e) {
        $logger->error('Controller deletion failed: ' . $e->getMessage());
        Response::error('Failed to delete controller', 500);
    }
}

/**
 * Test controller connection
 */
function handleTestController($controllerId) {
    global $auth, $db, $logger;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Get controller details
    $stmt = $db->prepare('SELECT * FROM controllers WHERE id = ?');
    $stmt->execute([$controllerId]);
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$controller) {
        Response::error('Controller not found', 404);
        return;
    }

    if ($user['role'] === 'reseller' && $controller['created_by'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // TODO: Implement actual controller connection testing
    // This would use the ControllerFactory to create an instance and test connection

    $logger->info('Controller connection test requested', ['controller_id' => $controllerId]);

    Response::success('Controller connection test completed', [
        'controller_id' => $controllerId,
        'status' => 'pending',
        'message' => 'Connection test not yet implemented. Will be available when controller implementations are complete.'
    ]);
}

/**
 * Get active sessions on controller
 */
function handleGetControllerSessions($controllerId) {
    global $auth, $db;

    $user = $auth->requireRole(['admin', 'reseller']);

    // Get controller
    $stmt = $db->prepare('SELECT created_by FROM controllers WHERE id = ?');
    $stmt->execute([$controllerId]);
    $controller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$controller) {
        Response::error('Controller not found', 404);
        return;
    }

    if ($user['role'] === 'reseller' && $controller['created_by'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Get sessions
    $stmt = $db->prepare('
        SELECT s.id, s.user_id, u.username, u.email, s.mac_address, s.ip_address,
               s.start_time, s.bytes_in, s.bytes_out, s.status, p.name as plan_name
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE s.controller_id = ? AND s.status = ?
        ORDER BY s.start_time DESC
    ');
    $stmt->execute([$controllerId, 'active']);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($sessions as &$session) {
        $session['id'] = (int)$session['id'];
        $session['user_id'] = (int)$session['user_id'];
        $session['bytes_in'] = (int)$session['bytes_in'];
        $session['bytes_out'] = (int)$session['bytes_out'];
    }

    Response::success('Active sessions retrieved successfully', [
        'controller_id' => $controllerId,
        'active_sessions' => count($sessions),
        'sessions' => $sessions
    ]);
}
