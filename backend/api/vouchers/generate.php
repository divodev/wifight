<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Voucher.php';
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

$voucher = new Voucher($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->plan_id) && !empty($data->quantity)) {
    
    if ($data->quantity < 1 || $data->quantity > 1000) {
        Response::error('Quantity must be between 1 and 1000', 400);
        exit;
    }
    
    $batch_name = isset($data->batch_name) ? $data->batch_name : null;
    
    $result = $voucher->generate($data->plan_id, $data->quantity, $batch_name);
    
    if ($result) {
        Logger::activity($payload['user_id'], 'generate_vouchers', [
            'plan_id' => $data->plan_id,
            'quantity' => $data->quantity,
            'batch_id' => $result['batch_id']
        ]);
        
        Response::success($result, 'Vouchers generated successfully', 201);
    } else {
        Response::error('Unable to generate vouchers', 500);
    }
} else {
    Response::error('Plan ID and quantity are required', 400);
}
?>