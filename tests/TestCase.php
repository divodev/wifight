<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case
 * 
 * Provides common testing functionality for all test classes
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Database connection for testing
     */
    protected static $db;

    /**
     * Test database name
     */
    protected static $testDatabase = 'wifight_test';

    /**
     * Setup database connection before tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Load environment variables
        if (file_exists(__DIR__ . '/../.env.testing')) {
            $lines = file(__DIR__ . '/../.env.testing', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    putenv(trim($line));
                }
            }
        }

        // Create database connection
        try {
            static::$db = new PDO(
                'mysql:host=localhost',
                getenv('DB_USERNAME') ?: 'root',
                getenv('DB_PASSWORD') ?: ''
            );
            static::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Setup test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test database if it doesn't exist
        if (static::$db) {
            static::$db->exec("CREATE DATABASE IF NOT EXISTS " . static::$testDatabase);
            static::$db->exec("USE " . static::$testDatabase);
        }
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Make an HTTP GET request
     */
    protected function get($uri, $headers = [])
    {
        return $this->makeRequest('GET', $uri, [], $headers);
    }

    /**
     * Make an HTTP POST request
     */
    protected function post($uri, $data = [], $headers = [])
    {
        return $this->makeRequest('POST', $uri, $data, $headers);
    }

    /**
     * Make an HTTP PUT request
     */
    protected function put($uri, $data = [], $headers = [])
    {
        return $this->makeRequest('PUT', $uri, $data, $headers);
    }

    /**
     * Make an HTTP DELETE request
     */
    protected function delete($uri, $headers = [])
    {
        return $this->makeRequest('DELETE', $uri, [], $headers);
    }

    /**
     * Make HTTP request
     */
    protected function makeRequest($method, $uri, $data = [], $headers = [])
    {
        $ch = curl_init();

        $url = 'http://localhost' . $uri;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return new TestResponse($response, $httpCode);
    }

    /**
     * Assert that a record exists in the database
     */
    protected function assertDatabaseHas($table, $data)
    {
        $conditions = [];
        $params = [];

        foreach ($data as $key => $value) {
            $conditions[] = "$key = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $conditions);
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);

        $count = $stmt->fetchColumn();

        $this->assertGreaterThan(
            0,
            $count,
            "Failed asserting that table [$table] contains matching record."
        );
    }

    /**
     * Assert that a record does not exist in the database
     */
    protected function assertDatabaseMissing($table, $data)
    {
        $conditions = [];
        $params = [];

        foreach ($data as $key => $value) {
            $conditions[] = "$key = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $conditions);
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);

        $count = $stmt->fetchColumn();

        $this->assertEquals(
            0,
            $count,
            "Failed asserting that table [$table] does not contain matching record."
        );
    }

    /**
     * Create a test user
     */
    protected function createUser($overrides = [])
    {
        $data = array_merge([
            'name' => 'Test User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active'
        ], $overrides);

        return $data;
    }
}

/**
 * Test Response Helper
 */
class TestResponse
{
    protected $content;
    protected $statusCode;

    public function __construct($content, $statusCode)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
    }

    public function assertStatus($expected)
    {
        if ($this->statusCode !== $expected) {
            throw new Exception(
                "Expected status code {$expected} but received {$this->statusCode}. Response: {$this->content}"
            );
        }
        return $this;
    }

    public function assertJson()
    {
        json_decode($this->content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Response is not valid JSON: {$this->content}");
        }
        return $this;
    }

    public function assertJsonStructure($structure)
    {
        $data = json_decode($this->content, true);

        $this->validateJsonStructure($structure, $data);

        return $this;
    }

    protected function validateJsonStructure($structure, $data, $path = '')
    {
        foreach ($structure as $key => $value) {
            if (is_numeric($key)) {
                // Array of keys
                if (!isset($data[$value])) {
                    throw new Exception("Missing key '{$value}' in JSON response at path: {$path}");
                }
            } else {
                // Nested structure
                if (!isset($data[$key])) {
                    throw new Exception("Missing key '{$key}' in JSON response at path: {$path}");
                }
                if (is_array($value)) {
                    $this->validateJsonStructure($value, $data[$key], $path . '.' . $key);
                }
            }
        }
    }

    public function getJson()
    {
        return json_decode($this->content, true);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
