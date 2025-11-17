<?php
// backend/cron/cleanup_sessions.php
// Purpose: remove terminated/old sessions (adjust retention interval as needed).

// Run only from CLI for security - uncomment if you want to ensure CLI-only execution
// if (php_sapi_name() !== 'cli') { echo "Forbidden\n"; exit(1); }

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../../storage/logs/cron_cleanup_sessions.log';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Example query: delete sessions terminated more than 30 days ago
    $stmt = $db->prepare("DELETE FROM sessions WHERE status = 'terminated' AND end_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $count = $stmt->rowCount();

    $msg = date('c') . " - Cleanup sessions: removed {$count} rows\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    exit(0);
} catch (Exception $e) {
    $err = date('c') . " - Cleanup sessions failed: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $err, FILE_APPEND);
    exit(2);
}