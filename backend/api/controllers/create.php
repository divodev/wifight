<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Controller.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../utils/Validator.php';
require_once '../../utils/Logger.php';

// Authenticate user
$payload = JWT::authenticate();

// Check admin role
if ($payload['role'] !== 'admin' && $payload['role'] !== 'manager') {
    Response::error('Unauthorized access', 403);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$controller = new Controller($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->name) && !empty($data->ip_address) && !empty($data->username) && !empty($data->password)) {
    
    // Validate IP address
    if (!Validator::ipAddress($data->ip_address)) {
        Response::error('Invalid IP address format', 400);
        exit;
    }
    
    // Set controller properties
    $controller->name = $data->name;
    $controller->ip_address = $data->ip_address;
    $controller->port = isset($data->port) ? $data->port : 8043;
    $controller->username = $data->username;
    $controller->password = $data->password;
    $controller->site_id = isset($data->site_id) ? $data->site_id : 'default';
    $controller->location_id = isset($data->location_id) ? $data->location_id : $payload['location_id'];
    $controller->status = 'active';
    
    if ($controller->create()) {
        Logger::activity($payload['user_id'], 'create_controller', [
            'controller_id' => $controller->id,
            'name' => $controller->name
        ]);
        
        Response::success([
            'id' => $controller->id,
            'name' => $controller->name,
            'ip_address' => $controller->ip_address,
            'port' => $controller->port
        ], 'Controller created successfully', 201);
    } else {
        Response::error('Unable to create controller', 500);
    }
} else {
    Response::error('Name, IP address, username, and password are required', 400);
}
?>