<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Plan.php';
require_once '../../utils/Response.php';

$database = new Database();
$db = $database->getConnection();

$plan = new Plan($db);

// Get location filter
$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : 'active';

$plans = $plan->getAll($location_id, $status);

Response::success($plans, 'Plans retrieved successfully');
?>