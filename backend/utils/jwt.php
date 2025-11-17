<?php
/*
 * This is a basic polyfill for the firebase/php-jwt library.
 * For a real production environment, you should install the library via Composer:
 * composer require firebase/php-jwt
 *
 * This basic implementation is for demonstration only.
 */

namespace Firebase\JWT;

class JWT
{
    public static function encode($payload, $key, $alg)
    {
        if ($alg !== 'HS256') {
            throw new \Exception('Unsupported algorithm');
        }
        $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        $payload = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $key, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private static function base64UrlEncode($text)
    {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }
}

// Exception class if it doesn't exist
if (!class_exists('Exception')) {
    class Exception extends \Exception {}
}
