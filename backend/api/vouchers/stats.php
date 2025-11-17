<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Voucher.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

// Authenticate user
$payload = JWT::authenticate();

$database = new Database();
$db = $database->getConnection();

$voucher = new Voucher($db);

$location_id = null;
if ($payload['role'] !== 'admin' && isset($payload['location_id'])) {
    $location_id = $payload['location_id'];
}

$stats = $voucher->getStats($location_id);

Response::success($stats, 'Voucher statistics retrieved successfully');
?>