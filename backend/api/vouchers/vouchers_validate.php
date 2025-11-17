<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Voucher.php';
require_once '../../utils/Response.php';
require_once '../../utils/Validator.php';

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
    
    $result = $voucher->validate($data->code);
    
    if ($result['valid']) {
        Response::success($result['voucher'], 'Voucher is valid');
    } else {
        Response::error($result['message'], 400);
    }
} else {
    Response::error('Voucher code is required', 400);
}
?>