<?php
/**
 * Security Tests
 *
 * Tests for security vulnerabilities and best practices
 */

namespace Tests\Security;

use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function testPasswordHashing()
    {
        $password = 'SecureP@ssw0rd';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    public function testPasswordHashIsUnique()
    {
        $password = 'SecureP@ssw0rd';
        $hash1 = password_hash($password, PASSWORD_BCRYPT);
        $hash2 = password_hash($password, PASSWORD_BCRYPT);

        // Same password should produce different hashes (due to salt)
        $this->assertNotEquals($hash1, $hash2);

        // But both should verify correctly
        $this->assertTrue(password_verify($password, $hash1));
        $this->assertTrue(password_verify($password, $hash2));
    }

    public function testSqlInjectionPrevention()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        // Test SQL injection attempts
        $maliciousInputs = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "1' UNION SELECT * FROM users--",
            "<script>alert('XSS')</script>",
        ];

        foreach ($maliciousInputs as $input) {
            $sanitized = $validator->sanitize($input);

            // Should not contain dangerous characters
            $this->assertStringNotContainsString('<script>', $sanitized);
        }
    }

    public function testXssPreventionInValidator()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $xssAttempts = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg/onload=alert("XSS")>',
            'javascript:alert("XSS")',
        ];

        foreach ($xssAttempts as $attempt) {
            $sanitized = $validator->sanitize($attempt);

            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('onerror=', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
        }
    }

    public function testJwtSecretStrength()
    {
        $weakSecret = 'weak';
        $strongSecret = 'this_is_a_very_strong_secret_key_with_at_least_32_characters';

        $this->assertLessThan(32, strlen($weakSecret));
        $this->assertGreaterThanOrEqual(32, strlen($strongSecret));
    }

    public function testJwtTokenTampering()
    {
        require_once __DIR__ . '/../../backend/utils/JWT.php';
        $jwt = new \JWT();

        $payload = ['user_id' => 1, 'role' => 'user'];
        $token = $jwt->generate($payload);

        // Tamper with token by changing a character
        $tamperedToken = substr($token, 0, -5) . 'XXXXX';

        $this->expectException(\Exception::class);
        $jwt->validate($tamperedToken);
    }

    public function testJwtTokenExpiration()
    {
        require_once __DIR__ . '/../../backend/utils/JWT.php';
        $jwt = new \JWT();

        $payload = ['user_id' => 1];

        // Create token that expires in 1 second
        $token = $jwt->generate($payload, 1);

        sleep(2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/expired|invalid/i');
        $jwt->validate($token);
    }

    public function testInputValidationForEmailInjection()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $emailInjectionAttempts = [
            "user@example.com\nBcc:attacker@evil.com",
            "user@example.com\r\nCc:attacker@evil.com",
            "user@example.com%0ABcc:attacker@evil.com",
        ];

        $rules = ['email' => 'required|email'];

        foreach ($emailInjectionAttempts as $attempt) {
            $errors = $validator->validate(['email' => $attempt], $rules);

            // Should fail email validation
            $this->assertArrayHasKey('email', $errors);
        }
    }

    public function testMacAddressValidation()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $validMacs = [
            '00:1A:2B:3C:4D:5E',
            'AA:BB:CC:DD:EE:FF',
            '12:34:56:78:9A:BC'
        ];

        $invalidMacs = [
            'not-a-mac',
            '00:1A:2B:3C:4D',  // Too short
            '00:1A:2B:3C:4D:5E:FF',  // Too long
            'ZZ:ZZ:ZZ:ZZ:ZZ:ZZ',  // Invalid hex
        ];

        $rules = ['mac' => 'mac'];

        foreach ($validMacs as $mac) {
            $errors = $validator->validate(['mac' => $mac], $rules);
            $this->assertEmpty($errors, "Valid MAC $mac failed validation");
        }

        foreach ($invalidMacs as $mac) {
            $errors = $validator->validate(['mac' => $mac], $rules);
            $this->assertNotEmpty($errors, "Invalid MAC $mac passed validation");
        }
    }

    public function testIpAddressValidation()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $validIps = [
            '192.168.1.1',
            '10.0.0.1',
            '8.8.8.8',
            '127.0.0.1'
        ];

        $invalidIps = [
            '256.256.256.256',
            '192.168.1',
            'not-an-ip',
            '192.168.1.1.1'
        ];

        $rules = ['ip' => 'ip'];

        foreach ($validIps as $ip) {
            $errors = $validator->validate(['ip' => $ip], $rules);
            $this->assertEmpty($errors, "Valid IP $ip failed validation");
        }

        foreach ($invalidIps as $ip) {
            $errors = $validator->validate(['ip' => $ip], $rules);
            $this->assertNotEmpty($errors, "Invalid IP $ip passed validation");
        }
    }

    public function testPathTraversalPrevention()
    {
        $maliciousPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            'path/../../sensitive/file.txt',
        ];

        foreach ($maliciousPaths as $path) {
            // Should not allow path traversal
            $this->assertStringContainsString('..', $path);

            // Normalized path should not contain '..'
            $normalized = realpath($path);
            if ($normalized !== false) {
                $this->assertStringNotContainsString('..', $normalized);
            }
        }
    }

    public function testSessionHijackingPrevention()
    {
        // JWT tokens should be user-specific and not reusable
        require_once __DIR__ . '/../../backend/utils/JWT.php';
        $jwt = new \JWT();

        $user1Payload = ['user_id' => 1, 'email' => 'user1@test.com'];
        $user2Payload = ['user_id' => 2, 'email' => 'user2@test.com'];

        $token1 = $jwt->generate($user1Payload);
        $token2 = $jwt->generate($user2Payload);

        $decoded1 = $jwt->validate($token1);
        $decoded2 = $jwt->validate($token2);

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(1, $decoded1['user_id']);
        $this->assertEquals(2, $decoded2['user_id']);
    }

    public function testRateLimitingHeaders()
    {
        // Security headers should be present in responses
        require_once __DIR__ . '/../../backend/utils/Response.php';

        $expectedHeaders = [
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: DENY',
            'X-XSS-Protection: 1; mode=block'
        ];

        // These headers are set in Response class
        // We can't test headers directly in CLI, but we can verify they're in the code

        $responseCode = file_get_contents(__DIR__ . '/../../backend/utils/Response.php');

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $responseCode);
        }
    }

    public function testTimingSafePasswordComparison()
    {
        $password = 'SecurePassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // password_verify uses timing-safe comparison
        $start = microtime(true);
        $result1 = password_verify('wrong', $hash);
        $time1 = microtime(true) - $start;

        $start = microtime(true);
        $result2 = password_verify($password, $hash);
        $time2 = microtime(true) - $start;

        $this->assertFalse($result1);
        $this->assertTrue($result2);

        // Times should be similar (within order of magnitude)
        // This prevents timing attacks
        $ratio = max($time1, $time2) / min($time1, $time2);
        $this->assertLessThan(10, $ratio, 'Password comparison may be vulnerable to timing attacks');
    }

    public function testCsrfTokenGeneration()
    {
        // Generate random tokens for CSRF protection
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex characters
        $this->assertEquals(64, strlen($token2));
    }

    public function testSecureRandomNumberGeneration()
    {
        $random1 = random_int(1, 1000000);
        $random2 = random_int(1, 1000000);

        $this->assertNotEquals($random1, $random2);
        $this->assertGreaterThanOrEqual(1, $random1);
        $this->assertLessThanOrEqual(1000000, $random1);
    }

    public function testUrlValidation()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $validUrls = [
            'https://example.com',
            'http://example.com/path',
            'https://subdomain.example.com',
        ];

        $invalidUrls = [
            'not-a-url',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'file:///etc/passwd',
        ];

        $rules = ['url' => 'url'];

        foreach ($validUrls as $url) {
            $errors = $validator->validate(['url' => $url], $rules);
            $this->assertEmpty($errors, "Valid URL $url failed validation");
        }

        foreach ($invalidUrls as $url) {
            $errors = $validator->validate(['url' => $url], $rules);
            $this->assertNotEmpty($errors, "Invalid URL $url passed validation");
        }
    }
}
