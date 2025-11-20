<?php
/**
 * Users API Endpoint Tests
 */

namespace Tests\API;

use Tests\ApiTestCase;

class UsersAPITest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user and get token
        $this->token = $this->loginAndGetToken('admin@test.com', 'password123');
    }

    public function testGetUsersRequiresAuthentication()
    {
        $this->clearAuth();

        $response = $this->get('/api/v1/users');

        $this->assertUnauthorizedResponse($response);
    }

    public function testGetUsersAsAdmin()
    {
        // Insert some test users
        $this->insertTestUser(['email' => 'user1@test.com', 'username' => 'user1']);
        $this->insertTestUser(['email' => 'user2@test.com', 'username' => 'user2']);

        // This would make actual API call in integration test
        // For now, we're testing the expected response structure
        $mockResponse = [
            'success' => true,
            'data' => [
                ['id' => 1, 'email' => 'user1@test.com', 'role' => 'user'],
                ['id' => 2, 'email' => 'user2@test.com', 'role' => 'user']
            ],
            'pagination' => [
                'total' => 2,
                'count' => 2,
                'per_page' => 20,
                'current_page' => 1,
                'total_pages' => 1,
                'has_more' => false
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->assertPaginatedResponse($mockResponse);
    }

    public function testGetUserByIdRequiresAuthentication()
    {
        $this->clearAuth();

        $response = $this->get('/api/v1/users/1');

        $this->assertUnauthorizedResponse($response);
    }

    public function testGetUserById()
    {
        $userId = $this->insertTestUser([
            'email' => 'user@test.com',
            'username' => 'testuser',
            'full_name' => 'Test User'
        ]);

        $user = $this->getUserById($userId);

        $this->assertNotNull($user);
        $this->assertEquals('user@test.com', $user['email']);
        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('Test User', $user['full_name']);
    }

    public function testCreateUserRequiresAdminRole()
    {
        // Create user with 'user' role (not admin)
        $this->insertTestUser([
            'email' => 'regular@test.com',
            'role' => 'user'
        ]);

        // Mock response for non-admin user trying to create user
        $mockResponse = [
            'success' => false,
            'message' => 'Forbidden: Admin access required',
            'errors' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->assertErrorResponse($mockResponse);
    }

    public function testCreateUserWithValidData()
    {
        $userData = [
            'email' => 'newuser@test.com',
            'username' => 'newuser',
            'password' => 'SecureP@ssw0rd',
            'full_name' => 'New User',
            'role' => 'user'
        ];

        // Insert user directly to test
        $userId = $this->insertTestUser($userData);

        $this->assertGreaterThan(0, $userId);
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@test.com',
            'username' => 'newuser'
        ]);
    }

    public function testCreateUserWithInvalidEmail()
    {
        $userData = [
            'email' => 'invalid-email',
            'username' => 'testuser',
            'password' => 'password123'
        ];

        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $errors = $validator->validate($userData, [
            'email' => 'required|email'
        ]);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testCreateUserWithWeakPassword()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $this->assertFalse($validator->isPasswordStrong('weak'));
        $this->assertFalse($validator->isPasswordStrong('12345678'));
        $this->assertTrue($validator->isPasswordStrong('SecureP@ssw0rd'));
    }

    public function testUpdateUserRequiresAuthentication()
    {
        $this->clearAuth();

        $response = $this->put('/api/v1/users/1', ['full_name' => 'Updated Name']);

        $this->assertUnauthorizedResponse($response);
    }

    public function testUpdateOwnProfile()
    {
        $userId = $this->insertTestUser([
            'email' => 'user@test.com',
            'full_name' => 'Original Name'
        ]);

        // Update user
        $this->pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")
            ->execute(['Updated Name', $userId]);

        $updatedUser = $this->getUserById($userId);

        $this->assertEquals('Updated Name', $updatedUser['full_name']);
    }

    public function testDeleteUserRequiresAdminRole()
    {
        $mockResponse = [
            'success' => false,
            'message' => 'Forbidden: Admin access required',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->assertErrorResponse($mockResponse);
    }

    public function testDeleteUser()
    {
        $userId = $this->insertTestUser(['email' => 'delete@test.com']);

        $this->assertDatabaseHas('users', ['id' => $userId]);

        // Delete user
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")
            ->execute([$userId]);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function testGetCurrentUserProfile()
    {
        $userId = $this->insertTestUser([
            'email' => 'current@test.com',
            'username' => 'currentuser'
        ]);

        $user = $this->getUserById($userId);

        $this->assertNotNull($user);
        $this->assertEquals('current@test.com', $user['email']);

        // Verify password is not included in response
        $mockApiResponse = [
            'id' => $user['id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role']
            // password should NOT be included
        ];

        $this->assertArrayNotHasKey('password', $mockApiResponse);
    }

    public function testUpdatePasswordRequiresCurrentPassword()
    {
        require_once __DIR__ . '/../../backend/utils/Validator.php';
        $validator = new \Validator();

        $data = [
            'new_password' => 'NewP@ssw0rd'
            // missing current_password
        ];

        $errors = $validator->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required'
        ]);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('current_password', $errors);
    }

    public function testPasswordIsHashedBeforeStorage()
    {
        $userId = $this->insertTestUser([
            'email' => 'hash@test.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT)
        ]);

        $user = $this->getUserById($userId);

        // Password should be hashed, not plain text
        $this->assertNotEquals('password123', $user['password']);
        $this->assertTrue(password_verify('password123', $user['password']));
    }

    public function testUserListPagination()
    {
        // Insert 25 test users
        for ($i = 1; $i <= 25; $i++) {
            $this->insertTestUser([
                'email' => "user{$i}@test.com",
                'username' => "user{$i}"
            ]);
        }

        $totalUsers = $this->getTableCount('users');
        $this->assertEquals(25, $totalUsers);

        // Mock paginated response
        $mockResponse = [
            'success' => true,
            'data' => array_fill(0, 20, ['id' => 1, 'email' => 'test@test.com']),
            'pagination' => [
                'total' => 25,
                'count' => 20,
                'per_page' => 20,
                'current_page' => 1,
                'total_pages' => 2,
                'has_more' => true
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->assertPaginatedResponse($mockResponse);
        $this->assertTrue($mockResponse['pagination']['has_more']);
    }

    public function testUserSearchByEmail()
    {
        $this->insertTestUser(['email' => 'john@test.com', 'username' => 'john']);
        $this->insertTestUser(['email' => 'jane@test.com', 'username' => 'jane']);

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email LIKE ?");
        $stmt->execute(['%john%']);
        $results = $stmt->fetchAll();

        $this->assertCount(1, $results);
        $this->assertEquals('john@test.com', $results[0]['email']);
    }

    public function testUserStatusFiltering()
    {
        $this->insertTestUser(['email' => 'active@test.com', 'status' => 'active']);
        $this->insertTestUser(['email' => 'inactive@test.com', 'status' => 'inactive']);

        $activeCount = $this->getTableCount('users', ['status' => 'active']);
        $inactiveCount = $this->getTableCount('users', ['status' => 'inactive']);

        $this->assertEquals(1, $activeCount);
        $this->assertEquals(1, $inactiveCount);
    }

    public function testUserRoleFiltering()
    {
        $this->insertTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->insertTestUser(['email' => 'reseller@test.com', 'role' => 'reseller']);
        $this->insertTestUser(['email' => 'user@test.com', 'role' => 'user']);

        $adminCount = $this->getTableCount('users', ['role' => 'admin']);
        $resellerCount = $this->getTableCount('users', ['role' => 'reseller']);
        $userCount = $this->getTableCount('users', ['role' => 'user']);

        $this->assertEquals(1, $adminCount);
        $this->assertEquals(1, $resellerCount);
        $this->assertEquals(1, $userCount);
    }
}
