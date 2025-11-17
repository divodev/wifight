<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Plan.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';

// Authenticate user
$payload = JWT::authenticate();

// Check admin/manager role
if ($payload['role'] !== 'admin' && $payload['role'] !== 'manager') {
    Response::error('Unauthorized access', 403);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$plan = new Plan($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name) && isset($data->price)) {
    
    // Set plan properties
    $plan->name = $data->name;
    $plan->description = isset($data->description) ? $data->description : '';
    $plan->price = $data->price;
    $plan->duration_hours = isset($data->duration_hours) ? $data->duration_hours : null;
    $plan->data_limit_mb = isset($data->data_limit_mb) ? $data->data_limit_mb : null;
    $plan->bandwidth_up = isset($data->bandwidth_up) ? $data->bandwidth_up : null;
    $plan->bandwidth_down = isset($data->bandwidth_down) ? $data->bandwidth_down : null;
    $plan->validity_days = isset($data->validity_days) ? $data->validity_days : 30;
    $plan->status = 'active';
    $plan->location_id = isset($data->location_id) ? $data->location_id : $payload['location_id'];
    
    if ($plan->create()) {
        Logger::activity($payload['user_id'], 'create_plan', [
            'plan_id' => $plan->id,
            'name' => $plan->name
        ]);
        
        Response::success([
            'id' => $plan->id,
            'name' => $plan->name,
            'price' => $plan->price
        ], 'Plan created successfully', 201);
    } else {
        Response::error('Unable to create plan', 500);
    }
} else {
    Response::error('Name and price are required', 400);
}
?>