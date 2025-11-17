<?php
// backend/api/sessions/history.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Session.php';
require_once __DIR__ . '/../../utils/JWT.php';

$payload = JWT::authenticate();

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$session = new Session($db);

$filters = [];

if (isset($_GET['controller_id'])) {
    $filters['controller_id'] = $_GET['controller_id'];
}

if (isset($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}

if (isset($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

if (isset($_GET['limit'])) {
    $filters['limit'] = (int)$_GET['limit'];
} else {
    $filters['limit'] = 100;
}

$history = $session->getHistory($filters);

echo json_encode([
    'success' => true,
    'message' => 'Session history retrieved',
    'data' => $history
]);