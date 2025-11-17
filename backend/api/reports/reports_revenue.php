<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Payment.php';
require_once '../../models/Voucher.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

// Authenticate user
$payload = JWT::authenticate();

// Check admin/manager role
if ($payload['role'] !== 'admin' && $payload['role'] !== 'manager') {
    Response::error('Unauthorized access', 403);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$payment = new Payment($db);
$voucher = new Voucher($db);

// Get date range from query params
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get payment statistics
$paymentStats = $payment->getStats($startDate, $endDate);

// Get voucher statistics
$voucherStats = $voucher->getStats();

// Calculate daily revenue
$query = "SELECT 
            DATE(created_at) as date,
            SUM(amount) as revenue,
            COUNT(*) as transactions
          FROM payments
          WHERE status = 'completed'
          AND created_at BETWEEN :start_date AND :end_date
          GROUP BY DATE(created_at)
          ORDER BY date ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate revenue by payment method
$query = "SELECT 
            payment_method,
            SUM(amount) as revenue,
            COUNT(*) as transactions
          FROM payments
          WHERE status = 'completed'
          AND created_at BETWEEN :start_date AND :end_date
          GROUP BY payment_method
          ORDER BY revenue DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$revenueByMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate revenue by plan
$query = "SELECT 
            p.name as plan_name,
            SUM(pay.amount) as revenue,
            COUNT(*) as transactions
          FROM payments pay
          JOIN plans p ON pay.plan_id = p.id
          WHERE pay.status = 'completed'
          AND pay.created_at BETWEEN :start_date AND :end_date
          GROUP BY pay.plan_id
          ORDER BY revenue DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$revenueByPlan = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::success([
    'summary' => [
        'total_revenue' => $paymentStats['total_revenue'],
        'total_transactions' => $paymentStats['total_transactions'],
        'completed_transactions' => $paymentStats['completed'],
        'pending_transactions' => $paymentStats['pending'],
        'failed_transactions' => $paymentStats['failed'],
        'average_transaction' => $paymentStats['average_transaction'],
        'voucher_revenue' => $voucherStats['total_revenue']
    ],
    'daily_revenue' => $dailyRevenue,
    'revenue_by_method' => $revenueByMethod,
    'revenue_by_plan' => $revenueByPlan,
    'date_range' => [
        'start' => $startDate,
        'end' => $endDate
    ]
], 'Revenue report generated successfully');
?>