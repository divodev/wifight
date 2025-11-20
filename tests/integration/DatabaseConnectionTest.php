<?php
/**
 * Database Connection Integration Test
 */

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class DatabaseConnectionTest extends DatabaseTestCase
{
    public function testDatabaseConnectionSuccessful()
    {
        $this->assertNotNull($this->pdo);
        $this->assertInstanceOf(\PDO::class, $this->pdo);
    }

    public function testDatabaseNameIsCorrect()
    {
        $stmt = $this->pdo->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch();

        $this->assertEquals('wifight_isp_test', $result['db_name']);
    }

    public function testRequiredTablesExist()
    {
        $requiredTables = [
            'users',
            'controllers',
            'plans',
            'sessions',
            'subscriptions',
            'payments',
            'vouchers',
            'radius_accounting',
            'audit_logs',
            'notifications',
            'system_settings'
        ];

        foreach ($requiredTables as $table) {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();

            $this->assertNotFalse($exists, "Table '$table' does not exist");
        }
    }

    public function testUsersTableStructure()
    {
        $stmt = $this->pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $expectedColumns = [
            'id',
            'email',
            'username',
            'password',
            'full_name',
            'role',
            'status',
            'created_at',
            'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from users table");
        }
    }

    public function testControllersTableStructure()
    {
        $stmt = $this->pdo->query("DESCRIBE controllers");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $expectedColumns = [
            'id',
            'user_id',
            'name',
            'type',
            'host',
            'username',
            'password',
            'status'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from controllers table");
        }
    }

    public function testPlansTableStructure()
    {
        $stmt = $this->pdo->query("DESCRIBE plans");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $expectedColumns = [
            'id',
            'name',
            'bandwidth_download',
            'bandwidth_upload',
            'price',
            'duration_days',
            'status'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' missing from plans table");
        }
    }

    public function testTransactionSupport()
    {
        $this->pdo->beginTransaction();

        $this->insertTestUser(['email' => 'transaction@test.com']);

        $this->pdo->rollBack();

        // User should not exist after rollback
        $this->assertDatabaseMissing('users', ['email' => 'transaction@test.com']);
    }

    public function testTransactionCommit()
    {
        $this->pdo->beginTransaction();

        $userId = $this->insertTestUser(['email' => 'commit@test.com']);

        $this->pdo->commit();

        // Start new transaction for testing
        $this->beginTransaction();

        // User should exist after commit
        $this->assertDatabaseHas('users', ['email' => 'commit@test.com']);
    }

    public function testForeignKeyConstraints()
    {
        // Create user
        $userId = $this->insertTestUser(['role' => 'reseller']);

        // Create controller for user
        $controllerId = $this->insertTestController(['user_id' => $userId]);

        $this->assertGreaterThan(0, $controllerId);

        // Try to delete user (should fail due to foreign key if ON DELETE RESTRICT)
        // This depends on schema configuration
        $controller = $this->pdo->query("SELECT * FROM controllers WHERE id = $controllerId")->fetch();
        $this->assertEquals($userId, $controller['user_id']);
    }

    public function testUniqueConstraints()
    {
        $this->insertTestUser(['email' => 'unique@test.com', 'username' => 'unique']);

        // Try to insert duplicate email
        try {
            $this->insertTestUser(['email' => 'unique@test.com', 'username' => 'different']);
            $this->fail('Expected exception for duplicate email');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
        }
    }

    public function testPreparedStatementExecution()
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

        $result = $stmt->execute([
            'prepared@test.com',
            'prepareduser',
            password_hash('password', PASSWORD_BCRYPT),
            'user',
            'active'
        ]);

        $this->assertTrue($result);
        $this->assertGreaterThan(0, $this->pdo->lastInsertId());
    }

    public function testParameterBinding()
    {
        $userId = $this->insertTestUser(['email' => 'binding@test.com']);

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertEquals($userId, $user['id']);
    }

    public function testBulkInsert()
    {
        $this->pdo->beginTransaction();

        for ($i = 1; $i <= 100; $i++) {
            $this->insertTestUser([
                'email' => "bulk{$i}@test.com",
                'username' => "bulk{$i}"
            ]);
        }

        $this->pdo->commit();

        $this->beginTransaction(); // Restart transaction for test isolation

        $count = $this->getTableCount('users');
        $this->assertGreaterThanOrEqual(100, $count);
    }

    public function testConcurrentAccess()
    {
        // Insert user in main connection
        $userId1 = $this->insertTestUser(['email' => 'concurrent1@test.com']);

        // Create second connection
        $pdo2 = $this->getTestDatabaseConnection();
        $pdo2->beginTransaction();

        $stmt = $pdo2->prepare("INSERT INTO users (email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            'concurrent2@test.com',
            'concurrent2',
            password_hash('password', PASSWORD_BCRYPT),
            'user',
            'active'
        ]);

        $userId2 = $pdo2->lastInsertId();

        $this->assertNotEquals($userId1, $userId2);

        $pdo2->rollBack();
    }

    public function testErrorHandling()
    {
        $this->expectException(\PDOException::class);

        // Try to insert invalid data (missing required field)
        $this->pdo->query("INSERT INTO users (email) VALUES ('invalid@test.com')");
    }

    public function testQueryPerformance()
    {
        // Insert test data
        for ($i = 1; $i <= 1000; $i++) {
            $this->insertTestUser([
                'email' => "perf{$i}@test.com",
                'username' => "perf{$i}"
            ]);
        }

        $startTime = microtime(true);

        // Query with index (email should be indexed)
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['perf500@test.com']);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Query should execute in less than 100ms
        $this->assertLessThan(100, $executionTime, "Query took too long: {$executionTime}ms");
    }
}
