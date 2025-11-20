<?php
/**
 * WiFight ISP System - Notification Service
 *
 * Handles email, SMS, and in-app notifications
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';

class NotificationService {
    private $db;
    private $logger;

    // Notification types
    const TYPE_EMAIL = 'email';
    const TYPE_SMS = 'sms';
    const TYPE_IN_APP = 'in_app';
    const TYPE_PUSH = 'push';

    // Notification templates
    const TEMPLATE_WELCOME = 'welcome';
    const TEMPLATE_PASSWORD_RESET = 'password_reset';
    const TEMPLATE_PAYMENT_SUCCESS = 'payment_success';
    const TEMPLATE_PAYMENT_FAILED = 'payment_failed';
    const TEMPLATE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    const TEMPLATE_SUBSCRIPTION_EXPIRED = 'subscription_expired';
    const TEMPLATE_SESSION_STARTED = 'session_started';
    const TEMPLATE_SESSION_ENDED = 'session_ended';
    const TEMPLATE_LOW_BALANCE = 'low_balance';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->logger = new Logger();
    }

    /**
     * Send notification
     */
    public function send(int $userId, string $type, string $template, array $data = []) {
        try {
            // Get user details
            $stmt = $this->db->prepare('SELECT email, phone, username FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('User not found');
            }

            $sent = false;

            switch ($type) {
                case self::TYPE_EMAIL:
                    $sent = $this->sendEmail($user['email'], $template, array_merge($data, ['username' => $user['username']]));
                    break;

                case self::TYPE_SMS:
                    $sent = $this->sendSMS($user['phone'], $template, $data);
                    break;

                case self::TYPE_IN_APP:
                    $sent = $this->createInAppNotification($userId, $template, $data);
                    break;

                case self::TYPE_PUSH:
                    $sent = $this->sendPushNotification($userId, $template, $data);
                    break;
            }

            // Log notification
            $this->logNotification([
                'user_id' => $userId,
                'type' => $type,
                'template' => $template,
                'sent' => $sent
            ]);

            return ['success' => $sent];

        } catch (Exception $e) {
            $this->logger->error('Notification send error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail(string $email, string $template, array $data) {
        $subject = $this->getEmailSubject($template);
        $body = $this->getEmailBody($template, $data);

        if (getenv('SMTP_ENABLED') === 'true') {
            // Use PHPMailer or similar
            return $this->sendSMTPEmail($email, $subject, $body);
        } else {
            // Use PHP mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . getenv('EMAIL_FROM_ADDRESS') . "\r\n";

            return mail($email, $subject, $body, $headers);
        }
    }

    /**
     * Send SMTP email (placeholder for PHPMailer integration)
     */
    private function sendSMTPEmail(string $email, string $subject, string $body) {
        // TODO: Implement PHPMailer integration
        $this->logger->info('SMTP email queued', ['to' => $email, 'subject' => $subject]);
        return true;
    }

    /**
     * Send SMS notification
     */
    private function sendSMS(string $phone, string $template, array $data) {
        if (empty($phone)) {
            return false;
        }

        $message = $this->getSMSMessage($template, $data);

        // TODO: Integrate with SMS provider (Twilio, Africa's Talking, etc.)
        $this->logger->info('SMS queued', ['to' => $phone, 'message' => $message]);

        return true;
    }

    /**
     * Create in-app notification
     */
    private function createInAppNotification(int $userId, string $template, array $data) {
        try {
            $message = $this->getNotificationMessage($template, $data);

            $stmt = $this->db->prepare('
                INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ');

            $stmt->execute([
                $userId,
                $template,
                $this->getNotificationTitle($template),
                $message
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->error('In-app notification creation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(int $userId, string $template, array $data) {
        // TODO: Integrate with Firebase Cloud Messaging or similar
        $this->logger->info('Push notification queued', ['user_id' => $userId, 'template' => $template]);
        return true;
    }

    /**
     * Get email subject for template
     */
    private function getEmailSubject(string $template) {
        $subjects = [
            self::TEMPLATE_WELCOME => 'Welcome to WiFight ISP',
            self::TEMPLATE_PASSWORD_RESET => 'Password Reset Request',
            self::TEMPLATE_PAYMENT_SUCCESS => 'Payment Successful',
            self::TEMPLATE_PAYMENT_FAILED => 'Payment Failed',
            self::TEMPLATE_SUBSCRIPTION_EXPIRING => 'Your Subscription is Expiring Soon',
            self::TEMPLATE_SUBSCRIPTION_EXPIRED => 'Your Subscription Has Expired',
            self::TEMPLATE_SESSION_STARTED => 'Session Started',
            self::TEMPLATE_SESSION_ENDED => 'Session Ended',
            self::TEMPLATE_LOW_BALANCE => 'Low Balance Alert',
        ];

        return $subjects[$template] ?? 'Notification from WiFight ISP';
    }

    /**
     * Get email body for template
     */
    private function getEmailBody(string $template, array $data) {
        $username = $data['username'] ?? 'User';

        switch ($template) {
            case self::TEMPLATE_WELCOME:
                return "<html><body><h2>Welcome to WiFight ISP!</h2><p>Hello {$username},</p><p>Thank you for joining us.</p></body></html>";

            case self::TEMPLATE_PAYMENT_SUCCESS:
                $amount = $data['amount'] ?? '0.00';
                return "<html><body><h2>Payment Successful</h2><p>Hello {$username},</p><p>Your payment of \${$amount} was processed successfully.</p></body></html>";

            case self::TEMPLATE_SUBSCRIPTION_EXPIRING:
                $daysLeft = $data['days_left'] ?? 'N/A';
                return "<html><body><h2>Subscription Expiring</h2><p>Hello {$username},</p><p>Your subscription will expire in {$daysLeft} days. Please renew to avoid service interruption.</p></body></html>";

            case self::TEMPLATE_LOW_BALANCE:
                $balance = $data['balance'] ?? '0.00';
                return "<html><body><h2>Low Balance Alert</h2><p>Hello {$username},</p><p>Your current balance is \${$balance}. Please top up to continue using our services.</p></body></html>";

            default:
                return "<html><body><p>Hello {$username},</p><p>You have a new notification from WiFight ISP.</p></body></html>";
        }
    }

    /**
     * Get SMS message for template
     */
    private function getSMSMessage(string $template, array $data) {
        switch ($template) {
            case self::TEMPLATE_PAYMENT_SUCCESS:
                $amount = $data['amount'] ?? '0.00';
                return "WiFight: Payment of \${$amount} received. Thank you!";

            case self::TEMPLATE_SUBSCRIPTION_EXPIRING:
                $daysLeft = $data['days_left'] ?? 'N/A';
                return "WiFight: Your subscription expires in {$daysLeft} days. Renew now!";

            case self::TEMPLATE_LOW_BALANCE:
                $balance = $data['balance'] ?? '0.00';
                return "WiFight: Low balance alert! Current: \${$balance}. Top up now.";

            default:
                return "WiFight: You have a new notification.";
        }
    }

    /**
     * Get notification title
     */
    private function getNotificationTitle(string $template) {
        return str_replace('_', ' ', ucwords($template, '_'));
    }

    /**
     * Get notification message
     */
    private function getNotificationMessage(string $template, array $data) {
        return $this->getSMSMessage($template, $data);
    }

    /**
     * Log notification
     */
    private function logNotification(array $data) {
        $this->logger->info('Notification sent', $data);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId) {
        try {
            $stmt = $this->db->prepare('
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ');

            $stmt->execute([$notificationId, $userId]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $limit = 20, bool $unreadOnly = false) {
        try {
            $where = 'user_id = ?';
            $params = [$userId];

            if ($unreadOnly) {
                $where .= ' AND is_read = 0';
            }

            $stmt = $this->db->prepare("
                SELECT id, type, title, message, is_read, read_at, created_at
                FROM notifications
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $params[] = $limit;
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}
