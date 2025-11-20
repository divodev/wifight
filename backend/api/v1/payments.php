<?php
/**
 * WiFight ISP System - Payments API
 *
 * Endpoints for payment processing
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/RateLimit.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

// Apply security headers
$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

// Apply rate limiting
$rateLimit = new RateLimit();
$rateLimit->middleware('api');

// Initialize utilities
$db = Database::getInstance()->getConnection();
$auth = new Auth();
$logger = new Logger();

// Get request method and parse route
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));

// Extract payment ID if present
$paymentId = isset($parts[3]) && is_numeric($parts[3]) ? (int)$parts[3] : null;
$action = isset($parts[4]) ? $parts[4] : null;

// Log request
$logger->logRequest($method, $requestUri);

try {
    switch ($method) {
        case 'GET':
            if ($paymentId) {
                // GET /api/v1/payments/{id} - Get payment details
                handleGetPayment($paymentId);
            } else {
                // GET /api/v1/payments - List payments
                handleListPayments();
            }
            break;

        case 'POST':
            if (!$paymentId) {
                // POST /api/v1/payments - Create payment
                handleCreatePayment();
            } elseif ($action === 'refund') {
                // POST /api/v1/payments/{id}/refund - Refund payment
                handleRefundPayment($paymentId);
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    $logger->error('Payments API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

// =============================================================================
// HANDLER FUNCTIONS
// =============================================================================

function handleListPayments() {
    global $auth, $db;

    $user = $auth->requireAuth();

    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $status = $_GET['status'] ?? null;

    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $where = [];
    $params = [];

    // Regular users can only see their own payments
    if ($user['role'] === 'user') {
        $where[] = 'user_id = ?';
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'reseller') {
        // Resellers see payments from their users
        $where[] = 'EXISTS (SELECT 1 FROM users u WHERE u.id = payments.user_id AND u.created_by = ?)';
        $params[] = $user['user_id'];
    }

    if ($status) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM payments $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get payments
    $stmt = $db->prepare("
        SELECT p.id, p.user_id, u.username, u.email, p.amount, p.currency,
               p.payment_method, p.status, p.transaction_id, p.description,
               p.created_at
        FROM payments p
        JOIN users u ON p.user_id = u.id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($payments as &$payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['user_id'] = (int)$payment['user_id'];
        $payment['amount'] = (float)$payment['amount'];
    }

    Response::paginated($payments, $totalRows, $page, $limit);
}

function handleGetPayment($paymentId) {
    global $auth, $db;

    $user = $auth->requireAuth();

    $stmt = $db->prepare('
        SELECT p.*, u.username, u.email
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        Response::error('Payment not found', 404);
        return;
    }

    // Check permissions
    if ($user['role'] === 'user' && $payment['user_id'] != $user['user_id']) {
        Response::error('Access denied', 403);
        return;
    }

    // Format response
    $payment['id'] = (int)$payment['id'];
    $payment['user_id'] = (int)$payment['user_id'];
    $payment['amount'] = (float)$payment['amount'];

    Response::success('Payment retrieved successfully', $payment);
}

function handleCreatePayment() {
    global $auth, $db, $logger;

    $user = $auth->requireAuth();

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['amount']) || empty($input['payment_method'])) {
        Response::error('amount and payment_method required', 400);
        return;
    }

    try {
        $stmt = $db->prepare('
            INSERT INTO payments (user_id, amount, currency, payment_method, status, transaction_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $transactionId = 'PAY-' . time() . '-' . $user['user_id'];

        $stmt->execute([
            $user['user_id'],
            $input['amount'],
            $input['currency'] ?? 'USD',
            $input['payment_method'],
            'pending',
            $transactionId,
            $input['description'] ?? 'Payment'
        ]);

        $paymentId = $db->lastInsertId();

        $logger->info('Payment created', ['payment_id' => $paymentId, 'user_id' => $user['user_id']]);

        Response::success('Payment created successfully', [
            'payment_id' => (int)$paymentId,
            'transaction_id' => $transactionId,
            'amount' => (float)$input['amount']
        ], 201);

    } catch (PDOException $e) {
        $logger->error('Payment creation failed: ' . $e->getMessage());
        Response::error('Failed to create payment', 500);
    }
}

function handleRefundPayment($paymentId) {
    global $auth, $db, $logger;

    $user = $auth->requireRole(['admin']);

    $input = json_decode(file_get_contents('php://input'), true);

    // Get payment
    $stmt = $db->prepare('SELECT * FROM payments WHERE id = ? AND status = ?');
    $stmt->execute([$paymentId, 'completed']);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        Response::error('Payment not found or cannot be refunded', 404);
        return;
    }

    $refundAmount = isset($input['amount']) ? (float)$input['amount'] : (float)$payment['amount'];

    if ($refundAmount > (float)$payment['amount']) {
        Response::error('Refund amount exceeds payment amount', 400);
        return;
    }

    try {
        $db->beginTransaction();

        // Update payment status
        $stmt = $db->prepare('UPDATE payments SET status = ? WHERE id = ?');
        $stmt->execute(['refunded', $paymentId]);

        // Add refund amount back to user balance
        $stmt = $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$refundAmount, $payment['user_id']]);

        $db->commit();

        $logger->info('Payment refunded', [
            'payment_id' => $paymentId,
            'amount' => $refundAmount,
            'admin_id' => $user['user_id']
        ]);

        Response::success('Payment refunded successfully', [
            'refund_amount' => $refundAmount
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->error('Payment refund failed: ' . $e->getMessage());
        Response::error('Failed to refund payment', 500);
    }
}
