<?php
// backend/api/controllers/update.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Controller.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Validator.php';

// Authenticate
try {
    $payload = JWT::authenticate();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($payload['role'] !== 'admin' && $payload['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$controller = new Controller($db);

// Get posted data
$input = file_get_contents("php://input");
$data = json_decode($input);

if (!$data || empty($data->id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Controller ID is required']);
    exit;
}

$updateData = [];

if (isset($data->name)) {
    $updateData['name'] = $data->name;
}

if (isset($data->ip_address)) {
    if (!Validator::ipAddress($data->ip_address)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid IP address']);
        exit;
    }
    $updateData['ip_address'] = $data->ip_address;
}

if (isset($data->port)) {
    $updateData['port'] = $data->port;
}

if (isset($data->username)) {
    $updateData['username'] = $data->username;
}

if (isset($data->password)) {
    $updateData['password'] = $data->password;
}

if (isset($data->site_id)) {
    $updateData['site_id'] = $data->site_id;
}

if (isset($data->status)) {
    $updateData['status'] = $data->status;
}

if (empty($updateData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No update data provided']);
    exit;
}

if ($controller->update($data->id, $updateData)) {
    echo json_encode([
        'success' => true,
        'message' => 'Controller updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update controller'
    ]);
}