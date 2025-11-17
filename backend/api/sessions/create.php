<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Session.php';
require_once '../../utils/Response.php';
require_once '../../utils/Validator.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$session = new Session($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->mac_address) && !empty($data->controller_id)) {
    
    // Validate MAC address
    if (!Validator::macAddress($data->mac_address)) {
        Response::error('Invalid MAC address format', 400);
        exit;
    }
    
    // Set session properties
    $session->controller_id = $data->controller_id;
    $session->mac_address = $data->mac_address;
    $session->ip_address = isset($data->ip_address) ? $data->ip_address : $_SERVER['REMOTE_ADDR'];
    $session->username = isset($data->username) ? $data->username : null;
    $session->plan_id = isset($data->plan_id) ? $data->plan_id : null;
    $session->voucher_id = isset($data->voucher_id) ? $data->voucher_id : null;
    $session->device_info = isset($data->device_info) ? json_encode($data->device_info) : null;
    
    if ($session->create()) {
        Logger::info('Session created', [
            'session_id' => $session->id,
            'mac_address' => $session->mac_address
        ]);
        
        Response::success([
            'session_id' => $session->id,
            'mac_address' => $session->mac_address,
            'start_time' => date('Y-m-d H:i:s')
        ], 'Session created successfully', 201);
    } else {
        Response::error('Unable to create session', 500);
    }
} else {
    Response::error('MAC address and controller ID are required', 400);
}
?>