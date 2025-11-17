<?php
require_once '../../config/cors.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';

// Verify token
$payload = JWT::authenticate();

Logger::activity($payload['user_id'], 'logout', ['email' => $payload['email']]);

Response::success(null, 'Logout successful');
?>