<?php
/**
 * Email Service
 * Handles sending emails via SMTP or API
 */
class EmailService {
    private $from_email;
    private $from_name;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    
    public function __construct() {
        $this->from_email = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@wifight.com';
        $this->from_name = getenv('MAIL_FROM_NAME') ?: 'WiFight';
        $this->smtp_host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $this->smtp_port = getenv('MAIL_PORT') ?: 587;
        $this->smtp_username = getenv('MAIL_USERNAME') ?: '';
        $this->smtp_password = getenv('MAIL_PASSWORD') ?: '';
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $isHtml = true) {
        try {
            $headers = $this->buildHeaders($isHtml);
            
            $success = mail($to, $subject, $body, $headers);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send email'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Build email headers
     */
    private function buildHeaders($isHtml = true) {
        $headers = [];
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->from_email}";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        if ($isHtml) {
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        }
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Send voucher email
     */
    public function sendVoucher($to, $voucherCode, $planName, $expiresAt) {
        $subject = "Your WiFight Voucher Code";
        
        $body = $this->getVoucherTemplate($voucherCode, $planName, $expiresAt);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcome($to, $name) {
        $subject = "Welcome to WiFight!";
        
        $body = $this->getWelcomeTemplate($name);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($to, $resetToken) {
        $subject = "Reset Your Password";
        
        $resetLink = getenv('APP_URL') . "/reset-password.php?token={$resetToken}";
        
        $body = $this->getPasswordResetTemplate($resetLink);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation($to, $amount, $currency, $transactionId) {
        $subject = "Payment Confirmation";
        
        $body = $this->getPaymentConfirmationTemplate($amount, $currency, $transactionId);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Get voucher email template
     */
    private function getVoucherTemplate($code, $planName, $expiresAt) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .voucher-code { 
                    background: #f4f4f4; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 24px; 
                    font-weight: bold;
                    letter-spacing: 3px;
                    border: 2px dashed #333;
                    margin: 20px 0;
                }
                .info { background: #e8f4f8; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Your WiFight Voucher</h1>
                <p>Thank you for your purchase! Here is your WiFi access voucher:</p>
                
                <div class='voucher-code'>{$code}</div>
                
                <div class='info'>
                    <p><strong>Plan:</strong> {$planName}</p>
                    <p><strong>Expires:</strong> {$expiresAt}</p>
                </div>
                
                <h3>How to Use:</h3>
                <ol>
                    <li>Connect to the WiFi network</li>
                    <li>Enter your voucher code when prompted</li>
                    <li>Enjoy your internet access!</li>
                </ol>
                
                <p>Need help? Contact us at support@wifight.com</p>
                
                <hr>
                <p style='text-align: center; color: #888; font-size: 12px;'>
                    &copy; " . date('Y') . " WiFight. All rights reserved.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get welcome email template
     */
    private function getWelcomeTemplate($name) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to WiFight!</h1>
                </div>
                <div class='content'>
                    <p>Hi {$name},</p>
                    <p>Welcome to WiFight! We're excited to have you on board.</p>
                    <p>You can now enjoy seamless WiFi access at our locations.</p>
                    <p>If you have any questions, feel free to reach out to our support team.</p>
                    <p>Best regards,<br>The WiFight Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get password reset template
     */
    private function getPasswordResetTemplate($resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .button { 
                    display: inline-block; 
                    padding: 12px 30px; 
                    background: #667eea; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Reset Your Password</h2>
                <p>You requested to reset your password. Click the button below to proceed:</p>
                <a href='{$resetLink}' class='button'>Reset Password</a>
                <p>If you didn't request this, please ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get payment confirmation template
     */
    private function getPaymentConfirmationTemplate($amount, $currency, $transactionId) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; }
                .details { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='success'>
                    <h2>✓ Payment Successful!</h2>
                </div>
                
                <p>Your payment has been processed successfully.</p>
                
                <div class='details'>
                    <p><strong>Amount:</strong> {$currency} {$amount}</p>
                    <p><strong>Transaction ID:</strong> {$transactionId}</p>
                    <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                
                <p>Thank you for your payment!</p>
                <p>The WiFight Team</p>
            </div>
        </body>
        </html>
        ";
    }
}
?>