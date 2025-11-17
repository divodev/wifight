<?php
/**
 * Payment Gateway Integration
 * Supports Stripe, PayPal, and M-Pesa
 */
class PaymentGateway {
    private $gateway;
    private $apiKey;
    private $apiSecret;
    
    public function __construct($gateway = 'stripe') {
        $this->gateway = $gateway;
        $this->loadCredentials();
    }
    
    /**
     * Load payment gateway credentials
     */
    private function loadCredentials() {
        switch ($this->gateway) {
            case 'stripe':
                $this->apiKey = getenv('STRIPE_API_KEY') ?: 'sk_test_...';
                break;
            case 'paypal':
                $this->apiKey = getenv('PAYPAL_CLIENT_ID') ?: '';
                $this->apiSecret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
                break;
            case 'mpesa':
                $this->apiKey = getenv('MPESA_CONSUMER_KEY') ?: '';
                $this->apiSecret = getenv('MPESA_CONSUMER_SECRET') ?: '';
                break;
        }
    }
    
    /**
     * Create payment intent/order
     */
    public function createPayment($amount, $currency, $description, $metadata = []) {
        switch ($this->gateway) {
            case 'stripe':
                return $this->createStripePayment($amount, $currency, $description, $metadata);
            case 'paypal':
                return $this->createPayPalPayment($amount, $currency, $description, $metadata);
            case 'mpesa':
                return $this->createMpesaPayment($amount, $currency, $description, $metadata);
            default:
                return ['success' => false, 'message' => 'Unsupported gateway'];
        }
    }
    
    /**
     * Create Stripe payment
     */
    private function createStripePayment($amount, $currency, $description, $metadata) {
        $url = 'https://api.stripe.com/v1/payment_intents';
        
        $data = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => strtolower($currency),
            'description' => $description,
            'metadata' => $metadata
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['id'])) {
            return [
                'success' => true,
                'payment_id' => $result['id'],
                'client_secret' => $result['client_secret'],
                'amount' => $amount,
                'currency' => $currency
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['error']['message'] ?? 'Payment creation failed'
        ];
    }
    
    /**
     * Create PayPal payment
     */
    private function createPayPalPayment($amount, $currency, $description, $metadata) {
        // Get access token
        $token = $this->getPayPalAccessToken();
        
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate with PayPal'];
        }
        
        $url = 'https://api.paypal.com/v2/checkout/orders';
        
        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', '')
                ],
                'description' => $description
            ]],
            'application_context' => [
                'return_url' => getenv('APP_URL') . '/portal/payment-success.html',
                'cancel_url' => getenv('APP_URL') . '/portal/payment-cancel.html'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 201 && isset($result['id'])) {
            return [
                'success' => true,
                'payment_id' => $result['id'],
                'approve_url' => $result['links'][1]['href'] ?? '',
                'amount' => $amount,
                'currency' => $currency
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Payment creation failed'
        ];
    }
    
    /**
     * Get PayPal access token
     */
    private function getPayPalAccessToken() {
        $url = 'https://api.paypal.com/v1/oauth2/token';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->apiKey . ':' . $this->apiSecret,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return $result['access_token'] ?? null;
    }
    
    /**
     * Create M-Pesa payment (STK Push)
     */
    private function createMpesaPayment($amount, $currency, $description, $metadata) {
        // Get access token
        $token = $this->getMpesaAccessToken();
        
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to authenticate with M-Pesa'];
        }
        
        $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        
        $timestamp = date('YmdHis');
        $shortcode = getenv('MPESA_SHORTCODE') ?: '174379';
        $passkey = getenv('MPESA_PASSKEY') ?: '';
        $password = base64_encode($shortcode . $passkey . $timestamp);
        
        $data = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $metadata['phone'] ?? '',
            'PartyB' => $shortcode,
            'PhoneNumber' => $metadata['phone'] ?? '',
            'CallBackURL' => getenv('APP_URL') . '/backend/api/payments/mpesa-callback.php',
            'AccountReference' => $metadata['reference'] ?? 'WiFight',
            'TransactionDesc' => $description
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            return [
                'success' => true,
                'payment_id' => $result['CheckoutRequestID'],
                'message' => 'Please check your phone to complete payment',
                'amount' => $amount,
                'currency' => $currency
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['ResponseDescription'] ?? 'Payment initiation failed'
        ];
    }
    
    /**
     * Get M-Pesa access token
     */
    private function getMpesaAccessToken() {
        $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret)
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return $result['access_token'] ?? null;
    }
    
    /**
     * Verify payment status
     */
    public function verifyPayment($paymentId) {
        switch ($this->gateway) {
            case 'stripe':
                return $this->verifyStripePayment($paymentId);
            case 'paypal':
                return $this->verifyPayPalPayment($paymentId);
            case 'mpesa':
                return $this->verifyMpesaPayment($paymentId);
            default:
                return ['success' => false, 'message' => 'Unsupported gateway'];
        }
    }
    
    /**
     * Verify Stripe payment
     */
    private function verifyStripePayment($paymentId) {
        $url = "https://api.stripe.com/v1/payment_intents/{$paymentId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['status'])) {
            return [
                'success' => true,
                'status' => $result['status'],
                'paid' => $result['status'] === 'succeeded'
            ];
        }
        
        return ['success' => false, 'message' => 'Payment verification failed'];
    }
    
    /**
     * Process refund
     */
    public function refund($paymentId, $amount = null) {
        switch ($this->gateway) {
            case 'stripe':
                return $this->refundStripePayment($paymentId, $amount);
            case 'paypal':
                return $this->refundPayPalPayment($paymentId, $amount);
            default:
                return ['success' => false, 'message' => 'Refunds not supported for this gateway'];
        }
    }
    
    /**
     * Refund Stripe payment
     */
    private function refundStripePayment($paymentId, $amount) {
        $url = 'https://api.stripe.com/v1/refunds';
        
        $data = ['payment_intent' => $paymentId];
        if ($amount) {
            $data['amount'] = $amount * 100;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['id'])) {
            return [
                'success' => true,
                'refund_id' => $result['id'],
                'status' => $result['status']
            ];
        }
        
        return ['success' => false, 'message' => 'Refund failed'];
    }
}
?>