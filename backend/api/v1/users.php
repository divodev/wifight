<?php
/**
 * WiFight ISP System - Users API
 *
 * GET    /api/v1/users          - List all users (admin/reseller)
 * GET    /api/v1/users/:id      - Get user by ID
 * POST   /api/v1/users          - Create new user (admin)
 * PUT    /api/v1/users/:id      - Update user
 * DELETE /api/v1/users/:id      - Delete user (admin)
 * GET    /api/v1/users/me       - Get current user profile
 * PUT    /api/v1/users/me       - Update current user profile
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';

$response = new Response();
$jwt = new JWT();
$logger = new Logger('Users');

// Authenticate request
$token = $jwt->getTokenFromHeader();
if (!$token || !$jwt->validate($token)) {
    $response->unauthorized('Authentication required');
}

$userData = $jwt->decode($token);
$method = $_SERVER['REQUEST_METHOD'];

// Get resource ID from URL
$action = $parts[3] ?? '';
$userId = is_numeric($action) ? $action : null;

switch ($method) {
    case 'GET':
        if ($action === 'me') {
            getMyProfile($userData, $response);
        } elseif ($userId) {
            getUserById($userId, $userData, $response);
        } else {
            getAllUsers($userData, $response);
        }
        break;

    case 'POST':
        createUser($userData, $response);
        break;

    case 'PUT':
        if ($action === 'me') {
            updateMyProfile($userData, $response);
        } elseif ($userId) {
            updateUser($userId, $userData, $response);
        } else {
            $response->error('User ID required', 400);
        }
        break;

    case 'DELETE':
        if ($userId) {
            deleteUser($userId, $userData, $response);
        } else {
            $response->error('User ID required', 400);
        }
        break;

    default:
        $response->error('Method not allowed', 405);
}

/**
 * Get all users (with pagination and filtering)
 */
function getAllUsers($userData, $response) {
    // Check permissions
    if (!in_array($userData->role, ['admin', 'reseller'])) {
        $response->forbidden('Insufficient permissions');
    }

    try {
        $db = (new Database())->getConnection();

        // Get query parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 100) : 20;
        $offset = ($page - 1) * $perPage;
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';

        // Build query
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = "(username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($role)) {
            $where[] = "role = :role";
            $params['role'] = $role;
        }

        if (!empty($status)) {
            $where[] = "status = :status";
            $params['status'] = $status;
        }

        // Resellers can only see their own users
        if ($userData->role === 'reseller') {
            $where[] = "created_by = :reseller_id";
            $params['reseller_id'] = $userData->id;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users {$whereClause}");
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get users
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT
                id, username, email, full_name, phone, role, status,
                balance, created_at, last_login, two_factor_enabled
            FROM users
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $response->paginated($users, $total, $page, $perPage);

    } catch (Exception $e) {
        $response->serverError('Failed to fetch users');
    }
}

/**
 * Get user by ID
 */
function getUserById($userId, $userData, $response) {
    try {
        $db = (new Database())->getConnection();

        // Check permissions
        if ($userData->role === 'user' && $userData->id != $userId) {
            $response->forbidden('Cannot view other users');
        }

        $stmt = $db->prepare("
            SELECT
                id, username, email, full_name, phone, role, status,
                balance, created_at, updated_at, last_login, two_factor_enabled
            FROM users
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->notFound('User not found');
        }

        // Get user statistics
        $statsStmt = $db->prepare("
            SELECT
                COUNT(DISTINCT s.id) as total_sessions,
                SUM(s.bytes_in + s.bytes_out) as total_data_used,
                COUNT(DISTINCT p.id) as total_payments,
                SUM(p.amount) as total_spent
            FROM users u
            LEFT JOIN sessions s ON u.id = s.user_id
            LEFT JOIN payments p ON u.id = p.user_id AND p.status = 'completed'
            WHERE u.id = :id
            GROUP BY u.id
        ");
        $statsStmt->execute(['id' => $userId]);
        $stats = $statsStmt->fetch();

        $user['statistics'] = $stats ?: [
            'total_sessions' => 0,
            'total_data_used' => 0,
            'total_payments' => 0,
            'total_spent' => 0
        ];

        $response->success($user);

    } catch (Exception $e) {
        $response->serverError('Failed to fetch user');
    }
}

/**
 * Create new user
 */
function createUser($userData, $response) {
    // Only admins can create users
    if ($userData->role !== 'admin') {
        $response->forbidden('Only administrators can create users');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new Validator($input);
    $validator->setRules([
        'username' => 'required|min:3|max:50',
        'email' => 'required|email',
        'password' => 'required|min:8',
        'full_name' => 'required',
        'role' => 'required|in:admin,reseller,user'
    ]);

    if (!$validator->validate()) {
        $response->validationError($validator->getErrors());
    }

    // Validate password
    $passwordCheck = Validator::validatePassword($input['password']);
    if (!$passwordCheck['valid']) {
        $response->validationError(['password' => $passwordCheck['errors']]);
    }

    try {
        $db = (new Database())->getConnection();

        // Check if user exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
        $checkStmt->execute([
            'email' => $input['email'],
            'username' => $input['username']
        ]);

        if ($checkStmt->fetch()) {
            $response->error('User already exists', 409);
        }

        // Create user
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, phone, role, status, balance)
            VALUES (:username, :email, :password, :full_name, :phone, :role, :status, :balance)
        ");

        $stmt->execute([
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'full_name' => $input['full_name'],
            'phone' => $input['phone'] ?? null,
            'role' => $input['role'],
            'status' => $input['status'] ?? 'active',
            'balance' => $input['balance'] ?? 0.00
        ]);

        $newUserId = $db->lastInsertId();

        // Log action
        $logger = new Logger('Users');
        $logger->info('User created', [
            'user_id' => $newUserId,
            'created_by' => $userData->id,
            'role' => $input['role']
        ]);

        $response->success([
            'id' => $newUserId,
            'username' => $input['username'],
            'email' => $input['email']
        ], 'User created successfully', 201);

    } catch (Exception $e) {
        $response->serverError('Failed to create user');
    }
}

/**
 * Update user
 */
function updateUser($userId, $userData, $response) {
    // Check permissions
    if ($userData->role === 'user' && $userData->id != $userId) {
        $response->forbidden('Cannot update other users');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = (new Database())->getConnection();

        // Check if user exists
        $checkStmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $checkStmt->execute(['id' => $userId]);
        $existingUser = $checkStmt->fetch();

        if (!$existingUser) {
            $response->notFound('User not found');
        }

        // Build update query
        $fields = [];
        $params = ['id' => $userId];

        $allowedFields = ['full_name', 'phone', 'status'];
        if ($userData->role === 'admin') {
            $allowedFields = array_merge($allowedFields, ['role', 'balance', 'email']);
        }

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $input[$field];
            }
        }

        // Handle password update
        if (!empty($input['password'])) {
            $passwordCheck = Validator::validatePassword($input['password']);
            if (!$passwordCheck['valid']) {
                $response->validationError(['password' => $passwordCheck['errors']]);
            }
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            $response->error('No fields to update', 400);
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $response->success(['id' => $userId], 'User updated successfully');

    } catch (Exception $e) {
        $response->serverError('Failed to update user');
    }
}

/**
 * Delete user
 */
function deleteUser($userId, $userData, $response) {
    // Only admins can delete users
    if ($userData->role !== 'admin') {
        $response->forbidden('Only administrators can delete users');
    }

    // Cannot delete self
    if ($userData->id == $userId) {
        $response->error('Cannot delete your own account', 400);
    }

    try {
        $db = (new Database())->getConnection();

        // Check if user exists
        $checkStmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $checkStmt->execute(['id' => $userId]);
        $user = $checkStmt->fetch();

        if (!$user) {
            $response->notFound('User not found');
        }

        // Check for active sessions
        $sessionStmt = $db->prepare("SELECT COUNT(*) as count FROM sessions WHERE user_id = :id AND status = 'active'");
        $sessionStmt->execute(['id' => $userId]);
        $activeSessions = $sessionStmt->fetch()['count'];

        if ($activeSessions > 0) {
            $response->error('Cannot delete user with active sessions', 400);
        }

        // Delete user (cascade will handle related records)
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        $logger = new Logger('Users');
        $logger->info('User deleted', [
            'user_id' => $userId,
            'deleted_by' => $userData->id
        ]);

        $response->success(null, 'User deleted successfully');

    } catch (Exception $e) {
        $response->serverError('Failed to delete user');
    }
}

/**
 * Get current user profile
 */
function getMyProfile($userData, $response) {
    getUserById($userData->id, $userData, $response);
}

/**
 * Update current user profile
 */
function updateMyProfile($userData, $response) {
    updateUser($userData->id, $userData, $response);
}
