<?php
/**
 * WiFight ISP System - Stripe Webhook Handler
 *
 * Processes Stripe webhook events
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../services/payments/StripeGateway.php';

// Initialize
$db = Database::getInstance()->getConnection();
$logger = new Logger();

// Get webhook payload and signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    // Initialize Stripe gateway
    $stripeConfig = [
        'secret_key' => getenv('STRIPE_SECRET_KEY'),
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET')
    ];
    $stripe = new StripeGateway($stripeConfig);

    // Verify webhook signature
    if (!$stripe->verifyWebhookSignature($payload, $signature)) {
        $logger->warning('Stripe webhook signature verification failed');
        http_response_code(400);
        exit;
    }

    $event = json_decode($payload, true);

    // Log webhook event
    $logger->info('Stripe webhook received', ['type' => $event['type']]);

    // Handle event
    switch ($event['type']) {
        case 'payment_intent.succeeded':
            handlePaymentSuccess($event['data']['object']);
            break;

        case 'payment_intent.payment_failed':
            handlePaymentFailed($event['data']['object']);
            break;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            handleSubscriptionUpdate($event['data']['object']);
            break;

        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object']);
            break;

        case 'invoice.payment_succeeded':
            handleInvoicePaymentSucceeded($event['data']['object']);
            break;

        case 'invoice.payment_failed':
            handleInvoicePaymentFailed($event['data']['object']);
            break;

        default:
            $logger->info('Unhandled webhook event', ['type' => $event['type']]);
    }

    // Return 200 OK
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $logger->error('Stripe webhook processing error: ' . $e->getMessage());
    http_response_code(500);
}

// =============================================================================
// EVENT HANDLERS
// =============================================================================

function handlePaymentSuccess($paymentIntent) {
    global $db, $logger;

    $metadata = $paymentIntent['metadata'] ?? [];

    try {
        // Update payment status in database
        $stmt = $db->prepare('
            UPDATE payments
            SET status = ?, transaction_id = ?, updated_at = NOW()
            WHERE transaction_id LIKE ? OR id = ?
        ');
        $stmt->execute([
            'completed',
            $paymentIntent['id'],
            '%' . ($metadata['order_id'] ?? '') . '%',
            $metadata['payment_id'] ?? 0
        ]);

        // Add balance if it's a balance top-up
        if (isset($metadata['user_id'])) {
            $stmt = $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([
                $paymentIntent['amount'] / 100,
                $metadata['user_id']
            ]);
        }

        $logger->info('Payment success processed', ['payment_intent' => $paymentIntent['id']]);

    } catch (Exception $e) {
        $logger->error('Failed to process payment success: ' . $e->getMessage());
    }
}

function handlePaymentFailed($paymentIntent) {
    global $db, $logger;

    try {
        $stmt = $db->prepare('
            UPDATE payments
            SET status = ?, updated_at = NOW()
            WHERE transaction_id = ?
        ');
        $stmt->execute(['failed', $paymentIntent['id']]);

        $logger->info('Payment failure processed', ['payment_intent' => $paymentIntent['id']]);

    } catch (Exception $e) {
        $logger->error('Failed to process payment failure: ' . $e->getMessage());
    }
}

function handleSubscriptionUpdate($subscription) {
    global $db, $logger;

    $metadata = $subscription['metadata'] ?? [];

    try {
        $status = $subscription['status'];
        $subscriptionId = $metadata['subscription_id'] ?? null;

        if ($subscriptionId) {
            $stmt = $db->prepare('
                UPDATE subscriptions
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$status, $subscriptionId]);
        }

        $logger->info('Subscription update processed', ['subscription' => $subscription['id']]);

    } catch (Exception $e) {
        $logger->error('Failed to process subscription update: ' . $e->getMessage());
    }
}

function handleSubscriptionDeleted($subscription) {
    global $db, $logger;

    $metadata = $subscription['metadata'] ?? [];
    $subscriptionId = $metadata['subscription_id'] ?? null;

    try {
        if ($subscriptionId) {
            $stmt = $db->prepare('
                UPDATE subscriptions
                SET status = ?, cancelled_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute(['cancelled', $subscriptionId]);
        }

        $logger->info('Subscription deletion processed', ['subscription' => $subscription['id']]);

    } catch (Exception $e) {
        $logger->error('Failed to process subscription deletion: ' . $e->getMessage());
    }
}

function handleInvoicePaymentSucceeded($invoice) {
    global $logger;
    $logger->info('Invoice payment succeeded', ['invoice' => $invoice['id']]);
}

function handleInvoicePaymentFailed($invoice) {
    global $logger;
    $logger->warning('Invoice payment failed', ['invoice' => $invoice['id']]);
}
