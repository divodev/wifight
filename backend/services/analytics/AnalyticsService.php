<?php
/**
 * WiFight ISP System - Analytics Service
 *
 * Provides business intelligence and analytics data
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../cache/CacheManager.php';

class AnalyticsService {
    private $db;
    private $logger;
    private $cache;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
        $this->cache = new CacheManager();
    }

    /**
     * Get dashboard overview metrics
     */
    public function getDashboardOverview() {
        return $this->cache->remember('analytics:dashboard:overview', function() {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Revenue metrics
            $todayRevenue = $this->getRevenue($today, $today);
            $yesterdayRevenue = $this->getRevenue($yesterday, $yesterday);
            $monthRevenue = $this->getRevenue(date('Y-m-01'), $today);

            // User metrics
            $totalUsers = $this->db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
            $newUsersToday = $this->db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '{$today}'")->fetchColumn();

            // Session metrics
            $activeSessions = $this->db->query('SELECT COUNT(*) FROM sessions WHERE status = "active"')->fetchColumn();
            $totalSessionsToday = $this->db->query("SELECT COUNT(*) FROM sessions WHERE DATE(created_at) = '{$today}'")->fetchColumn();

            // Subscription metrics
            $activeSubscriptions = $this->db->query('SELECT COUNT(*) FROM subscriptions WHERE status = "active"')->fetchColumn();
            $expiringSubscriptions = $this->db->query('
                SELECT COUNT(*) FROM subscriptions
                WHERE status = "active" AND end_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ')->fetchColumn();

            // Payment metrics
            $successfulPaymentsToday = $this->db->query("
                SELECT COUNT(*) FROM payments
                WHERE DATE(created_at) = '{$today}' AND status = 'completed'
            ")->fetchColumn();

            $failedPaymentsToday = $this->db->query("
                SELECT COUNT(*) FROM payments
                WHERE DATE(created_at) = '{$today}' AND status = 'failed'
            ")->fetchColumn();

            return [
                'revenue' => [
                    'today' => (float)$todayRevenue,
                    'yesterday' => (float)$yesterdayRevenue,
                    'month' => (float)$monthRevenue,
                    'change_percent' => $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 2) : 0
                ],
                'users' => [
                    'total_active' => (int)$totalUsers,
                    'new_today' => (int)$newUsersToday
                ],
                'sessions' => [
                    'active' => (int)$activeSessions,
                    'today_total' => (int)$totalSessionsToday
                ],
                'subscriptions' => [
                    'active' => (int)$activeSubscriptions,
                    'expiring_soon' => (int)$expiringSubscriptions
                ],
                'payments' => [
                    'successful_today' => (int)$successfulPaymentsToday,
                    'failed_today' => (int)$failedPaymentsToday,
                    'success_rate' => ($successfulPaymentsToday + $failedPaymentsToday) > 0
                        ? round(($successfulPaymentsToday / ($successfulPaymentsToday + $failedPaymentsToday)) * 100, 2)
                        : 0
                ]
            ];
        }, 300); // Cache for 5 minutes
    }

    /**
     * Get revenue for date range
     */
    public function getRevenue(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(amount), 0) as revenue
            FROM payments
            WHERE status = "completed"
            AND DATE(created_at) BETWEEN ? AND ?
        ');

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
    }

    /**
     * Get revenue trend (daily breakdown)
     */
    public function getRevenueTrend(int $days = 30) {
        return $this->cache->remember("analytics:revenue:trend:{$days}", function() use ($days) {
            $stmt = $this->db->prepare('
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as transaction_count,
                    SUM(amount) as revenue
                FROM payments
                WHERE status = "completed"
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ');

            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 3600);
    }

    /**
     * Get user growth statistics
     */
    public function getUserGrowth(int $days = 30) {
        return $this->cache->remember("analytics:users:growth:{$days}", function() use ($days) {
            $stmt = $this->db->prepare('
                SELECT
                    DATE(created_at) as date,
                    COUNT(*) as new_users,
                    role,
                    status
                FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), role, status
                ORDER BY date ASC
            ');

            $stmt->execute([$days]);
            $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by date
            $grouped = [];
            foreach ($rawData as $row) {
                $date = $row['date'];
                if (!isset($grouped[$date])) {
                    $grouped[$date] = [
                        'date' => $date,
                        'total' => 0,
                        'by_role' => [],
                        'by_status' => []
                    ];
                }
                $grouped[$date]['total'] += (int)$row['new_users'];
                $grouped[$date]['by_role'][$row['role']] = (int)$row['new_users'];
                $grouped[$date]['by_status'][$row['status']] = (int)$row['new_users'];
            }

            return array_values($grouped);
        }, 3600);
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(int $days = 7) {
        return $this->cache->remember("analytics:sessions:stats:{$days}", function() use ($days) {
            $stmt = $this->db->prepare('
                SELECT
                    DATE(s.created_at) as date,
                    COUNT(*) as session_count,
                    AVG(TIMESTAMPDIFF(MINUTE, s.created_at, s.ended_at)) as avg_duration_minutes,
                    SUM(s.bytes_uploaded + s.bytes_downloaded) / 1024 / 1024 / 1024 as total_bandwidth_gb
                FROM sessions s
                WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(s.created_at)
                ORDER BY date ASC
            ');

            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 1800);
    }

    /**
     * Get top plans by revenue
     */
    public function getTopPlansByRevenue(int $limit = 10) {
        return $this->cache->remember("analytics:plans:top:{$limit}", function() use ($limit) {
            $stmt = $this->db->prepare('
                SELECT
                    p.id,
                    p.name,
                    p.price,
                    COUNT(DISTINCT s.user_id) as subscriber_count,
                    COUNT(pay.id) as payment_count,
                    COALESCE(SUM(pay.amount), 0) as total_revenue
                FROM plans p
                LEFT JOIN subscriptions s ON p.id = s.plan_id AND s.status = "active"
                LEFT JOIN payments pay ON pay.plan_id = p.id AND pay.status = "completed"
                WHERE p.status = "active"
                GROUP BY p.id
                ORDER BY total_revenue DESC
                LIMIT ?
            ');

            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 1800);
    }

    /**
     * Get top users by revenue
     */
    public function getTopUsersByRevenue(int $limit = 10) {
        $stmt = $this->db->prepare('
            SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                COUNT(p.id) as payment_count,
                COALESCE(SUM(p.amount), 0) as total_spent,
                MAX(p.created_at) as last_payment_date
            FROM users u
            LEFT JOIN payments p ON u.id = p.user_id AND p.status = "completed"
            GROUP BY u.id
            HAVING total_spent > 0
            ORDER BY total_spent DESC
            LIMIT ?
        ');

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment method statistics
     */
    public function getPaymentMethodStats() {
        return $this->cache->remember('analytics:payments:methods', function() {
            $stmt = $this->db->query('
                SELECT
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_count,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
                FROM payments
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ');

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 1800);
    }

    /**
     * Get churn rate (subscription cancellations)
     */
    public function getChurnRate(int $days = 30) {
        $stmt = $this->db->prepare('
            SELECT
                COUNT(CASE WHEN status = "cancelled" THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active,
                COUNT(*) as total
            FROM subscriptions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ');

        $stmt->execute([$days]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)$data['total'];
        $cancelled = (int)$data['cancelled'];

        return [
            'cancelled' => $cancelled,
            'active' => (int)$data['active'],
            'total' => $total,
            'churn_rate' => $total > 0 ? round(($cancelled / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get customer lifetime value (CLV)
     */
    public function getCustomerLifetimeValue() {
        return $this->cache->remember('analytics:clv', function() {
            $stmt = $this->db->query('
                SELECT
                    AVG(user_revenue) as avg_lifetime_value,
                    MAX(user_revenue) as max_lifetime_value,
                    MIN(user_revenue) as min_lifetime_value
                FROM (
                    SELECT
                        user_id,
                        SUM(amount) as user_revenue
                    FROM payments
                    WHERE status = "completed"
                    GROUP BY user_id
                ) as user_totals
            ');

            return $stmt->fetch(PDO::FETCH_ASSOC);
        }, 3600);
    }

    /**
     * Get bandwidth usage analytics
     */
    public function getBandwidthAnalytics(int $days = 7) {
        return $this->cache->remember("analytics:bandwidth:{$days}", function() use ($days) {
            $stmt = $this->db->prepare('
                SELECT
                    DATE(created_at) as date,
                    SUM(bytes_uploaded) / 1024 / 1024 / 1024 as total_upload_gb,
                    SUM(bytes_downloaded) / 1024 / 1024 / 1024 as total_download_gb,
                    COUNT(DISTINCT user_id) as unique_users
                FROM sessions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = "ended"
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ');

            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 1800);
    }

    /**
     * Get controller performance metrics
     */
    public function getControllerMetrics() {
        return $this->cache->remember('analytics:controllers', function() {
            $stmt = $this->db->query('
                SELECT
                    c.id,
                    c.name,
                    c.type,
                    c.status,
                    COUNT(s.id) as total_sessions,
                    COUNT(CASE WHEN s.status = "active" THEN 1 END) as active_sessions,
                    COALESCE(SUM(s.bytes_uploaded + s.bytes_downloaded) / 1024 / 1024 / 1024, 0) as total_bandwidth_gb
                FROM controllers c
                LEFT JOIN sessions s ON c.id = s.controller_id
                GROUP BY c.id
                ORDER BY total_sessions DESC
            ');

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, 600);
    }

    /**
     * Get voucher redemption statistics
     */
    public function getVoucherStats() {
        return $this->cache->remember('analytics:vouchers', function() {
            $stmt = $this->db->query('
                SELECT
                    COUNT(*) as total_vouchers,
                    COUNT(CASE WHEN used = 1 THEN 1 END) as redeemed,
                    COUNT(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 END) as active,
                    COUNT(CASE WHEN used = 0 AND expires_at <= NOW() THEN 1 END) as expired,
                    COALESCE(SUM(CASE WHEN used = 1 THEN value ELSE 0 END), 0) as total_value_redeemed
                FROM vouchers
            ');

            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)$data['total_vouchers'];
            $redeemed = (int)$data['redeemed'];

            return [
                'total_vouchers' => $total,
                'redeemed' => $redeemed,
                'active' => (int)$data['active'],
                'expired' => (int)$data['expired'],
                'total_value_redeemed' => (float)$data['total_value_redeemed'],
                'redemption_rate' => $total > 0 ? round(($redeemed / $total) * 100, 2) : 0
            ];
        }, 1800);
    }

    /**
     * Get real-time activity feed
     */
    public function getActivityFeed(int $limit = 50) {
        $stmt = $this->db->prepare('
            SELECT
                action,
                entity_type,
                entity_id,
                user_id,
                ip_address,
                created_at
            FROM audit_logs
            ORDER BY created_at DESC
            LIMIT ?
        ');

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate comprehensive analytics report
     */
    public function generateComprehensiveReport(string $startDate, string $endDate) {
        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => (strtotime($endDate) - strtotime($startDate)) / 86400
            ],
            'revenue' => [
                'total' => $this->getRevenue($startDate, $endDate),
                'trend' => $this->getRevenueTrend(30)
            ],
            'users' => [
                'growth' => $this->getUserGrowth(30),
                'top_spenders' => $this->getTopUsersByRevenue(10)
            ],
            'sessions' => $this->getSessionStatistics(7),
            'plans' => $this->getTopPlansByRevenue(10),
            'payments' => $this->getPaymentMethodStats(),
            'churn' => $this->getChurnRate(30),
            'clv' => $this->getCustomerLifetimeValue(),
            'bandwidth' => $this->getBandwidthAnalytics(7),
            'controllers' => $this->getControllerMetrics(),
            'vouchers' => $this->getVoucherStats()
        ];
    }
}
