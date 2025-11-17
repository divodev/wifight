<?php
require_once '../../config/database.php';
require_once '../../config/cors.php';
require_once '../../models/Payment.php';
require_once '../../models/Plan.php';
require_once '../../services/PaymentGateway.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';

$database = new Database();
$db = $database->getConnection();

$payment = new Payment($db);
$plan = new Plan($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->plan_id) && !empty($data->payment_method)) {
    
    // Get plan details
    $planData = $plan->getById($data->plan_id);
    
    if (!$planData) {
        Response::error('Plan not found', 404);
        exit;
    }
    
    // Initialize payment gateway
    $gateway = new PaymentGateway($data->payment_method);
    
    // Create payment
    $paymentResult = $gateway->createPayment(
        $planData['price'],
        $data->currency ?? 'USD',
        "WiFight - " . $planData['name'],
        [
            'plan_id' => $data->plan_id,
            'customer_email' => $data->email ?? '',
            'customer_phone' => $data->phone ?? ''
        ]
    );
    
    if ($paymentResult['success']) {
        // Save payment record
        $payment->transaction_id = $paymentResult['payment_id'];
        $payment->plan_id = $data->plan_id;
        $payment->amount = $planData['price'];
        $payment->currency = $data->currency ?? 'USD';
        $payment->payment_method = $data->payment_method;
        $payment->status = 'pending';
        $payment->customer_email = $data->email ?? null;
        $payment->customer_phone = $data->phone ?? null;
        $payment->gateway_response = json_encode($paymentResult);
        
        if ($payment->create()) {
            Logger::payment('Payment initiated', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount
            ]);
            
            Response::success([
                'payment_id' => $payment->id,
                'transaction_id' => $paymentResult['payment_id'],
                'client_secret' => $paymentResult['client_secret'] ?? null,
                'approve_url' => $paymentResult['approve_url'] ?? null,
                'amount' => $planData['price'],
                'currency' => $data->currency ?? 'USD'
            ], 'Payment initiated successfully', 201);
        } else {
            Response::error('Failed to create payment record', 500);
        }
    } else {
        Logger::error('Payment creation failed', ['error' => $paymentResult['message']]);
        Response::error($paymentResult['message'], 400);
    }
} else {
    Response::error('Plan ID and payment method are required', 400);
}
?>