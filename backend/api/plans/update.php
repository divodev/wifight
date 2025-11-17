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

if (!empty($data->id)) {
    
    $updateData = [];
    
    if (isset($data->name)) $updateData['name'] = $data->name;
    if (isset($data->description)) $updateData['description'] = $data->description;
    if (isset($data->price)) $updateData['price'] = $data->price;
    if (isset($data->duration_hours)) $updateData['duration_hours'] = $data->duration_hours;
    if (isset($data->data_limit_mb)) $updateData['data_limit_mb'] = $data->data_limit_mb;
    if (isset($data->bandwidth_up)) $updateData['bandwidth_up'] = $data->bandwidth_up;
    if (isset($data->bandwidth_down)) $updateData['bandwidth_down'] = $data->bandwidth_down;
    if (isset($data->validity_days)) $updateData['validity_days'] = $data->validity_days;
    if (isset($data->status)) $updateData['status'] = $data->status;
    
    if ($plan->update($data->id, $updateData)) {
        Logger::activity($payload['user_id'], 'update_plan', [
            'plan_id' => $data->id
        ]);
        
        Response::success(null, 'Plan updated successfully');
    } else {
        Response::error('Unable to update plan', 500);
    }
} else {
    Response::error('Plan ID is required', 400);
}
?>