<?php
// backend/cron/expire_vouchers.php
// Purpose: mark vouchers that have expired as 'expired'.

// if (php_sapi_name() !== 'cli') { echo "Forbidden\n"; exit(1); }

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../../storage/logs/cron_expire_vouchers.log';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("UPDATE vouchers SET status = 'expired' WHERE status = 'unused' AND expires_at < NOW()");
    $stmt->execute();
    $count = $stmt->rowCount();

    $msg = date('c') . " - Expire vouchers: updated {$count} rows\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    exit(0);
} catch (Exception $e) {
    $err = date('c') . " - Expire vouchers failed: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $err, FILE_APPEND);
    exit(2);
}