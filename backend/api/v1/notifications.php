<?php
/**
 * WiFight ISP System - Notifications API
 *
 * User notification management endpoints
 */

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../services/notifications/NotificationService.php';
require_once __DIR__ . '/../../middleware/SecurityHeaders.php';

$securityHeaders = new SecurityHeaders();
$securityHeaders->apply();
$securityHeaders->applyJSONHeaders();

$auth = new Auth();
$notificationService = new NotificationService();

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));
$action = isset($parts[3]) ? $parts[3] : null;
$id = isset($parts[4]) ? $parts[4] : null;

try {
    switch ($method) {
        case 'GET':
            if (!$action || $action === 'list') {
                // GET /api/v1/notifications - Get user's notifications
                handleGetNotifications();
            } elseif ($action === 'unread') {
                // GET /api/v1/notifications/unread - Get unread notifications
                handleGetUnread();
            } elseif ($action === 'count') {
                // GET /api/v1/notifications/count - Get unread count
                handleGetUnreadCount();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'POST':
            if ($action === 'send') {
                // POST /api/v1/notifications/send - Send notification (admin/system)
                handleSendNotification();
            } elseif ($action === 'mark-read' && $id) {
                // POST /api/v1/notifications/mark-read/{id} - Mark as read
                handleMarkAsRead();
            } elseif ($action === 'mark-all-read') {
                // POST /api/v1/notifications/mark-all-read - Mark all as read
                handleMarkAllAsRead();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'DELETE':
            if ($id) {
                // DELETE /api/v1/notifications/{id} - Delete notification
                handleDeleteNotification();
            } elseif ($action === 'clear-all') {
                // DELETE /api/v1/notifications/clear-all - Clear all notifications
                handleClearAll();
            } else {
                Response::error('Notification ID required', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Internal server error', 500);
}

function handleGetNotifications() {
    global $auth, $notificationService;

    $user = $auth->requireAuth();

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

    $notifications = $notificationService->getUserNotifications($user['id'], $limit, $unreadOnly);

    Response::success('Notifications retrieved', [
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

function handleGetUnread() {
    global $auth, $notificationService;

    $user = $auth->requireAuth();

    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
    $notifications = $notificationService->getUserNotifications($user['id'], $limit, true);

    Response::success('Unread notifications retrieved', [
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

function handleGetUnreadCount() {
    global $auth;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare('
        SELECT COUNT(*) as count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ');

    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success('Unread count retrieved', [
        'unread_count' => (int)$result['count']
    ]);
}

function handleSendNotification() {
    global $auth, $notificationService;

    // Only admins can manually send notifications
    $user = $auth->requireRole(['admin']);

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new Validator();
    $errors = $validator->validate($input, [
        'user_id' => 'required|numeric',
        'type' => 'required',
        'template' => 'required'
    ]);

    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    // Validate notification type
    $validTypes = [
        NotificationService::TYPE_EMAIL,
        NotificationService::TYPE_SMS,
        NotificationService::TYPE_IN_APP,
        NotificationService::TYPE_PUSH
    ];

    if (!in_array($input['type'], $validTypes)) {
        Response::error('Invalid notification type', 400);
        return;
    }

    // Validate template
    $validTemplates = [
        NotificationService::TEMPLATE_WELCOME,
        NotificationService::TEMPLATE_PASSWORD_RESET,
        NotificationService::TEMPLATE_PAYMENT_SUCCESS,
        NotificationService::TEMPLATE_PAYMENT_FAILED,
        NotificationService::TEMPLATE_SUBSCRIPTION_EXPIRING,
        NotificationService::TEMPLATE_SUBSCRIPTION_EXPIRED,
        NotificationService::TEMPLATE_SESSION_STARTED,
        NotificationService::TEMPLATE_SESSION_ENDED,
        NotificationService::TEMPLATE_LOW_BALANCE
    ];

    if (!in_array($input['template'], $validTemplates)) {
        Response::error('Invalid notification template', 400);
        return;
    }

    $result = $notificationService->send(
        (int)$input['user_id'],
        $input['type'],
        $input['template'],
        $input['data'] ?? []
    );

    if ($result['success']) {
        Response::success('Notification sent successfully', $result);
    } else {
        Response::error($result['error'] ?? 'Failed to send notification', 400);
    }
}

function handleMarkAsRead() {
    global $auth, $notificationService, $id;

    $user = $auth->requireAuth();

    $result = $notificationService->markAsRead((int)$id, $user['id']);

    if ($result['success']) {
        Response::success('Notification marked as read');
    } else {
        Response::error($result['error'] ?? 'Failed to mark notification as read', 400);
    }
}

function handleMarkAllAsRead() {
    global $auth;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    try {
        $stmt = $db->prepare('
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ');

        $stmt->execute([$user['id']]);
        $updated = $stmt->rowCount();

        Response::success('All notifications marked as read', [
            'updated_count' => $updated
        ]);

    } catch (Exception $e) {
        Response::error('Failed to mark all as read', 500);
    }
}

function handleDeleteNotification() {
    global $auth, $id;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    try {
        $stmt = $db->prepare('
            DELETE FROM notifications
            WHERE id = ? AND user_id = ?
        ');

        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() > 0) {
            Response::success('Notification deleted');
        } else {
            Response::error('Notification not found', 404);
        }

    } catch (Exception $e) {
        Response::error('Failed to delete notification', 500);
    }
}

function handleClearAll() {
    global $auth;

    $user = $auth->requireAuth();
    $db = Database::getInstance()->getConnection();

    try {
        // Only delete read notifications
        $stmt = $db->prepare('
            DELETE FROM notifications
            WHERE user_id = ? AND is_read = 1
        ');

        $stmt->execute([$user['id']]);
        $deleted = $stmt->rowCount();

        Response::success('Read notifications cleared', [
            'deleted_count' => $deleted
        ]);

    } catch (Exception $e) {
        Response::error('Failed to clear notifications', 500);
    }
}
