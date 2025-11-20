# WiFight ISP System - Testing Commands Quick Reference

## Essential Commands

### Setup & Installation

```bash
# Install all testing dependencies
composer require --dev phpunit/phpunit:^10.5 mockery/mockery:^1.6 fakerphp/faker:^1.23

# Create test database
mysql -u root -e "CREATE DATABASE wifight_isp_test"

# Import schema to test database
mysql -u root wifight_isp_test < database/schema.sql

# Verify PHPUnit installation
vendor/bin/phpunit --version
```

## Running Tests

### All Tests
```bash
# Run complete test suite
vendor/bin/phpunit

# Run with colored output
vendor/bin/phpunit --colors=always

# Run with human-readable output
vendor/bin/phpunit --testdox
```

### By Test Suite
```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Integration tests only
vendor/bin/phpunit --testsuite=Integration

# API tests only
vendor/bin/phpunit --testsuite=API

# Security tests only
vendor/bin/phpunit tests/Security
```

### Specific Files
```bash
# Single test file
vendor/bin/phpunit tests/Unit/ValidatorTest.php

# Multiple specific files
vendor/bin/phpunit tests/Unit/ValidatorTest.php tests/Unit/JWTTest.php

# All files in directory
vendor/bin/phpunit tests/Unit/
```

### Specific Tests
```bash
# Single test method
vendor/bin/phpunit --filter testEmailValidation

# Multiple methods matching pattern
vendor/bin/phpunit --filter Validation

# Specific class and method
vendor/bin/phpunit --filter ValidatorTest::testEmailValidation

# Regular expression filter
vendor/bin/phpunit --filter '/Email.*Validation/'
```

## Coverage Reports

### HTML Coverage
```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage

# Generate for specific suite
vendor/bin/phpunit --testsuite=Unit --coverage-html coverage/unit

# Open report (after generation)
# Windows:
start coverage/index.html
# Mac:
open coverage/index.html
# Linux:
xdg-open coverage/index.html
```

### Text Coverage
```bash
# Coverage summary in terminal
vendor/bin/phpunit --coverage-text

# Coverage with test output
vendor/bin/phpunit --coverage-text --testdox
```

### Other Coverage Formats
```bash
# Clover XML (for CI/CD)
vendor/bin/phpunit --coverage-clover coverage.xml

# PHPUnit XML format
vendor/bin/phpunit --coverage-xml coverage/xml

# Coverage data cache
vendor/bin/phpunit --coverage-cache .phpunit.cache/coverage
```

## Output Control

### Verbosity
```bash
# Normal output
vendor/bin/phpunit

# Verbose output
vendor/bin/phpunit --verbose

# Debug output
vendor/bin/phpunit --debug

# Testdox format (readable test names)
vendor/bin/phpunit --testdox
```

### Progress Indicators
```bash
# Default dots
vendor/bin/phpunit

# No progress
vendor/bin/phpunit --no-progress

# Compact progress
vendor/bin/phpunit --compact
```

## Test Execution Control

### Stop on Failure
```bash
# Stop on first error
vendor/bin/phpunit --stop-on-error

# Stop on first failure
vendor/bin/phpunit --stop-on-failure

# Stop on first risky test
vendor/bin/phpunit --stop-on-risky

# Stop on first skipped test
vendor/bin/phpunit --stop-on-skipped
```

### Repeat Tests
```bash
# Repeat tests 3 times
vendor/bin/phpunit --repeat 3

# Run until failure (useful for flaky tests)
while vendor/bin/phpunit --filter testFlaky; do :; done
```

### Random Order
```bash
# Randomize test order
vendor/bin/phpunit --order-by=random

# Use specific random seed
vendor/bin/phpunit --order-by=random --random-order-seed=12345

# Reverse order
vendor/bin/phpunit --order-by=reverse
```

## Database Testing

### Reset Test Database
```bash
# Drop and recreate
mysql -u root -e "DROP DATABASE IF EXISTS wifight_isp_test; CREATE DATABASE wifight_isp_test"

# Import fresh schema
mysql -u root wifight_isp_test < database/schema.sql

# Import schema with seed data (if exists)
mysql -u root wifight_isp_test < database/schema.sql
mysql -u root wifight_isp_test < database/seeds/test_data.sql
```

### Verify Database
```bash
# Check if test database exists
mysql -u root -e "SHOW DATABASES LIKE 'wifight_isp_test'"

# List tables in test database
mysql -u root wifight_isp_test -e "SHOW TABLES"

# Check test data
mysql -u root wifight_isp_test -e "SELECT COUNT(*) FROM users"
```

## Testing Agent Scripts

### Main Workflow Script
```bash
# Complete testing workflow
.claude/agents/testing/run-tasks.sh all

# Individual tasks
.claude/agents/testing/run-tasks.sh setup        # Install dependencies
.claude/agents/testing/run-tasks.sh database     # Create test database
.claude/agents/testing/run-tasks.sh unit         # Run unit tests
.claude/agents/testing/run-tasks.sh integration  # Run integration tests
.claude/agents/testing/run-tasks.sh api          # Run API tests
.claude/agents/testing/run-tasks.sh coverage     # Generate coverage
.claude/agents/testing/run-tasks.sh security     # Run security tests
.claude/agents/testing/run-tasks.sh validate     # Validate test suite
```

### Windows (Git Bash)
```bash
# Run with Git Bash on Windows
bash .claude/agents/testing/run-tasks.sh all
```

## Performance & Optimization

### Parallel Execution
```bash
# Run tests in parallel (requires paratest)
composer require --dev brianium/paratest
vendor/bin/paratest --processes=4

# Run specific suite in parallel
vendor/bin/paratest --testsuite=Unit --processes=4
```

### Caching
```bash
# Use result cache (speeds up subsequent runs)
vendor/bin/phpunit --cache-result

# Clear cache
rm -rf .phpunit.cache

# Specify cache directory
vendor/bin/phpunit --cache-result-file=.phpunit.cache/result.cache
```

## Debugging Tests

### Debug Single Test
```bash
# Run with verbose output
vendor/bin/phpunit --testdox --verbose --filter testSpecificTest

# Run with debugging enabled
vendor/bin/phpunit --debug --filter testSpecificTest

# Stop on error for debugging
vendor/bin/phpunit --stop-on-error --filter testSpecificTest
```

### Print Output During Tests
```php
// In test file
public function testSomething() {
    echo "Debug: Value is " . $value . "\n";
    $this->assertTrue(true);
}
```

```bash
# Run without output buffering to see prints
vendor/bin/phpunit --debug --testdox
```

## Configuration

### Use Custom Config
```bash
# Use alternative phpunit.xml
vendor/bin/phpunit -c phpunit.ci.xml

# Override specific settings
vendor/bin/phpunit --bootstrap tests/custom-bootstrap.php
```

### Environment Variables
```bash
# Set test environment
APP_ENV=testing vendor/bin/phpunit

# Use different database
DB_DATABASE=wifight_isp_test_2 vendor/bin/phpunit

# Multiple environment variables
APP_ENV=testing DB_DATABASE=wifight_isp_test vendor/bin/phpunit
```

## Continuous Integration

### Local CI Simulation
```bash
# Simulate CI environment
APP_ENV=testing \
DB_HOST=localhost \
DB_DATABASE=wifight_isp_test \
DB_USERNAME=root \
DB_PASSWORD= \
vendor/bin/phpunit --coverage-clover coverage.xml
```

### Pre-commit Hook
```bash
# Create pre-commit hook
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash
vendor/bin/phpunit --testsuite=Unit
if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi
EOF

chmod +x .git/hooks/pre-commit
```

## Common Workflows

### Quick Test During Development
```bash
# Test what you're working on
vendor/bin/phpunit --filter testMethodName --testdox
```

### Pre-Commit Checks
```bash
# Run unit tests (fast)
vendor/bin/phpunit --testsuite=Unit

# Run all tests if time permits
vendor/bin/phpunit --stop-on-failure
```

### Before Pull Request
```bash
# Full test suite with coverage
vendor/bin/phpunit --coverage-html coverage --testdox

# Check coverage report
open coverage/index.html
```

### CI/CD Pipeline
```bash
# Complete CI workflow
mysql -u root -e "CREATE DATABASE wifight_isp_test"
mysql -u root wifight_isp_test < database/schema.sql
composer install --no-interaction --prefer-dist
vendor/bin/phpunit --coverage-clover coverage.xml --log-junit junit.xml
```

## Troubleshooting

### Clear All Caches
```bash
rm -rf .phpunit.cache
rm -rf coverage
composer dump-autoload
```

### Reinstall Dependencies
```bash
rm -rf vendor
composer install
```

### Check PHP Configuration
```bash
php -i | grep -i xdebug
php -m | grep -i pdo
php --version
```

### Verify Test Files
```bash
# Find all test files
find tests -name "*Test.php"

# Count test files
find tests -name "*Test.php" | wc -l

# Check for syntax errors
find tests -name "*.php" -exec php -l {} \;
```

## Performance Benchmarks

### Measure Test Execution Time
```bash
# Show execution time for each test
vendor/bin/phpunit --testdox --verbose

# Show slowest tests
vendor/bin/phpunit --testdox | grep -E '\([0-9]+\.[0-9]+ seconds\)'
```

### Profile Test Suite
```bash
# Generate test execution times
vendor/bin/phpunit --log-junit junit.xml

# Analyze junit.xml for slow tests
```

## Quick Reference Card

| Task | Command |
|------|---------|
| Run all tests | `vendor/bin/phpunit` |
| Run unit tests | `vendor/bin/phpunit --testsuite=Unit` |
| Run single test | `vendor/bin/phpunit --filter testName` |
| HTML coverage | `vendor/bin/phpunit --coverage-html coverage` |
| Stop on failure | `vendor/bin/phpunit --stop-on-failure` |
| Verbose output | `vendor/bin/phpunit --testdox --verbose` |
| Reset database | `mysql -u root wifight_isp_test < database/schema.sql` |
| Testing agent | `.claude/agents/testing/run-tasks.sh all` |

## Additional Resources

- PHPUnit Manual: https://phpunit.de/documentation.html
- PHPUnit CLI Options: https://phpunit.de/manual/current/en/textui.html
- Mockery Docs: http://docs.mockery.io/
- Faker Docs: https://fakerphp.github.io/

---

**Save this file for quick reference during development!**
