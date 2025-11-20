<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/utils/Auth.php';
require_once __DIR__ . '/../../backend/config/database.php';

class AuthenticationTest extends TestCase
{
    private Auth $auth;
    private PDO $db;
    private int $testUserId;

    protected function setUp(): void
    {
        $this->auth = new Auth();
        $this->db = getTestDatabase();

        // Create test user
        $this->createTestUser();
    }

    protected function tearDown(): void
    {
        // Clean up test user
        if (isset($this->testUserId)) {
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$this->testUserId]);
        }
    }

    private function createTestUser()
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (username, email, password, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            'testuser',
            'test@example.com',
            password_hash('testpassword123', PASSWORD_BCRYPT),
            'user',
            'active'
        ]);

        $this->testUserId = (int)$this->db->lastInsertId();
    }

    public function testAuthenticateWithEmail()
    {
        $result = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    public function testAuthenticateWithUsername()
    {
        $result = $this->auth->authenticate('testuser', 'testpassword123', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result);
    }

    public function testAuthenticateWithWrongPassword()
    {
        $result = $this->auth->authenticate('test@example.com', 'wrongpassword', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testAuthenticateWithNonExistentUser()
    {
        $result = $this->auth->authenticate('nonexistent@example.com', 'password', '127.0.0.1');

        $this->assertFalse($result['success']);
    }

    public function testVerifyValidToken()
    {
        $authResult = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');
        $token = $authResult['token'];

        $user = $this->auth->verifyToken($token);

        $this->assertIsArray($user);
        $this->assertEquals('test@example.com', $user['email']);
    }

    public function testVerifyInvalidToken()
    {
        $this->expectException(Exception::class);
        $this->auth->verifyToken('invalid.token.here');
    }

    public function testHasRole()
    {
        $authResult = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');
        $token = $authResult['token'];

        $this->assertTrue($this->auth->hasRole(['user'], $token));
        $this->assertFalse($this->auth->hasRole(['admin'], $token));
    }

    public function testRequireAuth()
    {
        $authResult = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');
        $token = $authResult['token'];

        $user = $this->auth->requireAuth($token);

        $this->assertIsArray($user);
        $this->assertEquals('test@example.com', $user['email']);
    }

    public function testRequireAuthWithoutToken()
    {
        $this->expectException(Exception::class);
        $this->auth->requireAuth(null);
    }

    public function testRequireRole()
    {
        $authResult = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');
        $token = $authResult['token'];

        $user = $this->auth->requireRole(['user'], $token);

        $this->assertIsArray($user);
    }

    public function testRequireRoleWithWrongRole()
    {
        $authResult = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');
        $token = $authResult['token'];

        $this->expectException(Exception::class);
        $this->auth->requireRole(['admin'], $token);
    }

    public function testBruteForceProtection()
    {
        // Attempt login 6 times with wrong password
        for ($i = 0; $i < 6; $i++) {
            $this->auth->authenticate('test@example.com', 'wrongpassword', '127.0.0.1');
        }

        // Next attempt should be blocked
        $result = $this->auth->authenticate('test@example.com', 'testpassword123', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('locked', strtolower($result['error']));
    }
}
