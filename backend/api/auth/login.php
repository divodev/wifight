<?php
// Disable error display (but log them)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../storage/logs/login_errors.log');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Load dependencies
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

// Autoload composer (for JWT)
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// Read input
$raw = file_get_contents("php://input");
$data = json_decode($raw);

// Validate input
if (empty($data->email) || empty($data->password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit();
}

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Find user by email
    $query = "SELECT id, full_name, email, password, role 
              FROM users 
              WHERE email = :email 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user exists
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
        exit();
    }

    // Verify password
    if (!password_verify($data->password, $user['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials'
        ]);
        exit();
    }

    // Generate JWT token
    if (!class_exists('Firebase\\JWT\\JWT')) {
        throw new Exception('JWT library not installed. Run: composer install');
    }

    $payload = [
        'iss' => 'wifight_api',
        'aud' => 'wifight_frontend',
        'iat' => time(),
        'exp' => time() + 3600,
        'sub' => (int)$user['id'],
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    
    $jwt = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $jwt,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}