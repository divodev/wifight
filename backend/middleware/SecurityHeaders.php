<?php
/**
 * WiFight ISP System - Security Headers Middleware
 *
 * Applies security-related HTTP headers to protect against common attacks
 */

class SecurityHeaders {
    private $headers = [];
    private $isDevelopment = false;

    public function __construct() {
        $this->isDevelopment = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');

        // Default security headers
        $this->headers = [
            // Prevent clickjacking attacks
            'X-Frame-Options' => 'SAMEORIGIN',

            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',

            // Enable XSS protection (for older browsers)
            'X-XSS-Protection' => '1; mode=block',

            // Control referrer information
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Permissions Policy (formerly Feature Policy)
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',

            // Content Security Policy
            'Content-Security-Policy' => $this->getCSP(),

            // Strict Transport Security (HTTPS only - don't use in development)
            'Strict-Transport-Security' => $this->isDevelopment ? null : 'max-age=31536000; includeSubDomains; preload',

            // Remove server information
            'X-Powered-By' => ''
        ];
    }

    /**
     * Apply security headers
     */
    public function apply() {
        // Remove PHP version information
        header_remove('X-Powered-By');

        foreach ($this->headers as $header => $value) {
            if ($value !== null && $value !== '') {
                header("$header: $value");
            }
        }
    }

    /**
     * Apply security headers as middleware
     */
    public function middleware() {
        $this->apply();
    }

    /**
     * Get Content Security Policy
     *
     * @return string CSP directive
     */
    private function getCSP() {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Adjust based on your needs
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        // In development, add more lenient policies
        if ($this->isDevelopment) {
            $directives[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:* 127.0.0.1:*";
            $directives[] = "connect-src 'self' ws://localhost:* ws://127.0.0.1:*";
        }

        return implode('; ', $directives);
    }

    /**
     * Set CORS headers for API
     *
     * @param array $allowedOrigins Array of allowed origins (default: *)
     * @param array $allowedMethods Array of allowed HTTP methods
     * @param array $allowedHeaders Array of allowed headers
     * @param int $maxAge Max age for preflight cache
     */
    public function setCORS(
        $allowedOrigins = ['*'],
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        $allowedHeaders = ['Content-Type', 'Authorization', 'X-CSRF-TOKEN'],
        $maxAge = 3600
    ) {
        // Get request origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            header('Access-Control-Allow-Credentials: true');
        }

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
            header('Access-Control-Max-Age: ' . $maxAge);
            http_response_code(200);
            exit;
        }

        // Expose headers
        header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
    }

    /**
     * Set custom header
     *
     * @param string $name Header name
     * @param string $value Header value
     */
    public function setHeader($name, $value) {
        $this->headers[$name] = $value;
    }

    /**
     * Remove header
     *
     * @param string $name Header name
     */
    public function removeHeader($name) {
        $this->headers[$name] = null;
    }

    /**
     * Set Content Security Policy
     *
     * @param string $csp CSP directive
     */
    public function setCSP($csp) {
        $this->headers['Content-Security-Policy'] = $csp;
    }

    /**
     * Apply headers for file downloads
     *
     * @param string $filename File name
     * @param string $contentType Content type
     */
    public function applyDownloadHeaders($filename, $contentType = 'application/octet-stream') {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Apply cache control headers
     *
     * @param string $type Type of caching ('no-cache', 'private', 'public')
     * @param int $maxAge Max age in seconds
     */
    public function setCacheControl($type = 'no-cache', $maxAge = 0) {
        switch ($type) {
            case 'no-cache':
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                break;

            case 'private':
                header("Cache-Control: private, max-age=$maxAge");
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
                break;

            case 'public':
                header("Cache-Control: public, max-age=$maxAge");
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
                break;
        }
    }

    /**
     * Apply JSON response headers
     */
    public function applyJSONHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        $this->setCacheControl('no-cache');
    }

    /**
     * Check if connection is secure (HTTPS)
     *
     * @return bool
     */
    public function isSecureConnection() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
    }

    /**
     * Force HTTPS redirect (use with caution)
     */
    public function forceHTTPS() {
        if (!$this->isSecureConnection() && !$this->isDevelopment) {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }

    /**
     * Get all configured headers
     *
     * @return array Headers array
     */
    public function getHeaders() {
        return array_filter($this->headers, function($value) {
            return $value !== null && $value !== '';
        });
    }
}
