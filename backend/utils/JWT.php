<?php
/**
 * WiFight ISP System - JWT Utility
 *
 * JSON Web Token authentication handler
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT {
    private $secret;
    private $algorithm;
    private $expiration;
    private $refreshExpiration;

    public function __construct() {
        $this->secret = getenv('JWT_SECRET') ?: 'default-secret-change-this';
        $this->algorithm = getenv('JWT_ALGORITHM') ?: 'HS256';
        $this->expiration = (int)(getenv('JWT_EXPIRATION') ?: 3600);
        $this->refreshExpiration = (int)(getenv('JWT_REFRESH_EXPIRATION') ?: 604800);
    }

    /**
     * Generate JWT token
     *
     * @param array $payload Token payload
     * @param bool $isRefreshToken
     * @return string
     */
    public function generate($payload, $isRefreshToken = false) {
        $issuedAt = time();
        $expiration = $isRefreshToken
            ? $issuedAt + $this->refreshExpiration
            : $issuedAt + $this->expiration;

        $token = [
            'iat' => $issuedAt,
            'exp' => $expiration,
            'data' => $payload
        ];

        if ($isRefreshToken) {
            $token['type'] = 'refresh';
        }

        return FirebaseJWT::encode($token, $this->secret, $this->algorithm);
    }

    /**
     * Validate and decode JWT token
     *
     * @param string $token
     * @return object|null
     */
    public function validate($token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, $this->algorithm));
            return $decoded;
        } catch (Exception $e) {
            error_log('JWT Validation Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decode JWT token without validation (use with caution)
     *
     * @param string $token
     * @return object|null
     */
    public function decode($token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, $this->algorithm));
            return $decoded->data ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if token is expired
     *
     * @param string $token
     * @return bool
     */
    public function isExpired($token) {
        $decoded = $this->validate($token);

        if (!$decoded) {
            return true;
        }

        return $decoded->exp < time();
    }

    /**
     * Get token expiration time
     *
     * @param string $token
     * @return int|null
     */
    public function getExpiration($token) {
        $decoded = $this->validate($token);
        return $decoded->exp ?? null;
    }

    /**
     * Extract token from Authorization header
     *
     * @return string|null
     */
    public function getTokenFromHeader() {
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            $matches = [];
            if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Refresh an existing token
     *
     * @param string $refreshToken
     * @return array|null ['access_token' => string, 'refresh_token' => string]
     */
    public function refresh($refreshToken) {
        $decoded = $this->validate($refreshToken);

        if (!$decoded || !isset($decoded->type) || $decoded->type !== 'refresh') {
            return null;
        }

        $payload = (array)$decoded->data;

        return [
            'access_token' => $this->generate($payload, false),
            'refresh_token' => $this->generate($payload, true),
            'expires_in' => $this->expiration
        ];
    }

    /**
     * Create token pair (access + refresh)
     *
     * @param array $payload
     * @return array
     */
    public function createTokenPair($payload) {
        return [
            'access_token' => $this->generate($payload, false),
            'refresh_token' => $this->generate($payload, true),
            'token_type' => 'Bearer',
            'expires_in' => $this->expiration
        ];
    }

    /**
     * Blacklist a token (requires Redis or database)
     *
     * @param string $token
     * @return bool
     */
    public function blacklist($token) {
        // TODO: Implement token blacklisting with Redis or database
        // For now, just log the action
        error_log('Token blacklisted: ' . substr($token, 0, 20) . '...');
        return true;
    }

    /**
     * Check if token is blacklisted
     *
     * @param string $token
     * @return bool
     */
    public function isBlacklisted($token) {
        // TODO: Implement blacklist checking with Redis or database
        return false;
    }
}
