<?php
/**
 * WiFight ISP System - Analytics API
 *
 * Business intelligence and analytics endpoints
 */

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../services/analytics/AnalyticsService.php';
require_once __DIR__ . '/../../services/analytics/ReportGenerator.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

$auth = new Auth();
$analytics = new AnalyticsService();
$reportGenerator = new ReportGenerator();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$action = isset($parts[3]) ? $parts[3] : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'dashboard') {
                // GET /api/v1/analytics/dashboard - Dashboard overview
                handleDashboard();
            } elseif ($action === 'revenue') {
                // GET /api/v1/analytics/revenue - Revenue analytics
                handleRevenue();
            } elseif ($action === 'users') {
                // GET /api/v1/analytics/users - User analytics
                handleUsers();
            } elseif ($action === 'sessions') {
                // GET /api/v1/analytics/sessions - Session analytics
                handleSessions();
            } elseif ($action === 'payments') {
                // GET /api/v1/analytics/payments - Payment analytics
                handlePayments();
            } elseif ($action === 'bandwidth') {
                // GET /api/v1/analytics/bandwidth - Bandwidth analytics
                handleBandwidth();
            } elseif ($action === 'controllers') {
                // GET /api/v1/analytics/controllers - Controller metrics
                handleControllers();
            } elseif ($action === 'vouchers') {
                // GET /api/v1/analytics/vouchers - Voucher statistics
                handleVouchers();
            } elseif ($action === 'activity') {
                // GET /api/v1/analytics/activity - Activity feed
                handleActivity();
            } elseif ($action === 'top-plans') {
                // GET /api/v1/analytics/top-plans - Top plans by revenue
                handleTopPlans();
            } elseif ($action === 'top-users') {
                // GET /api/v1/analytics/top-users - Top users by revenue
                handleTopUsers();
            } elseif ($action === 'churn') {
                // GET /api/v1/analytics/churn - Churn rate
                handleChurn();
            } elseif ($action === 'clv') {
                // GET /api/v1/analytics/clv - Customer lifetime value
                handleCLV();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'POST':
            if ($action === 'report') {
                // POST /api/v1/analytics/report - Generate report
                handleGenerateReport();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Internal server error', 500);
}

function handleDashboard() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);
    $data = $analytics->getDashboardOverview();

    Response::success('Dashboard metrics retrieved', $data);
}

function handleRevenue() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $days = isset($_GET['days']) ? min((int)$_GET['days'], 365) : 30;

    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $revenue = $analytics->getRevenue($_GET['start_date'], $_GET['end_date']);
        $data = [
            'period' => ['start' => $_GET['start_date'], 'end' => $_GET['end_date']],
            'total_revenue' => (float)$revenue
        ];
    } else {
        $data = $analytics->getRevenueTrend($days);
    }

    Response::success('Revenue analytics retrieved', $data);
}

function handleUsers() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $days = isset($_GET['days']) ? min((int)$_GET['days'], 365) : 30;
    $data = $analytics->getUserGrowth($days);

    Response::success('User analytics retrieved', $data);
}

function handleSessions() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $days = isset($_GET['days']) ? min((int)$_GET['days'], 90) : 7;
    $data = $analytics->getSessionStatistics($days);

    Response::success('Session analytics retrieved', $data);
}

function handlePayments() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $data = $analytics->getPaymentMethodStats();

    Response::success('Payment analytics retrieved', $data);
}

function handleBandwidth() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $days = isset($_GET['days']) ? min((int)$_GET['days'], 90) : 7;
    $data = $analytics->getBandwidthAnalytics($days);

    Response::success('Bandwidth analytics retrieved', $data);
}

function handleControllers() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $data = $analytics->getControllerMetrics();

    Response::success('Controller metrics retrieved', $data);
}

function handleVouchers() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $data = $analytics->getVoucherStats();

    Response::success('Voucher statistics retrieved', $data);
}

function handleActivity() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin']);

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $data = $analytics->getActivityFeed($limit);

    Response::success('Activity feed retrieved', [
        'activities' => $data,
        'count' => count($data)
    ]);
}

function handleTopPlans() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
    $data = $analytics->getTopPlansByRevenue($limit);

    Response::success('Top plans retrieved', $data);
}

function handleTopUsers() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
    $data = $analytics->getTopUsersByRevenue($limit);

    Response::success('Top users retrieved', $data);
}

function handleChurn() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $days = isset($_GET['days']) ? min((int)$_GET['days'], 365) : 30;
    $data = $analytics->getChurnRate($days);

    Response::success('Churn rate retrieved', $data);
}

function handleCLV() {
    global $auth, $analytics;

    $user = $auth->requireRole(['admin', 'reseller']);

    $data = $analytics->getCustomerLifetimeValue();

    Response::success('Customer lifetime value retrieved', $data);
}

function handleGenerateReport() {
    global $auth, $reportGenerator;

    $user = $auth->requireRole(['admin']);

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new Validator();
    $errors = $validator->validate($input, [
        'report_type' => 'required',
        'format' => 'in:json,csv,html'
    ]);

    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    $validReportTypes = [
        ReportGenerator::REPORT_REVENUE,
        ReportGenerator::REPORT_USERS,
        ReportGenerator::REPORT_SESSIONS,
        ReportGenerator::REPORT_PAYMENTS,
        ReportGenerator::REPORT_SUBSCRIPTIONS,
        ReportGenerator::REPORT_COMPREHENSIVE
    ];

    if (!in_array($input['report_type'], $validReportTypes)) {
        Response::error('Invalid report type', 400);
        return;
    }

    $options = [
        'format' => $input['format'] ?? 'json',
        'start_date' => $input['start_date'] ?? date('Y-m-01'),
        'end_date' => $input['end_date'] ?? date('Y-m-d')
    ];

    // Generate and save report
    if (isset($input['save']) && $input['save'] === true) {
        $result = $reportGenerator->saveToFile($input['report_type'], $options);
        Response::success('Report generated and saved', $result);
    } else {
        // Return report data directly
        $content = $reportGenerator->generate($input['report_type'], $options);

        if ($options['format'] === 'json') {
            Response::success('Report generated', json_decode($content, true));
        } else {
            // For CSV/HTML, return raw content with appropriate headers
            if ($options['format'] === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="report.csv"');
            } elseif ($options['format'] === 'html') {
                header('Content-Type: text/html');
            }
            echo $content;
            exit;
        }
    }
}
