<?php
/**
 * WiFight ISP System - Webhooks API
 *
 * Webhook subscription management endpoints
 */

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../services/webhooks/WebhookManager.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

$auth = new Auth();
$webhookManager = new WebhookManager();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$action = isset($parts[3]) ? $parts[3] : null;
$id = isset($parts[4]) ? $parts[4] : null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'events') {
                // GET /api/v1/webhooks/events - List available events
                handleGetEvents();
            } elseif ($action === 'subscriptions') {
                // GET /api/v1/webhooks/subscriptions - List user's subscriptions
                handleGetSubscriptions();
            } elseif ($action === 'logs' && $id) {
                // GET /api/v1/webhooks/logs/{subscription_id} - Get delivery logs
                handleGetLogs();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'POST':
            if ($action === 'subscribe') {
                // POST /api/v1/webhooks/subscribe - Create webhook subscription
                handleSubscribe();
            } elseif ($action === 'test' && $id) {
                // POST /api/v1/webhooks/test/{subscription_id} - Test webhook
                handleTestWebhook();
            } elseif ($action === 'retry') {
                // POST /api/v1/webhooks/retry - Retry failed webhooks (admin only)
                handleRetryFailed();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'DELETE':
            if ($id) {
                // DELETE /api/v1/webhooks/{subscription_id} - Delete subscription
                handleDeleteSubscription();
            } else {
                Response::error('Subscription ID required', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Internal server error', 500);
}

function handleGetEvents() {
    global $auth;

    // Any authenticated user can see available events
    $user = $auth->requireAuth();

    $events = WebhookManager::getAvailableEvents();

    Response::success('Available webhook events', ['events' => $events]);
}

function handleGetSubscriptions() {
    global $auth;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    // Users see only their subscriptions, admins see all
    $whereClause = $user['role'] === 'admin' ? '1=1' : 'user_id = ?';
    $params = $user['role'] === 'admin' ? [] : [$user['id']];

    $stmt = $db->prepare("
        SELECT
            id,
            user_id,
            event,
            url,
            status,
            created_at,
            last_triggered_at
        FROM webhook_subscriptions
        WHERE {$whereClause}
        ORDER BY created_at DESC
    ");

    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success('Webhook subscriptions retrieved', [
        'subscriptions' => $subscriptions,
        'count' => count($subscriptions)
    ]);
}

function handleSubscribe() {
    global $auth, $webhookManager;

    $user = $auth->requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new Validator();
    $errors = $validator->validate($input, [
        'event' => 'required',
        'url' => 'required|url'
    ]);

    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    // Validate event is supported
    $availableEvents = array_keys(WebhookManager::getAvailableEvents());
    if (!in_array($input['event'], $availableEvents)) {
        Response::error('Invalid event type', 400);
        return;
    }

    // Create subscription
    $result = $webhookManager->createSubscription(
        $user['id'],
        $input['event'],
        $input['url'],
        $input['secret'] ?? null
    );

    if ($result['success']) {
        Response::success('Webhook subscription created', $result, 201);
    } else {
        Response::error($result['error'], 400);
    }
}

function handleDeleteSubscription() {
    global $auth, $webhookManager, $id;

    $user = $auth->requireAuth();

    $result = $webhookManager->deleteSubscription((int)$id, $user['id']);

    if ($result['success']) {
        Response::success('Webhook subscription deleted');
    } else {
        Response::error($result['error'], 400);
    }
}

function handleGetLogs() {
    global $auth, $id;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    // Verify ownership
    $stmt = $db->prepare('SELECT user_id FROM webhook_subscriptions WHERE id = ?');
    $stmt->execute([$id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        Response::error('Subscription not found', 404);
        return;
    }

    if ($subscription['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        Response::error('Access denied', 403);
        return;
    }

    // Get logs
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;

    $stmt = $db->prepare('
        SELECT
            id,
            event,
            url,
            http_code,
            success,
            response,
            error,
            created_at
        FROM webhook_logs
        WHERE subscription_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ');

    $stmt->execute([$id, $limit]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate success rate
    $successCount = count(array_filter($logs, fn($log) => $log['success'] == 1));
    $successRate = count($logs) > 0 ? round(($successCount / count($logs)) * 100, 2) : 0;

    Response::success('Webhook delivery logs', [
        'logs' => $logs,
        'count' => count($logs),
        'success_rate' => $successRate
    ]);
}

function handleTestWebhook() {
    global $auth, $webhookManager, $id;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    // Get subscription
    $stmt = $db->prepare('
        SELECT id, user_id, event, url, secret, headers
        FROM webhook_subscriptions
        WHERE id = ?
    ');
    $stmt->execute([$id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        Response::error('Subscription not found', 404);
        return;
    }

    if ($subscription['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        Response::error('Access denied', 403);
        return;
    }

    // Send test webhook
    $testPayload = [
        'test' => true,
        'message' => 'This is a test webhook from WiFight ISP',
        'timestamp' => time(),
        'user_id' => $user['id']
    ];

    $webhookPayload = [
        'event' => $subscription['event'],
        'timestamp' => time(),
        'data' => $testPayload
    ];

    // Generate signature
    $signature = hash_hmac('sha256', json_encode($webhookPayload), $subscription['secret']);

    // Prepare headers
    $headers = [
        'Content-Type: application/json',
        'X-WiFight-Event: ' . $subscription['event'],
        'X-WiFight-Signature: ' . $signature,
        'X-WiFight-Test: true',
        'User-Agent: WiFight-Webhook/1.0'
    ];

    // Send HTTP POST request
    $ch = curl_init($subscription['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($webhookPayload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300;

    Response::success('Test webhook sent', [
        'http_code' => $httpCode,
        'success' => $success,
        'response' => substr($response, 0, 500),
        'error' => $error ?: null
    ]);
}

function handleRetryFailed() {
    global $auth, $webhookManager;

    $user = $auth->requireRole(['admin']);

    $input = json_decode(file_get_contents('php://input'), true);
    $maxRetries = isset($input['max_retries']) ? (int)$input['max_retries'] : 3;

    $result = $webhookManager->retryFailedWebhooks($maxRetries);

    Response::success('Failed webhooks retry initiated', $result);
}
