<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/User.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../utils/Validator.php';
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

$user = new User($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password) && !empty($data->full_name)) {
    
    // Validate email
    if (!Validator::email($data->email)) {
        Response::error('Invalid email format', 400);
        exit;
    }
    
    // Validate password
    if (!Validator::minLength($data->password, 6)) {
        Response::error('Password must be at least 6 characters', 400);
        exit;
    }
    
    // Check if email exists
    if ($user->emailExists($data->email)) {
        Response::error('Email already exists', 409);
        exit;
    }
    
    // Set user properties
    $user->email = $data->email;
    $user->password = $data->password;
    $user->full_name = $data->full_name;
    $user->role = isset($data->role) ? $data->role : 'customer';
    $user->phone = isset($data->phone) ? $data->phone : null;
    $user->location_id = isset($data->location_id) ? $data->location_id : $payload['location_id'];
    $user->status = 'active';
    
    if ($user->create()) {
        Logger::activity($payload['user_id'], 'create_user', [
            'new_user_id' => $user->id,
            'email' => $user->email
        ]);
        
        Response::success([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role
        ], 'User created successfully', 201);
    } else {
        Response::error('Unable to create user', 500);
    }
} else {
    Response::error('Email, password, and full name are required', 400);
}
?>