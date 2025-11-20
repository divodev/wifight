<?php
/**
 * WiFight ISP System - Authentication Utility
 *
 * Handles user authentication, authorization, and security checks
 */

require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $jwt;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->jwt = new JWT();
        $this->logger = new Logger();
    }

    /**
     * Authenticate user with username/email and password
     *
     * @param string $identifier Username or email
     * @param string $password Plain text password
     * @param string $ipAddress User's IP address
     * @return array|null ['success' => bool, 'data' => array, 'message' => string]
     */
    public function authenticate($identifier, $password, $ipAddress = null) {
        try {
            // Check for rate limiting (brute force protection)
            if ($this->isRateLimited($identifier, $ipAddress)) {
                $this->logger->warning('Authentication rate limit exceeded', [
                    'identifier' => $identifier,
                    'ip' => $ipAddress
                ]);

                return [
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again later.',
                    'error_code' => 'RATE_LIMITED'
                ];
            }

            // Find user by username or email
            $stmt = $this->db->prepare('
                SELECT id, username, email, password_hash, full_name, role, status, balance, two_factor_enabled, two_factor_secret
                FROM users
                WHERE (username = ? OR email = ?) AND status = ?
            ');
            $stmt->execute([$identifier, $identifier, 'active']);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->logFailedAttempt($identifier, $ipAddress, 'User not found');

                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'error_code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->logFailedAttempt($identifier, $ipAddress, 'Invalid password');

                return [
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'error_code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Check if 2FA is required
            if ($user['two_factor_enabled']) {
                // Return indication that 2FA is required
                return [
                    'success' => false,
                    'requires_2fa' => true,
                    'user_id' => $user['id'],
                    'message' => 'Two-factor authentication required',
                    'error_code' => '2FA_REQUIRED'
                ];
            }

            // Generate JWT tokens
            $tokenPayload = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ];

            $tokens = $this->jwt->createTokenPair($tokenPayload);

            // Update last login
            $this->updateLastLogin($user['id'], $ipAddress);

            // Log successful login
            $this->logger->info('User login successful', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'ip' => $ipAddress
            ]);

            // Audit log
            $this->logAudit($user['id'], 'LOGIN', 'users', $user['id'], $ipAddress);

            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role'],
                        'balance' => (float)$user['balance']
                    ],
                    'tokens' => $tokens
                ],
                'message' => 'Login successful'
            ];

        } catch (Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Authentication failed',
                'error_code' => 'AUTH_ERROR'
            ];
        }
    }

    /**
     * Verify JWT token and return user data
     *
     * @param string $token JWT token
     * @return array|null User data if valid, null otherwise
     */
    public function verifyToken($token = null) {
        if (!$token) {
            $token = $this->jwt->getTokenFromHeader();
        }

        if (!$token) {
            return null;
        }

        // Check if token is blacklisted
        if ($this->jwt->isBlacklisted($token)) {
            return null;
        }

        $decoded = $this->jwt->validate($token);

        if (!$decoded || !isset($decoded->data)) {
            return null;
        }

        return (array)$decoded->data;
    }

    /**
     * Check if user has required role
     *
     * @param string|array $requiredRoles Required role(s)
     * @param string $token JWT token
     * @return bool
     */
    public function hasRole($requiredRoles, $token = null) {
        $user = $this->verifyToken($token);

        if (!$user) {
            return false;
        }

        $requiredRoles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];

        return in_array($user['role'], $requiredRoles);
    }

    /**
     * Check if user owns a resource
     *
     * @param int $userId User ID
     * @param string $token JWT token
     * @return bool
     */
    public function ownsResource($userId, $token = null) {
        $user = $this->verifyToken($token);

        if (!$user) {
            return false;
        }

        // Admins can access all resources
        if ($user['role'] === 'admin') {
            return true;
        }

        return (int)$user['user_id'] === (int)$userId;
    }

    /**
     * Require authentication - exit with 401 if not authenticated
     *
     * @param string $token JWT token
     * @return array User data
     */
    public function requireAuth($token = null) {
        $user = $this->verifyToken($token);

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized. Valid token required.',
                'error_code' => 'UNAUTHORIZED'
            ]);
            exit;
        }

        return $user;
    }

    /**
     * Require specific role - exit with 403 if unauthorized
     *
     * @param string|array $requiredRoles Required role(s)
     * @param string $token JWT token
     * @return array User data
     */
    public function requireRole($requiredRoles, $token = null) {
        $user = $this->requireAuth($token);

        if (!$this->hasRole($requiredRoles, $token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden. Insufficient permissions.',
                'error_code' => 'FORBIDDEN'
            ]);
            exit;
        }

        return $user;
    }

    /**
     * Hash password using bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Check if authentication is rate limited
     *
     * @param string $identifier Username or email
     * @param string $ipAddress IP address
     * @return bool
     */
    private function isRateLimited($identifier, $ipAddress) {
        $maxAttempts = (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5);
        $lockoutDuration = (int)(getenv('LOCKOUT_DURATION') ?: 900); // 15 minutes

        $stmt = $this->db->prepare('
            SELECT COUNT(*) as attempt_count
            FROM audit_logs
            WHERE action = ?
            AND (details LIKE ? OR ip_address = ?)
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute(['LOGIN_FAILED', "%$identifier%", $ipAddress, $lockoutDuration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['attempt_count'] >= $maxAttempts;
    }

    /**
     * Log failed authentication attempt
     *
     * @param string $identifier Username or email
     * @param string $ipAddress IP address
     * @param string $reason Failure reason
     */
    private function logFailedAttempt($identifier, $ipAddress, $reason) {
        $this->logger->warning('Login attempt failed', [
            'identifier' => $identifier,
            'ip' => $ipAddress,
            'reason' => $reason
        ]);

        $this->logAudit(null, 'LOGIN_FAILED', 'users', null, $ipAddress, "Failed login for: $identifier - $reason");
    }

    /**
     * Update last login timestamp
     *
     * @param int $userId User ID
     * @param string $ipAddress IP address
     */
    private function updateLastLogin($userId, $ipAddress) {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Log audit entry
     *
     * @param int|null $userId User ID
     * @param string $action Action performed
     * @param string $tableName Table name
     * @param int|null $recordId Record ID
     * @param string|null $ipAddress IP address
     * @param string|null $details Additional details
     */
    private function logAudit($userId, $action, $tableName, $recordId = null, $ipAddress = null, $details = null) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, details)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $action, $tableName, $recordId, $ipAddress, $details]);
        } catch (Exception $e) {
            $this->logger->error('Audit log error: ' . $e->getMessage());
        }
    }

    /**
     * Logout user (blacklist token)
     *
     * @param string $token JWT token
     * @return bool
     */
    public function logout($token = null) {
        if (!$token) {
            $token = $this->jwt->getTokenFromHeader();
        }

        if (!$token) {
            return false;
        }

        $user = $this->verifyToken($token);

        if ($user) {
            $this->logger->info('User logged out', ['user_id' => $user['user_id']]);
            $this->logAudit($user['user_id'], 'LOGOUT', 'users', $user['user_id']);
        }

        return $this->jwt->blacklist($token);
    }
}
