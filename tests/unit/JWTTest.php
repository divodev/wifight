<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/utils/JWT.php';

class JWTTest extends TestCase
{
    private JWT $jwt;
    private array $testPayload;

    protected function setUp(): void
    {
        $this->jwt = new JWT();
        $this->testPayload = [
            'user_id' => 1,
            'email' => 'test@example.com',
            'role' => 'admin'
        ];
    }

    public function testTokenGeneration()
    {
        $token = $this->jwt->generate($this->testPayload);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testTokenValidation()
    {
        $token = $this->jwt->generate($this->testPayload);
        $decoded = $this->jwt->validate($token);

        $this->assertIsArray($decoded);
        $this->assertEquals($this->testPayload['user_id'], $decoded['user_id']);
        $this->assertEquals($this->testPayload['email'], $decoded['email']);
        $this->assertEquals($this->testPayload['role'], $decoded['role']);
    }

    public function testTokenExpiration()
    {
        // Create token with 1 second expiration
        $token = $this->jwt->generate($this->testPayload, 1);

        // Wait for token to expire
        sleep(2);

        $this->expectException(Exception::class);
        $this->jwt->validate($token);
    }

    public function testInvalidToken()
    {
        $this->expectException(Exception::class);
        $this->jwt->validate('invalid.token.here');
    }

    public function testRefreshToken()
    {
        $refreshToken = $this->jwt->generateRefreshToken($this->testPayload);

        $this->assertIsString($refreshToken);
        $this->assertNotEmpty($refreshToken);
    }

    public function testTokenPairCreation()
    {
        $tokens = $this->jwt->createTokenPair($this->testPayload);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
    }

    public function testGetTokenFromHeader()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test.token.here';

        $token = $this->jwt->getTokenFromHeader();

        $this->assertEquals('test.token.here', $token);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testGetTokenFromHeaderFails()
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $token = $this->jwt->getTokenFromHeader();

        $this->assertNull($token);
    }
}
