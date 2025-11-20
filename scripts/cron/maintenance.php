<?php
/**
 * WiFight ISP System - Maintenance Cron Job
 *
 * Run: php scripts/cron/maintenance.php
 * Crontab: */5 * * * * php /path/to/wifight/scripts/cron/maintenance.php
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/utils/Logger.php';
require_once __DIR__ . '/../../backend/services/billing/SubscriptionManager.php';
require_once __DIR__ . '/../../backend/services/cache/CacheManager.php';

$logger = new Logger();
$db = Database::getInstance()->getConnection();
$cache = new CacheManager();

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║      WiFight ISP - Maintenance Cron Job                 ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);

try {
    // 1. Clean up expired sessions
    echo "1. Cleaning up expired sessions...\n";
    $stmt = $db->prepare('CALL sp_cleanup_expired_sessions(@cleaned_count)');
    $stmt->execute();
    $result = $db->query('SELECT @cleaned_count as count')->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Cleaned {$result['count']} expired sessions\n\n";

    // 2. Process expiring subscriptions
    echo "2. Processing expiring subscriptions...\n";
    $subscriptionManager = new SubscriptionManager();
    $result = $subscriptionManager->processExpiringSubscriptions();
    echo "   ✓ Processed subscriptions: {$result['renewed']} renewed, {$result['failed']} failed\n\n";

    // 3. Clean up old logs (keep last 30 days)
    echo "3. Cleaning up old logs...\n";
    $stmt = $db->prepare('
        DELETE FROM audit_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ');
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "   ✓ Deleted {$deleted} old audit log entries\n\n";

    // 4. Optimize tables
    echo "4. Optimizing database tables...\n";
    $tables = ['users', 'sessions', 'subscriptions', 'payments', 'audit_logs'];
    foreach ($tables as $table) {
        $db->exec("OPTIMIZE TABLE $table");
        echo "   ✓ Optimized $table\n";
    }
    echo "\n";

    // 5. Generate daily statistics
    echo "5. Generating daily statistics...\n";
    $stats = [
        'date' => date('Y-m-d'),
        'active_users' => $db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
        'active_sessions' => $db->query('SELECT COUNT(*) FROM sessions WHERE status = "active"')->fetchColumn(),
        'revenue' => $db->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) = CURDATE() AND status = "completed"')->fetchColumn(),
    ];
    $cache->set('daily:stats:' . date('Y-m-d'), $stats, 86400);
    echo "   ✓ Statistics cached\n\n";

    // 6. Check controller health
    echo "6. Checking controller health...\n";
    $stmt = $db->query('SELECT COUNT(*) FROM controllers WHERE status = "active"');
    $activeControllers = $stmt->fetchColumn();
    echo "   ✓ {$activeControllers} active controllers\n\n";

    $duration = round(microtime(true) - $startTime, 2);

    echo "╔══════════════════════════════════════════════════════════╗\n";
    echo "║         Maintenance completed successfully!             ║\n";
    echo "║              Duration: {$duration}s                           ║\n";
    echo "╚══════════════════════════════════════════════════════════╝\n";

    $logger->info('Maintenance cron completed', [
        'duration' => $duration,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    $logger->error('Maintenance cron failed: ' . $e->getMessage());
    exit(1);
}
