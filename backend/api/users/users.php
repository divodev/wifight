<?php
// backend/api/users/list.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/JWT.php';

$payload = JWT::authenticate();

if ($payload['role'] !== 'admin' && $payload['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$user = new User($db);

$location_id = null;
$role = null;

if ($payload['role'] !== 'admin') {
    $location_id = $payload['location_id'];
}

if (isset($_GET['location_id'])) {
    $location_id = $_GET['location_id'];
}

if (isset($_GET['role'])) {
    $role = $_GET['role'];
}

$users = $user->getAll($location_id, $role);

// Remove passwords
foreach ($users as &$u) {
    unset($u['password']);
}

echo json_encode([
    'success' => true,
    'message' => 'Users retrieved successfully',
    'data' => $users
]);