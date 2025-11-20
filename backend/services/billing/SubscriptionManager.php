<?php
/**
 * WiFight ISP System - Subscription Manager
 *
 * Manages subscription lifecycle, renewals, and plan changes
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../payments/PaymentGatewayInterface.php';

class SubscriptionManager {
    private $db;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
    }

    /**
     * Create new subscription
     */
    public function createSubscription(int $userId, int $planId, string $paymentMethod, PaymentGatewayInterface $gateway = null) {
        try {
            $this->db->beginTransaction();

            // Get plan details
            $stmt = $this->db->prepare('SELECT * FROM plans WHERE id = ? AND status = ?');
            $stmt->execute([$planId, 'active']);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                throw new Exception('Plan not found or inactive');
            }

            // Check user balance or payment method
            if ($paymentMethod === 'balance') {
                $stmt = $this->db->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $balance = (float)$stmt->fetch(PDO::FETCH_ASSOC)['balance'];

                if ($balance < (float)$plan['price']) {
                    throw new Exception('Insufficient balance');
                }
            }

            // Calculate subscription dates
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
            $nextBillingDate = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));

            // Create subscription
            $stmt = $this->db->prepare('
                INSERT INTO subscriptions
                (user_id, plan_id, status, start_date, end_date, next_billing_date, billing_cycle, payment_method, auto_renew)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                $planId,
                'active',
                $startDate,
                $endDate,
                $nextBillingDate,
                'monthly',
                $paymentMethod,
                1
            ]);

            $subscriptionId = $this->db->lastInsertId();

            // Process payment
            if ($paymentMethod === 'balance') {
                // Deduct from balance
                $stmt = $this->db->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$plan['price'], $userId]);

                // Create payment record
                $stmt = $this->db->prepare('
                    INSERT INTO payments (user_id, amount, currency, payment_method, status, transaction_id, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $userId,
                    $plan['price'],
                    $plan['currency'] ?? 'USD',
                    'balance',
                    'completed',
                    'SUB-' . $subscriptionId . '-' . time(),
                    'Subscription to ' . $plan['name']
                ]);
            }

            $this->db->commit();

            $this->logger->info('Subscription created', [
                'subscription_id' => $subscriptionId,
                'user_id' => $userId,
                'plan_id' => $planId
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Subscription creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Renew subscription
     */
    public function renewSubscription(int $subscriptionId) {
        try {
            $this->db->beginTransaction();

            // Get subscription details
            $stmt = $this->db->prepare('
                SELECT s.*, p.price, p.duration_days, p.name as plan_name, p.currency
                FROM subscriptions s
                JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ');
            $stmt->execute([$subscriptionId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                throw new Exception('Subscription not found');
            }

            // Check user balance
            $stmt = $this->db->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$subscription['user_id']]);
            $balance = (float)$stmt->fetch(PDO::FETCH_ASSOC)['balance'];

            if ($balance < (float)$subscription['price']) {
                throw new Exception('Insufficient balance for renewal');
            }

            // Calculate new dates
            $newEndDate = date('Y-m-d H:i:s', strtotime($subscription['end_date'] . " +{$subscription['duration_days']} days"));
            $newBillingDate = date('Y-m-d H:i:s', strtotime($newEndDate . " +{$subscription['duration_days']} days"));

            // Update subscription
            $stmt = $this->db->prepare('
                UPDATE subscriptions
                SET end_date = ?, next_billing_date = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$newEndDate, $newBillingDate, $subscriptionId]);

            // Deduct payment
            $stmt = $this->db->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$subscription['price'], $subscription['user_id']]);

            // Create payment record
            $stmt = $this->db->prepare('
                INSERT INTO payments (user_id, amount, currency, payment_method, status, transaction_id, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $subscription['user_id'],
                $subscription['price'],
                $subscription['currency'],
                $subscription['payment_method'],
                'completed',
                'RENEW-' . $subscriptionId . '-' . time(),
                'Renewal: ' . $subscription['plan_name']
            ]);

            $this->db->commit();

            $this->logger->info('Subscription renewed', ['subscription_id' => $subscriptionId]);

            return ['success' => true, 'new_end_date' => $newEndDate];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Subscription renewal failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(int $subscriptionId, string $reason = '', bool $immediately = false) {
        try {
            $newStatus = $immediately ? 'cancelled' : 'cancelled';
            $cancelAt = $immediately ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare('
                UPDATE subscriptions
                SET status = ?, cancelled_at = ?, cancellation_reason = ?, auto_renew = 0, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$newStatus, $cancelAt, $reason, $subscriptionId]);

            $this->logger->info('Subscription cancelled', [
                'subscription_id' => $subscriptionId,
                'immediately' => $immediately
            ]);

            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error('Subscription cancellation failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process expiring subscriptions (cron job)
     */
    public function processExpiringSubscriptions() {
        try {
            // Find subscriptions expiring in next 24 hours
            $stmt = $this->db->prepare('
                SELECT id, user_id, plan_id, payment_method
                FROM subscriptions
                WHERE status = ?
                AND auto_renew = 1
                AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY)
            ');
            $stmt->execute(['active']);
            $expiringSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $renewed = 0;
            $failed = 0;

            foreach ($expiringSubscriptions as $subscription) {
                $result = $this->renewSubscription($subscription['id']);
                if ($result['success']) {
                    $renewed++;
                } else {
                    $failed++;
                    // TODO: Send notification about failed renewal
                }
            }

            $this->logger->info('Processed expiring subscriptions', [
                'total' => count($expiringSubscriptions),
                'renewed' => $renewed,
                'failed' => $failed
            ]);

            return [
                'total' => count($expiringSubscriptions),
                'renewed' => $renewed,
                'failed' => $failed
            ];

        } catch (Exception $e) {
            $this->logger->error('Processing expiring subscriptions failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upgrade subscription
     */
    public function upgradeSubscription(int $subscriptionId, int $newPlanId) {
        try {
            $this->db->beginTransaction();

            // Get current subscription and plans
            $stmt = $this->db->prepare('
                SELECT s.*,
                       old_p.price as old_price, old_p.duration_days as old_duration,
                       new_p.price as new_price, new_p.duration_days as new_duration
                FROM subscriptions s
                JOIN plans old_p ON s.plan_id = old_p.id
                JOIN plans new_p ON new_p.id = ?
                WHERE s.id = ?
            ');
            $stmt->execute([$newPlanId, $subscriptionId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                throw new Exception('Subscription or plan not found');
            }

            // Calculate proration
            $daysRemaining = (strtotime($data['end_date']) - time()) / 86400;
            $prorationCredit = ((float)$data['old_price'] / $data['old_duration']) * $daysRemaining;
            $priceDifference = (float)$data['new_price'] - $prorationCredit;

            // Check if user can afford upgrade
            if ($priceDifference > 0) {
                $stmt = $this->db->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->execute([$data['user_id']]);
                $balance = (float)$stmt->fetch(PDO::FETCH_ASSOC)['balance'];

                if ($balance < $priceDifference) {
                    throw new Exception('Insufficient balance for upgrade');
                }

                // Charge difference
                $stmt = $this->db->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$priceDifference, $data['user_id']]);
            }

            // Update subscription
            $newEndDate = date('Y-m-d H:i:s', strtotime("+{$data['new_duration']} days"));
            $stmt = $this->db->prepare('
                UPDATE subscriptions
                SET plan_id = ?, end_date = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$newPlanId, $newEndDate, $subscriptionId]);

            $this->db->commit();

            $this->logger->info('Subscription upgraded', [
                'subscription_id' => $subscriptionId,
                'new_plan_id' => $newPlanId,
                'charged' => $priceDifference
            ]);

            return [
                'success' => true,
                'amount_charged' => max(0, $priceDifference),
                'new_end_date' => $newEndDate
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Subscription upgrade failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
