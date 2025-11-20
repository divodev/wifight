<?php
/**
 * WiFight ISP System - Report Generator
 *
 * Generates various business reports in multiple formats
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/AnalyticsService.php';

class ReportGenerator {
    private $db;
    private $logger;
    private $analytics;

    const FORMAT_JSON = 'json';
    const FORMAT_CSV = 'csv';
    const FORMAT_HTML = 'html';

    const REPORT_REVENUE = 'revenue';
    const REPORT_USERS = 'users';
    const REPORT_SESSIONS = 'sessions';
    const REPORT_PAYMENTS = 'payments';
    const REPORT_SUBSCRIPTIONS = 'subscriptions';
    const REPORT_COMPREHENSIVE = 'comprehensive';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
        $this->analytics = new AnalyticsService();
    }

    /**
     * Generate report
     */
    public function generate(string $reportType, array $options = []) {
        $startDate = $options['start_date'] ?? date('Y-m-01'); // First day of month
        $endDate = $options['end_date'] ?? date('Y-m-d');
        $format = $options['format'] ?? self::FORMAT_JSON;

        $data = $this->getReportData($reportType, $startDate, $endDate, $options);

        switch ($format) {
            case self::FORMAT_CSV:
                return $this->formatAsCSV($data, $reportType);
            case self::FORMAT_HTML:
                return $this->formatAsHTML($data, $reportType);
            case self::FORMAT_JSON:
            default:
                return json_encode($data, JSON_PRETTY_PRINT);
        }
    }

    /**
     * Get report data based on type
     */
    private function getReportData(string $reportType, string $startDate, string $endDate, array $options) {
        switch ($reportType) {
            case self::REPORT_REVENUE:
                return $this->generateRevenueReport($startDate, $endDate);

            case self::REPORT_USERS:
                return $this->generateUsersReport($startDate, $endDate);

            case self::REPORT_SESSIONS:
                return $this->generateSessionsReport($startDate, $endDate);

            case self::REPORT_PAYMENTS:
                return $this->generatePaymentsReport($startDate, $endDate);

            case self::REPORT_SUBSCRIPTIONS:
                return $this->generateSubscriptionsReport($startDate, $endDate);

            case self::REPORT_COMPREHENSIVE:
                return $this->analytics->generateComprehensiveReport($startDate, $endDate);

            default:
                throw new Exception('Invalid report type');
        }
    }

    /**
     * Generate revenue report
     */
    private function generateRevenueReport(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT
                DATE(created_at) as date,
                payment_method,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as revenue,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
            FROM payments
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), payment_method
            ORDER BY date ASC, revenue DESC
        ');

        $stmt->execute([$startDate, $endDate]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $totalRevenue = array_sum(array_column($details, 'revenue'));
        $totalTransactions = array_sum(array_column($details, 'transaction_count'));

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_revenue' => (float)$totalRevenue,
                'total_transactions' => (int)$totalTransactions,
                'average_transaction' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0
            ],
            'details' => $details
        ];
    }

    /**
     * Generate users report
     */
    private function generateUsersReport(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT
                DATE(created_at) as date,
                role,
                status,
                COUNT(*) as user_count
            FROM users
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), role, status
            ORDER BY date ASC
        ');

        $stmt->execute([$startDate, $endDate]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get current totals
        $totalUsers = $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $activeUsers = $this->db->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'new_users' => array_sum(array_column($details, 'user_count')),
                'total_users' => (int)$totalUsers,
                'active_users' => (int)$activeUsers
            ],
            'details' => $details
        ];
    }

    /**
     * Generate sessions report
     */
    private function generateSessionsReport(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT
                DATE(s.created_at) as date,
                c.name as controller_name,
                COUNT(*) as session_count,
                AVG(TIMESTAMPDIFF(MINUTE, s.created_at, s.ended_at)) as avg_duration_minutes,
                SUM(s.bytes_uploaded) / 1024 / 1024 / 1024 as total_upload_gb,
                SUM(s.bytes_downloaded) / 1024 / 1024 / 1024 as total_download_gb
            FROM sessions s
            LEFT JOIN controllers c ON s.controller_id = c.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            GROUP BY DATE(s.created_at), c.id
            ORDER BY date ASC
        ');

        $stmt->execute([$startDate, $endDate]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_sessions' => array_sum(array_column($details, 'session_count')),
                'total_upload_gb' => array_sum(array_column($details, 'total_upload_gb')),
                'total_download_gb' => array_sum(array_column($details, 'total_download_gb'))
            ],
            'details' => $details
        ];
    }

    /**
     * Generate payments report
     */
    private function generatePaymentsReport(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.user_id,
                u.username,
                u.email,
                p.amount,
                p.payment_method,
                p.status,
                p.created_at,
                pl.name as plan_name
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN plans pl ON p.plan_id = pl.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            ORDER BY p.created_at DESC
        ');

        $stmt->execute([$startDate, $endDate]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $completed = array_filter($details, fn($p) => $p['status'] === 'completed');
        $failed = array_filter($details, fn($p) => $p['status'] === 'failed');

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_payments' => count($details),
                'completed' => count($completed),
                'failed' => count($failed),
                'total_amount' => array_sum(array_column($completed, 'amount')),
                'success_rate' => count($details) > 0 ? round((count($completed) / count($details)) * 100, 2) : 0
            ],
            'details' => $details
        ];
    }

    /**
     * Generate subscriptions report
     */
    private function generateSubscriptionsReport(string $startDate, string $endDate) {
        $stmt = $this->db->prepare('
            SELECT
                s.id,
                u.username,
                u.email,
                p.name as plan_name,
                p.price,
                s.status,
                s.start_date,
                s.end_date,
                s.auto_renew,
                s.created_at
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN plans p ON s.plan_id = p.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            ORDER BY s.created_at DESC
        ');

        $stmt->execute([$startDate, $endDate]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $active = array_filter($details, fn($s) => $s['status'] === 'active');
        $cancelled = array_filter($details, fn($s) => $s['status'] === 'cancelled');

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_subscriptions' => count($details),
                'active' => count($active),
                'cancelled' => count($cancelled),
                'mrr' => array_sum(array_column($active, 'price')) // Monthly Recurring Revenue
            ],
            'details' => $details
        ];
    }

    /**
     * Format data as CSV
     */
    private function formatAsCSV(array $data, string $reportType) {
        if (empty($data['details'])) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write headers
        $headers = array_keys($data['details'][0]);
        fputcsv($output, $headers);

        // Write data rows
        foreach ($data['details'] as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Format data as HTML
     */
    private function formatAsHTML(array $data, string $reportType) {
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<title>' . ucfirst($reportType) . ' Report</title>';
        $html .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4CAF50; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .summary { background-color: #e7f3fe; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        </style>';
        $html .= '</head><body>';
        $html .= '<h1>' . ucfirst($reportType) . ' Report</h1>';

        // Summary section
        if (isset($data['summary'])) {
            $html .= '<div class="summary"><h2>Summary</h2>';
            foreach ($data['summary'] as $key => $value) {
                $html .= '<p><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</p>';
            }
            $html .= '</div>';
        }

        // Details table
        if (isset($data['details']) && !empty($data['details'])) {
            $html .= '<h2>Details</h2><table>';

            // Headers
            $html .= '<tr>';
            foreach (array_keys($data['details'][0]) as $header) {
                $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
            }
            $html .= '</tr>';

            // Rows
            foreach ($data['details'] as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Save report to file
     */
    public function saveToFile(string $reportType, array $options = []) {
        $format = $options['format'] ?? self::FORMAT_JSON;
        $content = $this->generate($reportType, $options);

        $filename = 'reports/' . $reportType . '_' . date('Y-m-d_His') . '.' . $format;
        $filepath = __DIR__ . '/../../../storage/' . $filename;

        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filepath, $content);

        $this->logger->info('Report generated', [
            'type' => $reportType,
            'format' => $format,
            'file' => $filename
        ]);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    }
}
