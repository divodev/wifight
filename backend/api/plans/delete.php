<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Plan.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';

// Authenticate user
$payload = JWT::authenticate();

// Check admin role
if ($payload['role'] !== 'admin') {
    Response::error('Unauthorized access - Admin only', 403);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$plan = new Plan($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id)) {
    
    $result = $plan->delete($data->id);
    
    if ($result['success']) {
        Logger::activity($payload['user_id'], 'delete_plan', [
            'plan_id' => $data->id
        ]);
        
        Response::success(null, $result['message']);
    } else {
        Response::error($result['message'], 400);
    }
} else {
    Response::error('Plan ID is required', 400);
}
?>