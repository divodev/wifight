<?php
/**
 * WiFight ISP System - Rate Limiting Middleware
 *
 * Protects against brute force attacks and API abuse
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';

class RateLimit {
    private $logger;
    private $storageFile;

    // Rate limit configurations
    private $limits = [
        'default' => ['requests' => 60, 'window' => 60],      // 60 requests per minute
        'auth' => ['requests' => 5, 'window' => 300],         // 5 requests per 5 minutes
        'api' => ['requests' => 100, 'window' => 60],         // 100 requests per minute
        'strict' => ['requests' => 10, 'window' => 60]        // 10 requests per minute
    ];

    public function __construct() {
        $this->logger = new Logger();
        $this->storageFile = __DIR__ . '/../../storage/cache/rate_limit.json';

        // Create storage directory if it doesn't exist
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Check rate limit for a specific identifier
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param string $limitType Type of limit ('default', 'auth', 'api', 'strict')
     * @return bool True if allowed, false if rate limited
     */
    public function check($identifier, $limitType = 'default') {
        if (!isset($this->limits[$limitType])) {
            $limitType = 'default';
        }

        $limit = $this->limits[$limitType];
        $key = $this->getKey($identifier, $limitType);

        $data = $this->loadData();
        $now = time();

        // Clean up old entries
        $this->cleanup($data, $now);

        // Check if key exists
        if (!isset($data[$key])) {
            $data[$key] = [
                'count' => 1,
                'reset_time' => $now + $limit['window'],
                'first_request' => $now
            ];
            $this->saveData($data);
            return true;
        }

        $entry = $data[$key];

        // Check if window has expired
        if ($now > $entry['reset_time']) {
            $data[$key] = [
                'count' => 1,
                'reset_time' => $now + $limit['window'],
                'first_request' => $now
            ];
            $this->saveData($data);
            return true;
        }

        // Check if limit exceeded
        if ($entry['count'] >= $limit['requests']) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'limit_type' => $limitType,
                'count' => $entry['count'],
                'max' => $limit['requests']
            ]);
            return false;
        }

        // Increment counter
        $data[$key]['count']++;
        $this->saveData($data);

        return true;
    }

    /**
     * Middleware function to be used in API routes
     *
     * @param string $limitType Type of limit
     */
    public function middleware($limitType = 'default') {
        $identifier = $this->getIdentifier();

        if (!$this->check($identifier, $limitType)) {
            $limit = $this->limits[$limitType];
            $retryAfter = $this->getRetryAfter($identifier, $limitType);

            http_response_code(429);
            header("Retry-After: $retryAfter");
            header('X-RateLimit-Limit: ' . $limit['requests']);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . (time() + $retryAfter));

            Response::error('Too many requests. Please try again later.', 429, [
                'retry_after' => $retryAfter,
                'limit' => $limit['requests'],
                'window' => $limit['window']
            ]);
            exit;
        }

        // Add rate limit headers
        $this->addHeaders($identifier, $limitType);
    }

    /**
     * Add rate limit information headers
     *
     * @param string $identifier Unique identifier
     * @param string $limitType Type of limit
     */
    private function addHeaders($identifier, $limitType) {
        $limit = $this->limits[$limitType];
        $key = $this->getKey($identifier, $limitType);
        $data = $this->loadData();

        if (isset($data[$key])) {
            $remaining = max(0, $limit['requests'] - $data[$key]['count']);
            $reset = $data[$key]['reset_time'];

            header('X-RateLimit-Limit: ' . $limit['requests']);
            header('X-RateLimit-Remaining: ' . $remaining);
            header('X-RateLimit-Reset: ' . $reset);
        }
    }

    /**
     * Get unique identifier for rate limiting
     *
     * @return string Identifier (IP + User Agent hash)
     */
    private function getIdentifier() {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return md5($ip . $userAgent);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function getClientIP() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    // CloudFlare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle multiple IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get rate limit key
     *
     * @param string $identifier Unique identifier
     * @param string $limitType Type of limit
     * @return string Key
     */
    private function getKey($identifier, $limitType) {
        return md5($identifier . ':' . $limitType);
    }

    /**
     * Get retry after time in seconds
     *
     * @param string $identifier Unique identifier
     * @param string $limitType Type of limit
     * @return int Seconds until retry allowed
     */
    private function getRetryAfter($identifier, $limitType) {
        $key = $this->getKey($identifier, $limitType);
        $data = $this->loadData();

        if (isset($data[$key])) {
            return max(0, $data[$key]['reset_time'] - time());
        }

        return $this->limits[$limitType]['window'];
    }

    /**
     * Load rate limit data from storage
     *
     * @return array Rate limit data
     */
    private function loadData() {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $content = file_get_contents($this->storageFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Save rate limit data to storage
     *
     * @param array $data Rate limit data
     */
    private function saveData($data) {
        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up expired entries
     *
     * @param array &$data Rate limit data
     * @param int $now Current timestamp
     */
    private function cleanup(&$data, $now) {
        $cleaned = 0;

        foreach ($data as $key => $entry) {
            if ($now > $entry['reset_time'] + 300) { // Keep for 5 extra minutes
                unset($data[$key]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->logger->debug("Cleaned up $cleaned expired rate limit entries");
        }
    }

    /**
     * Reset rate limit for an identifier
     *
     * @param string $identifier Unique identifier
     * @param string $limitType Type of limit
     */
    public function reset($identifier, $limitType = 'default') {
        $key = $this->getKey($identifier, $limitType);
        $data = $this->loadData();

        if (isset($data[$key])) {
            unset($data[$key]);
            $this->saveData($data);
            $this->logger->info('Rate limit reset', ['identifier' => $identifier, 'limit_type' => $limitType]);
        }
    }

    /**
     * Get current rate limit status
     *
     * @param string $identifier Unique identifier
     * @param string $limitType Type of limit
     * @return array Status information
     */
    public function getStatus($identifier, $limitType = 'default') {
        $limit = $this->limits[$limitType];
        $key = $this->getKey($identifier, $limitType);
        $data = $this->loadData();
        $now = time();

        if (!isset($data[$key]) || $now > $data[$key]['reset_time']) {
            return [
                'limit' => $limit['requests'],
                'remaining' => $limit['requests'],
                'reset_time' => $now + $limit['window'],
                'reset_in' => $limit['window']
            ];
        }

        $entry = $data[$key];

        return [
            'limit' => $limit['requests'],
            'remaining' => max(0, $limit['requests'] - $entry['count']),
            'reset_time' => $entry['reset_time'],
            'reset_in' => max(0, $entry['reset_time'] - $now)
        ];
    }

    /**
     * Configure custom rate limits
     *
     * @param string $name Limit name
     * @param int $requests Number of requests
     * @param int $window Time window in seconds
     */
    public function setLimit($name, $requests, $window) {
        $this->limits[$name] = [
            'requests' => $requests,
            'window' => $window
        ];
    }
}
