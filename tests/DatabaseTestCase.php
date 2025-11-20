<?php
/**
 * Database Test Case
 *
 * Base class for tests that require database access
 * Automatically handles transactions and rollback
 */

namespace Tests;

use PDO;

abstract class DatabaseTestCase extends TestCase
{
    protected ?PDO $pdo = null;
    protected bool $inTransaction = false;

    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfNoDatabaseConnection();
        $this->pdo = $this->getTestDatabaseConnection();

        // Start transaction for test isolation
        $this->beginTransaction();
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        $this->rollbackTransaction();

        $this->pdo = null;

        parent::tearDown();
    }

    /**
     * Get test database connection
     *
     * @return PDO
     */
    protected function getTestDatabaseConnection(): PDO
    {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=wifight_isp_test",
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $pdo;
        } catch (\PDOException $e) {
            $this->fail('Failed to connect to test database: ' . $e->getMessage());
        }
    }

    /**
     * Begin transaction
     */
    protected function beginTransaction(): void
    {
        if (!$this->inTransaction && $this->pdo) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * Rollback transaction
     */
    protected function rollbackTransaction(): void
    {
        if ($this->inTransaction && $this->pdo) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * Insert test user into database
     *
     * @param array $data User data
     * @return int User ID
     */
    protected function insertTestUser(array $data = []): int
    {
        $defaults = [
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'full_name' => 'Test User',
            'role' => 'user',
            'status' => 'active'
        ];

        $data = array_merge($defaults, $data);

        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, username, password, full_name, role, status, created_at)
            VALUES (:email, :username, :password, :full_name, :role, :status, NOW())
        ");

        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert test plan into database
     *
     * @param array $data Plan data
     * @return int Plan ID
     */
    protected function insertTestPlan(array $data = []): int
    {
        $defaults = [
            'name' => 'Test Plan',
            'bandwidth_download' => 10000,
            'bandwidth_upload' => 5000,
            'price' => 29.99,
            'duration_days' => 30,
            'status' => 'active'
        ];

        $data = array_merge($defaults, $data);

        $stmt = $this->pdo->prepare("
            INSERT INTO plans (name, bandwidth_download, bandwidth_upload, price, duration_days, status, created_at)
            VALUES (:name, :bandwidth_download, :bandwidth_upload, :price, :duration_days, :status, NOW())
        ");

        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert test controller into database
     *
     * @param array $data Controller data
     * @return int Controller ID
     */
    protected function insertTestController(array $data = []): int
    {
        $userId = $data['user_id'] ?? $this->insertTestUser(['role' => 'reseller']);

        $defaults = [
            'user_id' => $userId,
            'name' => 'Test Controller',
            'type' => 'mikrotik',
            'host' => '192.168.1.1',
            'username' => 'admin',
            'password' => 'password',
            'status' => 'active'
        ];

        $data = array_merge($defaults, $data);

        $stmt = $this->pdo->prepare("
            INSERT INTO controllers (user_id, name, type, host, username, password, status, created_at)
            VALUES (:user_id, :name, :type, :host, :username, :password, :status, NOW())
        ");

        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get user by ID
     *
     * @param int $id User ID
     * @return array|null
     */
    protected function getUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Assert database has record
     *
     * @param string $table Table name
     * @param array $conditions Where conditions
     */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as count FROM $table WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        $this->assertGreaterThan(
            0,
            $result['count'],
            "Failed asserting that table '$table' has matching record"
        );
    }

    /**
     * Assert database missing record
     *
     * @param string $table Table name
     * @param array $conditions Where conditions
     */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as count FROM $table WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        $this->assertEquals(
            0,
            $result['count'],
            "Failed asserting that table '$table' does not have matching record"
        );
    }

    /**
     * Truncate table
     *
     * @param string $table Table name
     */
    protected function truncateTable(string $table): void
    {
        $this->pdo->exec("TRUNCATE TABLE $table");
    }

    /**
     * Get record count from table
     *
     * @param string $table Table name
     * @param array $conditions Optional where conditions
     * @return int
     */
    protected function getTableCount(string $table, array $conditions = []): int
    {
        if (empty($conditions)) {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM $table");
        } else {
            $where = [];
            $params = [];

            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }

            $sql = "SELECT COUNT(*) as count FROM $table WHERE " . implode(' AND ', $where);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        $result = $stmt->fetch();
        return (int) $result['count'];
    }
}
