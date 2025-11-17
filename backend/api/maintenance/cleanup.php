<?php
/**
 * Cleanup Expired Sessions
 * This script should be run periodically via cron job
 * Example: */5 * * * * php /path/to/cleanup_sessions.php
 */

require_once '../../config/database.php';
require_once '../../models/Session.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$session = new Session($db);

// Cleanup expired sessions
$result = $session->cleanupExpired();

if ($result) {
    Logger::info('Session cleanup completed successfully');
    echo json_encode([
        'success' => true,
        'message' => 'Expired sessions cleaned up successfully'
    ]);
} else {
    Logger::error('Session cleanup failed');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to cleanup expired sessions'
    ]);
}
?>