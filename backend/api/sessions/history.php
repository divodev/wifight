<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Session.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

// Authenticate user
$payload = JWT::authenticate();

$database = new Database();
$db = $database->getConnection();

$session = new Session($db);

// Build filters from query params
$filters = [];

if (isset($_GET['controller_id'])) {
    $filters['controller_id'] = $_GET['controller_id'];
}

if (isset($_GET['mac_address'])) {
    $filters['mac_address'] = $_GET['mac_address'];
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

Response::success($history, 'Session history retrieved successfully');
?>