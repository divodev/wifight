<?php
/**
 * WiFight ISP System - Payment Gateway Interface
 *
 * Standard interface for all payment gateway implementations
 */

interface PaymentGatewayInterface {
    /**
     * Initialize the gateway with configuration
     *
     * @param array $config Gateway configuration
     */
    public function __construct(array $config);

    /**
     * Create a one-time payment
     *
     * @param array $paymentData Payment details
     * @return array ['success' => bool, 'transaction_id' => string, 'data' => array, 'error' => string]
     */
    public function createPayment(array $paymentData);

    /**
     * Process refund
     *
     * @param string $transactionId Original transaction ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array ['success' => bool, 'refund_id' => string, 'error' => string]
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = '');

    /**
     * Create customer profile
     *
     * @param array $customerData Customer details
     * @return array ['success' => bool, 'customer_id' => string, 'error' => string]
     */
    public function createCustomer(array $customerData);

    /**
     * Create subscription
     *
     * @param array $subscriptionData Subscription details
     * @return array ['success' => bool, 'subscription_id' => string, 'data' => array, 'error' => string]
     */
    public function createSubscription(array $subscriptionData);

    /**
     * Cancel subscription
     *
     * @param string $subscriptionId Subscription ID
     * @param bool $immediately Cancel immediately or at period end
     * @return array ['success' => bool, 'error' => string]
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false);

    /**
     * Update subscription
     *
     * @param string $subscriptionId Subscription ID
     * @param array $updateData Update details
     * @return array ['success' => bool, 'error' => string]
     */
    public function updateSubscription(string $subscriptionId, array $updateData);

    /**
     * Get payment status
     *
     * @param string $transactionId Transaction ID
     * @return array ['status' => string, 'data' => array]
     */
    public function getPaymentStatus(string $transactionId);

    /**
     * Verify webhook signature
     *
     * @param string $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature);

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getName();

    /**
     * Check if gateway supports subscriptions
     *
     * @return bool
     */
    public function supportsSubscriptions();
}
