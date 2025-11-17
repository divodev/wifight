<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Controller.php';
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

$controller = new Controller($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id)) {
    
    if ($controller->delete($data->id)) {
        Logger::activity($payload['user_id'], 'delete_controller', [
            'controller_id' => $data->id
        ]);
        
        Response::success(null, 'Controller deleted successfully');
    } else {
        Response::error('Unable to delete controller', 500);
    }
} else {
    Response::error('Controller ID is required', 400);
}
?>