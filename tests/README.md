# WiFight ISP System - Testing Suite

This directory contains the complete test suite for the WiFight ISP System.

## Test Structure

```
tests/
├── bootstrap.php           # PHPUnit bootstrap file
├── Unit/                   # Unit tests for individual components
│   ├── ValidatorTest.php   # Input validation tests
│   ├── JWTTest.php         # JWT token tests
│   └── EncryptionTest.php  # Encryption utility tests
├── Integration/            # Integration tests
│   ├── AuthenticationTest.php  # Auth system tests
│   └── CacheTest.php       # Cache manager tests
└── API/                    # API endpoint tests
    └── AuthAPITest.php     # Authentication API tests
```

## Setup

### 1. Create Test Database

```bash
mysql -u root -p -e "CREATE DATABASE wifight_isp_test"
mysql -u root -p wifight_isp_test < database/schema.sql
```

### 2. Install PHPUnit

```bash
composer require --dev phpunit/phpunit
```

### 3. Configure phpunit.xml

The `phpunit.xml` file is already configured with:
- Test suites (Unit, Integration, API)
- Code coverage settings
- Test environment variables

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
vendor/bin/phpunit --testsuite Unit

# Integration tests only
vendor/bin/phpunit --testsuite Integration

# API tests only
vendor/bin/phpunit --testsuite API
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/ValidatorTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter testEmailValidation tests/Unit/ValidatorTest.php
```

### Run with Code Coverage

```bash
vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Writing Tests

### Unit Test Example

```php
<?php

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    private $myClass;

    protected function setUp(): void
    {
        $this->myClass = new MyClass();
    }

    public function testSomething()
    {
        $result = $this->myClass->doSomething();

        $this->assertTrue($result);
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example

```php
<?php

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = getTestDatabase();
    }

    public function testDatabaseOperation()
    {
        // Test database operations
        $stmt = $this->db->query('SELECT COUNT(*) FROM users');
        $count = $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count);
    }
}
```

### API Test Example

```php
<?php

use PHPUnit\Framework\TestCase;

class APITest extends TestCase
{
    public function testEndpoint()
    {
        $response = $this->makeRequest('GET', '/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
    }
}
```

## Test Coverage Goals

- **Unit Tests**: 80%+ coverage for utilities and services
- **Integration Tests**: Cover all major workflows
- **API Tests**: Test all public endpoints

## Current Test Coverage

### Unit Tests ✅
- Validator (email, required, min, max, numeric, MAC, IP, URL, in, regex)
- JWT (generation, validation, expiration, token pairs)
- Encryption (encrypt/decrypt, token generation, hashing)

### Integration Tests ✅
- Authentication (login, token validation, role checking, brute force)
- Cache (set/get, expiration, remember pattern, increment/decrement)

### API Tests ✅
- Auth API (login, logout, protected endpoints)

## Best Practices

1. **Isolation**: Each test should be independent
2. **Cleanup**: Use `setUp()` and `tearDown()` to manage test state
3. **Clear Names**: Test names should describe what they test
4. **Assertions**: Use specific assertions (`assertEquals` vs `assertTrue`)
5. **Coverage**: Aim for both success and failure scenarios
6. **Speed**: Keep tests fast (use mocks for slow operations)

## Continuous Integration

Add to your CI pipeline:

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: vendor/bin/phpunit
```

## Troubleshooting

### Database Connection Errors

Make sure the test database exists and credentials are correct in `tests/bootstrap.php`.

### Token/Session Errors

Check that JWT_SECRET is set in test environment.

### Permission Errors

Ensure `storage/` directory is writable:

```bash
chmod -R 775 storage/
```

## Next Steps

1. Add more API endpoint tests
2. Add controller integration tests
3. Add payment gateway tests (with mocked providers)
4. Add WebSocket/real-time tests
5. Add load/performance tests
6. Implement mutation testing
