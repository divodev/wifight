<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/User.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

$database = new Database();
$db = $database->getConnection();

// Verify current token
$payload = JWT::authenticate();

// Get fresh user data
$user = new User($db);
$userData = $user->getById($payload['user_id']);

if ($userData) {
    // Generate new token
    $newPayload = [
        'user_id' => $userData['id'],
        'email' => $userData['email'],
        'role' => $userData['role'],
        'location_id' => $userData['location_id']
    ];
    
    $newToken = JWT::encode($newPayload);
    
    Response::success([
        'token' => $newToken,
        'user' => $userData
    ], 'Token refreshed successfully');
} else {
    Response::error('User not found', 404);
}
?>