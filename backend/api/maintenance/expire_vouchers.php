<?php
/**
 * Expire Old Vouchers
 * This script should be run periodically via cron job
 * Example: 0 0 * * * php /path/to/expire_vouchers.php
 */

require_once '../../config/database.php';
require_once '../../models/Voucher.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$voucher = new Voucher($db);

// Expire old vouchers
$result = $voucher->expireOldVouchers();

if ($result) {
    Logger::info('Voucher expiration completed successfully');
    echo json_encode([
        'success' => true,
        'message' => 'Expired vouchers processed successfully'
    ]);
} else {
    Logger::error('Voucher expiration failed');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to expire vouchers'
    ]);
}
?>