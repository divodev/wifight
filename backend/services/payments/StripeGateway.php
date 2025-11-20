<?php
/**
 * WiFight ISP System - Stripe Payment Gateway
 *
 * Comprehensive Stripe integration using stripe-php library
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/PaymentGatewayInterface.php';

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Stripe\Refund;
use Stripe\Webhook;
use Stripe\Exception\ApiErrorException;

class StripeGateway implements PaymentGatewayInterface {
    private $config;
    private $apiKey;
    private $webhookSecret;

    public function __construct(array $config) {
        $this->config = $config;
        $this->apiKey = $config['secret_key'] ?? getenv('STRIPE_SECRET_KEY');
        $this->webhookSecret = $config['webhook_secret'] ?? getenv('STRIPE_WEBHOOK_SECRET');

        if (empty($this->apiKey)) {
            throw new Exception('Stripe API key not configured');
        }

        Stripe::setApiKey($this->apiKey);
    }

    /**
     * Create one-time payment
     */
    public function createPayment(array $paymentData) {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($paymentData['amount'] * 100), // Convert to cents
                'currency' => $paymentData['currency'] ?? 'usd',
                'customer' => $paymentData['customer_id'] ?? null,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'confirmation_method' => 'manual',
                'confirm' => $paymentData['confirm'] ?? false,
                'metadata' => [
                    'user_id' => $paymentData['user_id'] ?? null,
                    'plan_id' => $paymentData['plan_id'] ?? null,
                    'order_id' => $paymentData['order_id'] ?? null,
                ],
                'description' => $paymentData['description'] ?? 'WiFight ISP Payment',
            ]);

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $paymentIntent->status,
                'data' => [
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'requires_action' => $paymentIntent->status === 'requires_action',
                ]
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    /**
     * Process refund
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = '') {
        try {
            $refund = Refund::create([
                'payment_intent' => $transactionId,
                'amount' => (int)($amount * 100),
                'reason' => $this->mapRefundReason($reason),
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100,
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe refund error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer profile
     */
    public function createCustomer(array $customerData) {
        try {
            $customer = Customer::create([
                'email' => $customerData['email'],
                'name' => $customerData['name'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'metadata' => [
                    'user_id' => $customerData['user_id'] ?? null,
                ],
                'payment_method' => $customerData['payment_method'] ?? null,
                'invoice_settings' => [
                    'default_payment_method' => $customerData['payment_method'] ?? null,
                ],
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'data' => [
                    'email' => $customer->email,
                    'name' => $customer->name,
                ]
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe customer creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $subscriptionData) {
        try {
            $params = [
                'customer' => $subscriptionData['customer_id'],
                'items' => [
                    ['price' => $subscriptionData['price_id']],
                ],
                'metadata' => [
                    'user_id' => $subscriptionData['user_id'] ?? null,
                    'plan_id' => $subscriptionData['plan_id'] ?? null,
                ],
            ];

            // Add trial period if specified
            if (isset($subscriptionData['trial_days']) && $subscriptionData['trial_days'] > 0) {
                $params['trial_period_days'] = $subscriptionData['trial_days'];
            }

            // Add coupon if specified
            if (!empty($subscriptionData['coupon'])) {
                $params['coupon'] = $subscriptionData['coupon'];
            }

            // Set billing cycle anchor if specified
            if (isset($subscriptionData['billing_cycle_anchor'])) {
                $params['billing_cycle_anchor'] = $subscriptionData['billing_cycle_anchor'];
            }

            $subscription = Subscription::create($params);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'data' => [
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'trial_end' => $subscription->trial_end,
                    'latest_invoice' => $subscription->latest_invoice,
                ]
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe subscription creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false) {
        try {
            $subscription = Subscription::retrieve($subscriptionId);

            if ($immediately) {
                $subscription = $subscription->cancel();
            } else {
                $subscription = $subscription->update([
                    'cancel_at_period_end' => true,
                ]);
            }

            return [
                'success' => true,
                'status' => $subscription->status,
                'cancel_at' => $subscription->cancel_at,
                'canceled_at' => $subscription->canceled_at,
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe subscription cancellation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription
     */
    public function updateSubscription(string $subscriptionId, array $updateData) {
        try {
            $params = [];

            // Update price/plan
            if (isset($updateData['price_id'])) {
                $params['items'] = [
                    [
                        'id' => $updateData['subscription_item_id'],
                        'price' => $updateData['price_id'],
                    ],
                ];
                $params['proration_behavior'] = $updateData['proration_behavior'] ?? 'create_prorations';
            }

            // Update payment method
            if (isset($updateData['default_payment_method'])) {
                $params['default_payment_method'] = $updateData['default_payment_method'];
            }

            // Update metadata
            if (isset($updateData['metadata'])) {
                $params['metadata'] = $updateData['metadata'];
            }

            $subscription = Subscription::update($subscriptionId, $params);

            return [
                'success' => true,
                'status' => $subscription->status,
                'data' => [
                    'current_period_end' => $subscription->current_period_end,
                ]
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe subscription update error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId) {
        try {
            $paymentIntent = PaymentIntent::retrieve($transactionId);

            return [
                'status' => $paymentIntent->status,
                'data' => [
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'created' => $paymentIntent->created,
                    'customer' => $paymentIntent->customer,
                    'metadata' => $paymentIntent->metadata,
                ]
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe payment status error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature) {
        try {
            Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
            return true;
        } catch (\Exception $e) {
            error_log('Stripe webhook verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get gateway name
     */
    public function getName() {
        return 'stripe';
    }

    /**
     * Check if gateway supports subscriptions
     */
    public function supportsSubscriptions() {
        return true;
    }

    /**
     * Map refund reason to Stripe's accepted values
     */
    private function mapRefundReason(string $reason) {
        $reasonMap = [
            'duplicate' => 'duplicate',
            'fraudulent' => 'fraudulent',
            'customer_request' => 'requested_by_customer',
        ];

        return $reasonMap[$reason] ?? 'requested_by_customer';
    }

    /**
     * Confirm payment (for 3D Secure)
     */
    public function confirmPayment(string $paymentIntentId, string $paymentMethod = null) {
        try {
            $params = [];
            if ($paymentMethod) {
                $params['payment_method'] = $paymentMethod;
            }

            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            $paymentIntent = $paymentIntent->confirm($params);

            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'requires_action' => $paymentIntent->status === 'requires_action',
                'client_secret' => $paymentIntent->client_secret,
            ];

        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
