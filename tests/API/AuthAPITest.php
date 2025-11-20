<?php

use PHPUnit\Framework\TestCase;

class AuthAPITest extends TestCase
{
    private string $baseUrl = 'http://localhost/api/v1';
    private int $testUserId;

    protected function setUp(): void
    {
        // Create test user directly in database
        $db = getTestDatabase();
        $stmt = $db->prepare('
            INSERT INTO users (username, email, password, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            'apitest',
            'apitest@example.com',
            password_hash('TestPass123!', PASSWORD_BCRYPT),
            'user',
            'active'
        ]);

        $this->testUserId = (int)$db->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Clean up
        if (isset($this->testUserId)) {
            $db = getTestDatabase();
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$this->testUserId]);
        }
    }

    public function testLoginEndpoint()
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'apitest@example.com',
            'password' => 'TestPass123!'
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('access_token', $response['body']['data']);
        $this->assertArrayHasKey('refresh_token', $response['body']['data']);
    }

    public function testLoginWithInvalidCredentials()
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'apitest@example.com',
            'password' => 'wrongpassword'
        ]);

        $this->assertEquals(401, $response['status']);
        $this->assertFalse($response['body']['success']);
    }

    public function testLoginWithMissingFields()
    {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'apitest@example.com'
        ]);

        $this->assertEquals(400, $response['status']);
    }

    public function testAccessProtectedEndpointWithoutToken()
    {
        $response = $this->makeRequest('GET', '/users/me');

        $this->assertEquals(401, $response['status']);
    }

    public function testAccessProtectedEndpointWithToken()
    {
        // First login
        $loginResponse = $this->makeRequest('POST', '/auth/login', [
            'email' => 'apitest@example.com',
            'password' => 'TestPass123!'
        ]);

        $token = $loginResponse['body']['data']['access_token'];

        // Access protected endpoint
        $response = $this->makeRequest('GET', '/users/me', null, [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertEquals('apitest@example.com', $response['body']['data']['email']);
    }

    public function testLogout()
    {
        // First login
        $loginResponse = $this->makeRequest('POST', '/auth/login', [
            'email' => 'apitest@example.com',
            'password' => 'TestPass123!'
        ]);

        $token = $loginResponse['body']['data']['access_token'];

        // Logout
        $response = $this->makeRequest('POST', '/auth/logout', null, [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
    }

    private function makeRequest(string $method, string $endpoint, ?array $data = null, array $headers = [])
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);

        $defaultHeaders = [
            'Content-Type' => 'application/json'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);
        $headerArray = [];
        foreach ($allHeaders as $key => $value) {
            $headerArray[] = "$key: $value";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => json_decode($response, true)
        ];
    }
}
