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

// Get filters from query params
$filters = [];

if (isset($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['plan_id'])) {
    $filters['plan_id'] = $_GET['plan_id'];
}

if (isset($_GET['batch_id'])) {
    $filters['batch_id'] = $_GET['batch_id'];
}

if (isset($_GET['limit'])) {
    $filters['limit'] = (int)$_GET['limit'];
}

$vouchers = $voucher->getAll($filters);

Response::success($vouchers, 'Vouchers retrieved successfully');
?>