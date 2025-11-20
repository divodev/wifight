<?php
/**
 * WiFight ISP System - Plans API
 *
 * Endpoints for managing internet plans
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Validator.php';
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
$validator = new Validator();
$logger = new Logger();

// Get request method and parse route
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($requestUri, '/'));

// Extract plan ID if present
$planId = isset($parts[3]) && is_numeric($parts[3]) ? (int)$parts[3] : null;
$action = isset($parts[4]) ? $parts[4] : null;

// Log request
$logger->logRequest($method, $requestUri);

try {
    // Route handlers
    switch ($method) {
        case 'GET':
            if ($planId && $action === 'subscribers') {
                // GET /api/v1/plans/{id}/subscribers - Get plan subscribers
                handleGetPlanSubscribers($planId);
            } elseif ($planId) {
                // GET /api/v1/plans/{id} - Get single plan
                handleGetPlan($planId);
            } else {
                // GET /api/v1/plans - List all plans
                handleListPlans();
            }
            break;

        case 'POST':
            if ($planId && $action === 'subscribe') {
                // POST /api/v1/plans/{id}/subscribe - Subscribe user to plan
                handleSubscribeToPlan($planId);
            } elseif (!$planId) {
                // POST /api/v1/plans - Create new plan
                handleCreatePlan();
            } else {
                Response::error('Invalid endpoint', 404);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if ($planId) {
                // PUT /api/v1/plans/{id} - Update plan
                handleUpdatePlan($planId);
            } else {
                Response::error('Plan ID required', 400);
            }
            break;

        case 'DELETE':
            if ($planId) {
                // DELETE /api/v1/plans/{id} - Delete plan
                handleDeletePlan($planId);
            } else {
                Response::error('Plan ID required', 400);
            }
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    $logger->error('Plans API error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

// =============================================================================
// HANDLER FUNCTIONS
// =============================================================================

/**
 * List all plans with pagination and filtering
 */
function handleListPlans() {
    global $db, $validator;

    // No authentication required for public plans listing
    // But can filter by status if user is not admin

    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $status = $_GET['status'] ?? 'active';
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $minBandwidth = isset($_GET['min_bandwidth']) ? (int)$_GET['min_bandwidth'] : null;
    $isUnlimited = isset($_GET['unlimited']) ? filter_var($_GET['unlimited'], FILTER_VALIDATE_BOOLEAN) : null;

    // Validate pagination
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $where = ['status = ?'];
    $params = [$status];

    if ($minPrice !== null) {
        $where[] = 'price >= ?';
        $params[] = $minPrice;
    }

    if ($maxPrice !== null) {
        $where[] = 'price <= ?';
        $params[] = $maxPrice;
    }

    if ($minBandwidth !== null) {
        $where[] = 'bandwidth_down >= ?';
        $params[] = $minBandwidth;
    }

    if ($isUnlimited !== null) {
        $where[] = 'is_unlimited = ?';
        $params[] = $isUnlimited ? 1 : 0;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM plans $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get plans
    $stmt = $db->prepare("
        SELECT id, name, description, bandwidth_up, bandwidth_down, duration_days,
               price, currency, status, max_devices, data_limit_gb, is_unlimited,
               created_at, updated_at
        FROM plans
        $whereClause
        ORDER BY price ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($plans as &$plan) {
        $plan['id'] = (int)$plan['id'];
        $plan['bandwidth_up'] = (int)$plan['bandwidth_up'];
        $plan['bandwidth_down'] = (int)$plan['bandwidth_down'];
        $plan['duration_days'] = (int)$plan['duration_days'];
        $plan['price'] = (float)$plan['price'];
        $plan['max_devices'] = (int)$plan['max_devices'];
        $plan['data_limit_gb'] = $plan['data_limit_gb'] ? (int)$plan['data_limit_gb'] : null;
        $plan['is_unlimited'] = (bool)$plan['is_unlimited'];
    }

    Response::paginated($plans, $totalRows, $page, $limit);
}

/**
 * Get single plan details
 */
function handleGetPlan($planId) {
    global $db;

    $stmt = $db->prepare('
        SELECT id, name, description, bandwidth_up, bandwidth_down, duration_days,
               price, currency, status, max_devices, data_limit_gb, is_unlimited,
               created_at, updated_at
        FROM plans
        WHERE id = ?
    ');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        Response::error('Plan not found', 404);
        return;
    }

    // Format response
    $plan['id'] = (int)$plan['id'];
    $plan['bandwidth_up'] = (int)$plan['bandwidth_up'];
    $plan['bandwidth_down'] = (int)$plan['bandwidth_down'];
    $plan['duration_days'] = (int)$plan['duration_days'];
    $plan['price'] = (float)$plan['price'];
    $plan['max_devices'] = (int)$plan['max_devices'];
    $plan['data_limit_gb'] = $plan['data_limit_gb'] ? (int)$plan['data_limit_gb'] : null;
    $plan['is_unlimited'] = (bool)$plan['is_unlimited'];

    // Get subscriber count
    $subsStmt = $db->prepare('
        SELECT COUNT(*) as subscriber_count
        FROM subscriptions
        WHERE plan_id = ? AND status = ?
    ');
    $subsStmt->execute([$planId, 'active']);
    $plan['active_subscribers'] = (int)$subsStmt->fetch(PDO::FETCH_ASSOC)['subscriber_count'];

    Response::success('Plan retrieved successfully', $plan);
}

/**
 * Create new plan
 */
function handleCreatePlan() {
    global $auth, $db, $validator, $logger;

    // Only admin can create plans
    $user = $auth->requireRole(['admin']);

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Define validation rules
    $rules = [
        'name' => ['required' => true, 'max' => 100],
        'description' => ['max' => 500],
        'bandwidth_up' => ['required' => true, 'numeric' => true],
        'bandwidth_down' => ['required' => true, 'numeric' => true],
        'duration_days' => ['required' => true, 'numeric' => true],
        'price' => ['required' => true, 'numeric' => true],
        'currency' => ['required' => true, 'max' => 3],
        'max_devices' => ['required' => true, 'numeric' => true],
        'data_limit_gb' => ['numeric' => true],
        'is_unlimited' => ['required' => true]
    ];

    // Validate input
    $errors = $validator->validate($input, $rules);
    if (!empty($errors)) {
        Response::validationError($errors);
        return;
    }

    // Insert plan
    try {
        $stmt = $db->prepare('
            INSERT INTO plans (name, description, bandwidth_up, bandwidth_down, duration_days,
                              price, currency, status, max_devices, data_limit_gb, is_unlimited)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $input['name'],
            $input['description'] ?? null,
            $input['bandwidth_up'],
            $input['bandwidth_down'],
            $input['duration_days'],
            $input['price'],
            $input['currency'],
            $input['status'] ?? 'active',
            $input['max_devices'],
            $input['data_limit_gb'] ?? null,
            filter_var($input['is_unlimited'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0
        ]);

        $planId = $db->lastInsertId();

        $logger->info('Plan created', ['plan_id' => $planId, 'user_id' => $user['user_id']]);

        Response::success('Plan created successfully', [
            'id' => (int)$planId,
            'name' => $input['name'],
            'price' => (float)$input['price'],
            'currency' => $input['currency']
        ], 201);

    } catch (PDOException $e) {
        $logger->error('Plan creation failed: ' . $e->getMessage());
        Response::error('Failed to create plan', 500);
    }
}

/**
 * Update plan
 */
function handleUpdatePlan($planId) {
    global $auth, $db, $validator, $logger;

    // Only admin can update plans
    $user = $auth->requireRole(['admin']);

    // Check plan exists
    $stmt = $db->prepare('SELECT id FROM plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        Response::error('Plan not found', 404);
        return;
    }

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::error('Invalid JSON input', 400);
        return;
    }

    // Build update query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'name', 'description', 'bandwidth_up', 'bandwidth_down', 'duration_days',
        'price', 'currency', 'status', 'max_devices', 'data_limit_gb', 'is_unlimited'
    ];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            if ($field === 'is_unlimited') {
                $updates[] = "$field = ?";
                $params[] = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } else {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
    }

    if (empty($updates)) {
        Response::error('No fields to update', 400);
        return;
    }

    $updates[] = 'updated_at = NOW()';
    $params[] = $planId;

    try {
        $stmt = $db->prepare('UPDATE plans SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        $logger->info('Plan updated', ['plan_id' => $planId, 'user_id' => $user['user_id']]);

        Response::success('Plan updated successfully');

    } catch (PDOException $e) {
        $logger->error('Plan update failed: ' . $e->getMessage());
        Response::error('Failed to update plan', 500);
    }
}

/**
 * Delete plan
 */
function handleDeletePlan($planId) {
    global $auth, $db, $logger;

    // Only admin can delete plans
    $user = $auth->requireRole(['admin']);

    // Check plan exists
    $stmt = $db->prepare('SELECT id FROM plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        Response::error('Plan not found', 404);
        return;
    }

    // Check for active subscriptions
    $stmt = $db->prepare('SELECT COUNT(*) as active_count FROM subscriptions WHERE plan_id = ? AND status = ?');
    $stmt->execute([$planId, 'active']);
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

    if ($activeCount > 0) {
        Response::error("Cannot delete plan with $activeCount active subscriptions. Set status to 'inactive' instead.", 400);
        return;
    }

    try {
        $stmt = $db->prepare('DELETE FROM plans WHERE id = ?');
        $stmt->execute([$planId]);

        $logger->info('Plan deleted', ['plan_id' => $planId, 'user_id' => $user['user_id']]);

        Response::success('Plan deleted successfully');

    } catch (PDOException $e) {
        $logger->error('Plan deletion failed: ' . $e->getMessage());
        Response::error('Failed to delete plan', 500);
    }
}

/**
 * Subscribe user to plan
 */
function handleSubscribeToPlan($planId) {
    global $auth, $db, $validator, $logger;

    // User must be authenticated
    $user = $auth->requireAuth();

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);

    // Get plan details
    $stmt = $db->prepare('
        SELECT id, name, price, duration_days, status
        FROM plans
        WHERE id = ?
    ');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        Response::error('Plan not found', 404);
        return;
    }

    if ($plan['status'] !== 'active') {
        Response::error('Plan is not available', 400);
        return;
    }

    // Check user balance
    $stmt = $db->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$user['user_id']]);
    $userBalance = (float)$stmt->fetch(PDO::FETCH_ASSOC)['balance'];

    if ($userBalance < (float)$plan['price']) {
        Response::error('Insufficient balance', 400, [
            'required' => (float)$plan['price'],
            'available' => $userBalance,
            'shortage' => (float)$plan['price'] - $userBalance
        ]);
        return;
    }

    // Check for existing active subscription
    $stmt = $db->prepare('
        SELECT COUNT(*) as active_count
        FROM subscriptions
        WHERE user_id = ? AND plan_id = ? AND status = ?
    ');
    $stmt->execute([$user['user_id'], $planId, 'active']);
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

    if ($activeCount > 0) {
        Response::error('You already have an active subscription to this plan', 400);
        return;
    }

    // Start transaction
    $db->beginTransaction();

    try {
        // Create subscription
        $stmt = $db->prepare('
            INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date, billing_cycle, payment_method)
            VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?)
        ');
        $stmt->execute([
            $user['user_id'],
            $planId,
            'active',
            $plan['duration_days'],
            'monthly',
            'balance'
        ]);

        $subscriptionId = $db->lastInsertId();

        // Deduct from user balance
        $stmt = $db->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
        $stmt->execute([$plan['price'], $user['user_id']]);

        // Create payment record
        $stmt = $db->prepare('
            INSERT INTO payments (user_id, amount, currency, payment_method, status, transaction_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $user['user_id'],
            $plan['price'],
            'USD',
            'balance',
            'completed',
            'SUB-' . $subscriptionId . '-' . time(),
            'Subscription to ' . $plan['name']
        ]);

        $db->commit();

        $logger->info('User subscribed to plan', [
            'user_id' => $user['user_id'],
            'plan_id' => $planId,
            'subscription_id' => $subscriptionId
        ]);

        Response::success('Subscribed to plan successfully', [
            'subscription_id' => (int)$subscriptionId,
            'plan_name' => $plan['name'],
            'amount_paid' => (float)$plan['price'],
            'new_balance' => $userBalance - (float)$plan['price']
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->error('Subscription failed: ' . $e->getMessage());
        Response::error('Failed to subscribe to plan', 500);
    }
}

/**
 * Get plan subscribers
 */
function handleGetPlanSubscribers($planId) {
    global $auth, $db;

    // Only admin can see subscribers
    $user = $auth->requireRole(['admin']);

    // Check plan exists
    $stmt = $db->prepare('SELECT id FROM plans WHERE id = ?');
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        Response::error('Plan not found', 404);
        return;
    }

    // Get subscribers
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    // Get total count
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ?');
    $countStmt->execute([$planId]);
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get subscribers
    $stmt = $db->prepare('
        SELECT s.id, s.user_id, u.username, u.email, u.full_name,
               s.status, s.start_date, s.end_date, s.created_at
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.plan_id = ?
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$planId, $limit, $offset]);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    foreach ($subscribers as &$subscriber) {
        $subscriber['id'] = (int)$subscriber['id'];
        $subscriber['user_id'] = (int)$subscriber['user_id'];
    }

    Response::paginated($subscribers, $totalRows, $page, $limit);
}
