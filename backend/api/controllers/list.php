<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Controller.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

// Authenticate user
$payload = JWT::authenticate();

$database = new Database();
$db = $database->getConnection();

$controller = new Controller($db);

// Get location filter
$location_id = null;
if ($payload['role'] !== 'admin') {
    $location_id = $payload['location_id'];
} elseif (isset($_GET['location_id'])) {
    $location_id = $_GET['location_id'];
}

$controllers = $controller->getAll($location_id);

// Remove password from response
foreach ($controllers as &$ctrl) {
    unset($ctrl['password']);
}

Response::success($controllers, 'Controllers retrieved successfully');
?>