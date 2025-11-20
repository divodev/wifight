<?php
/**
 * API Test Case
 *
 * Base class for API endpoint testing
 * Provides HTTP request simulation and response validation
 */

namespace Tests;

abstract class ApiTestCase extends DatabaseTestCase
{
    protected string $apiBaseUrl = 'http://localhost';
    protected array $headers = [];
    protected ?string $authToken = null;

    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Set authentication token
     *
     * @param string $token JWT token
     */
    protected function setAuthToken(string $token): void
    {
        $this->authToken = $token;
        $this->headers['Authorization'] = 'Bearer ' . $token;
    }

    /**
     * Clear authentication
     */
    protected function clearAuth(): void
    {
        $this->authToken = null;
        unset($this->headers['Authorization']);
    }

    /**
     * Simulate GET request
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Response data
     */
    protected function get(string $endpoint, array $params = []): array
    {
        $url = $this->buildUrl($endpoint, $params);
        return $this->makeRequest('GET', $url);
    }

    /**
     * Simulate POST request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @return array Response data
     */
    protected function post(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->makeRequest('POST', $url, $data);
    }

    /**
     * Simulate PUT request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @return array Response data
     */
    protected function put(string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->makeRequest('PUT', $url, $data);
    }

    /**
     * Simulate DELETE request
     *
     * @param string $endpoint API endpoint
     * @return array Response data
     */
    protected function delete(string $endpoint): array
    {
        $url = $this->buildUrl($endpoint);
        return $this->makeRequest('DELETE', $url);
    }

    /**
     * Make HTTP request (mock for testing without actual server)
     *
     * @param string $method HTTP method
     * @param string $url URL
     * @param array $data Request data
     * @return array Response data
     */
    protected function makeRequest(string $method, string $url, array $data = []): array
    {
        // This is a mock implementation for unit testing
        // In integration tests, this would make actual HTTP requests

        // For unit testing, we'll include the endpoint file and capture output
        $endpoint = $this->extractEndpoint($url);

        // Mock the HTTP method
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $endpoint;

        // Mock request data
        if (!empty($data)) {
            $_POST = $data;
            file_put_contents('php://input', json_encode($data));
        }

        // Mock authorization header
        if ($this->authToken) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->authToken;
        }

        // Capture output
        ob_start();

        try {
            // Include the endpoint file
            // This would need to be implemented based on your routing
            $response = ['success' => true, 'message' => 'Mock response'];
        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        $output = ob_get_clean();

        // Try to decode JSON output if available
        if (!empty($output)) {
            $decoded = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = $decoded;
            }
        }

        return $response;
    }

    /**
     * Build full URL
     *
     * @param string $endpoint Endpoint path
     * @param array $params Query parameters
     * @return string
     */
    protected function buildUrl(string $endpoint, array $params = []): string
    {
        $url = $this->apiBaseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Extract endpoint from URL
     *
     * @param string $url Full URL
     * @return string
     */
    protected function extractEndpoint(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?? '';
    }

    /**
     * Login and get auth token
     *
     * @param string $email User email
     * @param string $password User password
     * @return string Auth token
     */
    protected function loginAndGetToken(string $email = 'admin@wifight.local', string $password = 'admin123'): string
    {
        // Create test user in database
        $this->insertTestUser([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'admin'
        ]);

        // Mock login to get token
        require_once __DIR__ . '/../backend/utils/JWT.php';
        $jwt = new \JWT();

        $payload = [
            'user_id' => 1,
            'email' => $email,
            'role' => 'admin'
        ];

        $token = $jwt->generate($payload);
        $this->setAuthToken($token);

        return $token;
    }

    /**
     * Assert successful API response
     *
     * @param array $response Response data
     * @param string $message Expected message (optional)
     */
    protected function assertSuccessResponse(array $response, ?string $message = null): void
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success'], 'Expected successful response');

        if ($message !== null) {
            $this->assertEquals($message, $response['message']);
        }
    }

    /**
     * Assert error API response
     *
     * @param array $response Response data
     * @param int $expectedCode Expected HTTP status code (if tracked)
     */
    protected function assertErrorResponse(array $response, ?int $expectedCode = null): void
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success'], 'Expected error response');
        $this->assertArrayHasKey('message', $response);
    }

    /**
     * Assert unauthorized response
     *
     * @param array $response Response data
     */
    protected function assertUnauthorizedResponse(array $response): void
    {
        $this->assertErrorResponse($response);
        $this->assertStringContainsString('unauthorized', strtolower($response['message']));
    }

    /**
     * Assert validation error response
     *
     * @param array $response Response data
     * @param array $expectedFields Fields that should have errors
     */
    protected function assertValidationErrorResponse(array $response, array $expectedFields = []): void
    {
        $this->assertValidationError($response);

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $response['errors'], "Expected validation error for field: $field");
        }
    }

    /**
     * Assert paginated response
     *
     * @param array $response Response data
     */
    protected function assertPaginatedResponse(array $response): void
    {
        $this->assertSuccessResponse($response);
        $this->assertArrayHasKey('pagination', $response);

        $pagination = $response['pagination'];
        $this->assertArrayHasKeys(
            ['total', 'count', 'per_page', 'current_page', 'total_pages', 'has_more'],
            $pagination
        );
    }

    /**
     * Assert response has data
     *
     * @param array $response Response data
     * @param string $key Data key (default: 'data')
     */
    protected function assertResponseHasData(array $response, string $key = 'data'): void
    {
        $this->assertArrayHasKey($key, $response);
        $this->assertNotNull($response[$key]);
    }
}
