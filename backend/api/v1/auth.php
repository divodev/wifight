<?php
/**
 * WiFight ISP System - Authentication API
 *
 * POST /api/v1/auth/login
 * POST /api/v1/auth/register
 * POST /api/v1/auth/logout
 * POST /api/v1/auth/refresh
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Validator.php';

$response = new Response();
$jwt = new JWT();

// Get action from URL
$action = $parts[3] ?? 'login';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        handlePost($action, $response, $jwt);
        break;

    default:
        $response->error('Method not allowed', 405);
}

function handlePost($action, $response, $jwt) {
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'login':
            handleLogin($input, $response, $jwt);
            break;

        case 'register':
            handleRegister($input, $response, $jwt);
            break;

        case 'refresh':
            handleRefresh($input, $response, $jwt);
            break;

        case 'logout':
            handleLogout($input, $response, $jwt);
            break;

        default:
            $response->notFound('Action not found');
    }
}

function handleLogin($input, $response, $jwt) {
    // Validate input
    $validator = new Validator($input);
    $validator->setRules([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!$validator->validate()) {
        $response->validationError($validator->getErrors());
    }

    try {
        $db = (new Database())->getConnection();

        // Find user
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND status = 'active'");
        $stmt->execute(['email' => $input['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            $response->error('Invalid credentials', 401);
        }

        // Update last login
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $user['id']]);

        // Generate tokens
        $payload = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $tokens = $jwt->createTokenPair($payload);

        $response->success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'full_name' => $user['full_name']
            ],
            'tokens' => $tokens
        ], 'Login successful');

    } catch (Exception $e) {
        $response->serverError('Login failed');
    }
}

function handleRegister($input, $response, $jwt) {
    // Check if registration is enabled
    if (getenv('REGISTRATION_ENABLED') === 'false') {
        $response->error('Registration is currently disabled', 403);
    }

    // Validate input
    $validator = new Validator($input);
    $validator->setRules([
        'username' => 'required|min:3|max:50',
        'email' => 'required|email',
        'password' => 'required|min:8',
        'full_name' => 'required'
    ]);

    if (!$validator->validate()) {
        $response->validationError($validator->getErrors());
    }

    // Validate password strength
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
            INSERT INTO users (username, email, password_hash, full_name, phone, role)
            VALUES (:username, :email, :password, :full_name, :phone, 'user')
        ");

        $stmt->execute([
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_BCRYPT),
            'full_name' => $input['full_name'],
            'phone' => $input['phone'] ?? null
        ]);

        $userId = $db->lastInsertId();

        $response->success([
            'id' => $userId,
            'username' => $input['username'],
            'email' => $input['email']
        ], 'Registration successful', 201);

    } catch (Exception $e) {
        $response->serverError('Registration failed');
    }
}

function handleRefresh($input, $response, $jwt) {
    if (empty($input['refresh_token'])) {
        $response->error('Refresh token required', 400);
    }

    $tokens = $jwt->refresh($input['refresh_token']);

    if (!$tokens) {
        $response->error('Invalid refresh token', 401);
    }

    $response->success($tokens, 'Token refreshed');
}

function handleLogout($input, $response, $jwt) {
    // Get token from header
    $token = $jwt->getTokenFromHeader();

    if ($token) {
        $jwt->blacklist($token);
    }

    $response->success(null, 'Logout successful');
}
