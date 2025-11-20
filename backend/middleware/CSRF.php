<?php
/**
 * WiFight ISP System - CSRF Protection Middleware
 *
 * Cross-Site Request Forgery protection
 */

require_once __DIR__ . '/../utils/Response.php';

class CSRF {
    private $sessionKey = 'csrf_token';
    private $headerName = 'X-CSRF-TOKEN';
    private $tokenLength = 32;

    /**
     * Generate CSRF token
     *
     * @return string CSRF token
     */
    public function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes($this->tokenLength));
        $_SESSION[$this->sessionKey] = $token;

        return $token;
    }

    /**
     * Get current CSRF token (generate if not exists)
     *
     * @return string CSRF token
     */
    public function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey])) {
            return $this->generateToken();
        }

        return $_SESSION[$this->sessionKey];
    }

    /**
     * Validate CSRF token from request
     *
     * @param string|null $token Token to validate (if null, will check header and POST)
     * @return bool True if valid, false otherwise
     */
    public function validateToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey])) {
            return false;
        }

        $expectedToken = $_SESSION[$this->sessionKey];

        // Get token from request if not provided
        if ($token === null) {
            // Check header first
            $headers = getallheaders();
            if (isset($headers[$this->headerName])) {
                $token = $headers[$this->headerName];
            }
            // Then check POST data
            elseif (isset($_POST['csrf_token'])) {
                $token = $_POST['csrf_token'];
            }
            // Check JSON body
            elseif ($_SERVER['CONTENT_TYPE'] === 'application/json') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['csrf_token'])) {
                    $token = $input['csrf_token'];
                }
            }
        }

        if ($token === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedToken, $token);
    }

    /**
     * CSRF middleware for state-changing requests (POST, PUT, DELETE)
     */
    public function middleware() {
        $method = $_SERVER['REQUEST_METHOD'];

        // Only check CSRF for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return;
        }

        if (!$this->validateToken()) {
            http_response_code(403);
            Response::error('CSRF token validation failed', 403, [
                'error_code' => 'CSRF_VALIDATION_FAILED'
            ]);
            exit;
        }
    }

    /**
     * Refresh CSRF token (regenerate)
     *
     * @return string New CSRF token
     */
    public function refreshToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $this->generateToken();
    }

    /**
     * Clear CSRF token from session
     */
    public function clearToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[$this->sessionKey]);
    }

    /**
     * Generate HTML hidden input field with CSRF token
     *
     * @return string HTML input field
     */
    public function generateHiddenField() {
        $token = $this->getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate meta tag with CSRF token (for JavaScript)
     *
     * @return string HTML meta tag
     */
    public function generateMetaTag() {
        $token = $this->getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Check if request should be exempt from CSRF check
     *
     * @param array $exemptPaths Array of paths to exempt (e.g., ['/api/webhook'])
     * @return bool
     */
    public function isExempt($exemptPaths = []) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        foreach ($exemptPaths as $path) {
            if (strpos($requestUri, $path) !== false) {
                return true;
            }
        }

        return false;
    }
}
