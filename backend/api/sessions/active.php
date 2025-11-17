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

// Get filters
$controller_id = isset($_GET['controller_id']) ? $_GET['controller_id'] : null;
$mac_address = isset($_GET['mac_address']) ? $_GET['mac_address'] : null;

$activeSessions = $session->getActive($controller_id, $mac_address);

Response::success($activeSessions, 'Active sessions retrieved successfully');
?>