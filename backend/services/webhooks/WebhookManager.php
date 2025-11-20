<?php
/**
 * WiFight ISP System - Webhook Manager
 *
 * Manages outgoing webhooks to third-party services
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';

class WebhookManager {
    private $db;
    private $logger;

    // Webhook events
    const EVENT_USER_CREATED = 'user.created';
    const EVENT_USER_UPDATED = 'user.updated';
    const EVENT_USER_DELETED = 'user.deleted';
    const EVENT_SESSION_STARTED = 'session.started';
    const EVENT_SESSION_ENDED = 'session.ended';
    const EVENT_PAYMENT_COMPLETED = 'payment.completed';
    const EVENT_PAYMENT_FAILED = 'payment.failed';
    const EVENT_SUBSCRIPTION_CREATED = 'subscription.created';
    const EVENT_SUBSCRIPTION_RENEWED = 'subscription.renewed';
    const EVENT_SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    const EVENT_SUBSCRIPTION_EXPIRED = 'subscription.expired';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
    }

    /**
     * Trigger webhook event
     */
    public function trigger(string $event, array $payload) {
        try {
            // Get all webhook subscriptions for this event
            $stmt = $this->db->prepare('
                SELECT id, url, secret, headers
                FROM webhook_subscriptions
                WHERE event = ? AND status = ?
            ');
            $stmt->execute([$event, 'active']);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($subscriptions as $subscription) {
                $this->sendWebhook($subscription, $event, $payload);
            }

            return true;

        } catch (Exception $e) {
            $this->logger->error('Webhook trigger error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send webhook to endpoint
     */
    private function sendWebhook(array $subscription, string $event, array $payload) {
        $webhookPayload = [
            'event' => $event,
            'timestamp' => time(),
            'data' => $payload
        ];

        // Generate signature
        $signature = $this->generateSignature(json_encode($webhookPayload), $subscription['secret']);

        // Prepare headers
        $headers = [
            'Content-Type: application/json',
            'X-WiFight-Event: ' . $event,
            'X-WiFight-Signature: ' . $signature,
            'User-Agent: WiFight-Webhook/1.0'
        ];

        // Add custom headers if any
        if (!empty($subscription['headers'])) {
            $customHeaders = json_decode($subscription['headers'], true);
            foreach ($customHeaders as $key => $value) {
                $headers[] = "$key: $value";
            }
        }

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

        // Log webhook delivery
        $this->logWebhookDelivery([
            'subscription_id' => $subscription['id'],
            'event' => $event,
            'url' => $subscription['url'],
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'response' => substr($response, 0, 500),
            'error' => $error
        ]);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Generate HMAC signature for webhook
     */
    private function generateSignature(string $payload, string $secret) {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature(string $payload, string $signature, string $secret) {
        $expectedSignature = $this->generateSignature($payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Log webhook delivery
     */
    private function logWebhookDelivery(array $data) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO webhook_logs
                (subscription_id, event, url, http_code, success, response, error, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');

            $stmt->execute([
                $data['subscription_id'],
                $data['event'],
                $data['url'],
                $data['http_code'],
                $data['success'] ? 1 : 0,
                $data['response'],
                $data['error']
            ]);

            if (!$data['success']) {
                $this->logger->warning('Webhook delivery failed', $data);
            }

        } catch (Exception $e) {
            $this->logger->error('Webhook logging error: ' . $e->getMessage());
        }
    }

    /**
     * Create webhook subscription
     */
    public function createSubscription(int $userId, string $event, string $url, string $secret = null) {
        try {
            if (empty($secret)) {
                $secret = bin2hex(random_bytes(32));
            }

            $stmt = $this->db->prepare('
                INSERT INTO webhook_subscriptions
                (user_id, event, url, secret, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');

            $stmt->execute([$userId, $event, $url, $secret, 'active']);

            return [
                'success' => true,
                'subscription_id' => $this->db->lastInsertId(),
                'secret' => $secret
            ];

        } catch (Exception $e) {
            $this->logger->error('Webhook subscription creation error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete webhook subscription
     */
    public function deleteSubscription(int $subscriptionId, int $userId) {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM webhook_subscriptions
                WHERE id = ? AND user_id = ?
            ');

            $stmt->execute([$subscriptionId, $userId]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get available webhook events
     */
    public static function getAvailableEvents() {
        return [
            self::EVENT_USER_CREATED => 'User created',
            self::EVENT_USER_UPDATED => 'User updated',
            self::EVENT_USER_DELETED => 'User deleted',
            self::EVENT_SESSION_STARTED => 'Session started',
            self::EVENT_SESSION_ENDED => 'Session ended',
            self::EVENT_PAYMENT_COMPLETED => 'Payment completed',
            self::EVENT_PAYMENT_FAILED => 'Payment failed',
            self::EVENT_SUBSCRIPTION_CREATED => 'Subscription created',
            self::EVENT_SUBSCRIPTION_RENEWED => 'Subscription renewed',
            self::EVENT_SUBSCRIPTION_CANCELLED => 'Subscription cancelled',
            self::EVENT_SUBSCRIPTION_EXPIRED => 'Subscription expired',
        ];
    }

    /**
     * Retry failed webhooks
     */
    public function retryFailedWebhooks(int $maxRetries = 3) {
        try {
            // Get failed webhooks from last hour
            $stmt = $this->db->prepare('
                SELECT w.*, s.url, s.secret, s.headers
                FROM webhook_logs w
                JOIN webhook_subscriptions s ON w.subscription_id = s.id
                WHERE w.success = 0
                AND w.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND w.retry_count < ?
                GROUP BY w.subscription_id, w.event
            ');

            $stmt->execute([$maxRetries]);
            $failedWebhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $retried = 0;
            foreach ($failedWebhooks as $webhook) {
                // Recreate payload (simplified)
                $payload = json_decode($webhook['response'], true) ?? [];

                $this->sendWebhook([
                    'id' => $webhook['subscription_id'],
                    'url' => $webhook['url'],
                    'secret' => $webhook['secret'],
                    'headers' => $webhook['headers']
                ], $webhook['event'], $payload);

                $retried++;
            }

            return ['retried' => $retried];

        } catch (Exception $e) {
            $this->logger->error('Webhook retry error: ' . $e->getMessage());
            return ['retried' => 0, 'error' => $e->getMessage()];
        }
    }
}
