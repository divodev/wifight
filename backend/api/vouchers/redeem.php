<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Voucher.php';
require_once '../../utils/Response.php';
require_once '../../utils/Validator.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$voucher = new Voucher($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->code)) {
    
    // Validate voucher code format
    if (!Validator::voucherCode($data->code)) {
        Response::error('Invalid voucher code format', 400);
        exit;
    }
    
    $mac_address = isset($data->mac_address) ? $data->mac_address : null;
    
    // Validate MAC address if provided
    if ($mac_address && !Validator::macAddress($mac_address)) {
        Response::error('Invalid MAC address format', 400);
        exit;
    }
    
    $user_info = [
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    $result = $voucher->redeem($data->code, $mac_address, $user_info);
    
    if ($result['success']) {
        Logger::info('Voucher redeemed', [
            'code' => $data->code,
            'mac_address' => $mac_address
        ]);
        
        Response::success($result['voucher'], 'Voucher redeemed successfully');
    } else {
        Response::error($result['message'], 400);
    }
} else {
    Response::error('Voucher code is required', 400);
}
?>