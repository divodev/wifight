# WiFight ISP System - Developer Getting Started Guide

## Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/wifight-isp-system.git
cd wifight-isp-system
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env`:
```
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_DATABASE=wifight_isp
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=your-secret-key-min-32-characters
```

### 4. Set Up Database

```bash
mysql -u root -p -e "CREATE DATABASE wifight_isp"
mysql -u root -p wifight_isp < database/schema.sql
```

### 5. Start Development Server

```bash
php -S localhost:8000
```

Visit: http://localhost:8000/api/v1/health

## Project Structure

```
wifight-isp-system/
├── backend/
│   ├── api/              # API endpoints
│   │   ├── v1/          # API version 1
│   │   └── index.php    # API router
│   ├── config/          # Configuration files
│   ├── middleware/      # Middleware (auth, CORS)
│   ├── services/        # Business logic
│   │   └── controllers/ # Network controller integrations
│   └── utils/           # Utility classes (JWT, Validator, Logger)
├── database/
│   ├── schema.sql       # Database schema
│   └── migrations/      # Database migrations
├── frontend/            # Admin dashboard
├── tests/               # Test suite
│   ├── Unit/           # Unit tests
│   ├── Integration/    # Integration tests
│   └── Feature/        # Feature tests
├── storage/
│   ├── logs/           # Application logs
│   └── backups/        # Database backups
├── scripts/            # Deployment scripts
├── .claude/            # Claude Code agents
└── docs/               # Documentation
```

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

### 2. Make Changes

Follow PSR-12 coding standards:
```bash
vendor/bin/phpcs --standard=PSR12 backend/
```

### 3. Write Tests

```bash
vendor/bin/phpunit tests/Unit/YourTest.php
```

### 4. Run All Tests

```bash
vendor/bin/phpunit
```

### 5. Commit Changes

```bash
git add .
git commit -m "feat: add new feature"
```

### 6. Push and Create PR

```bash
git push origin feature/your-feature-name
```

## Common Development Tasks

### Adding a New API Endpoint

1. Create handler in `backend/api/v1/`
2. Add route in `backend/api/index.php`
3. Write tests in `tests/Integration/`
4. Document in `docs/api/ENDPOINTS.md`

Example:
```php
// backend/api/v1/example.php
<?php
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/JWT.php';

$jwt = new JWT();
$token = $jwt->getTokenFromHeader();
$user = $jwt->validate($token);

// Your endpoint logic here

Response::success(['message' => 'Success']);
```

### Adding a Controller Integration

1. Create class in `backend/services/controllers/`
2. Implement `ControllerInterface`
3. Register in `ControllerFactory`
4. Write tests

Example:
```php
class NewController implements ControllerInterface {
    public function connect(array $credentials) {
        // Implementation
    }

    public function authenticateUser(string $mac, string $username, array $plan) {
        // Implementation
    }
}
```

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific suite
vendor/bin/phpunit --testsuite=Unit

# With coverage
vendor/bin/phpunit --coverage-html storage/coverage
```

### Database Migrations

```bash
# Create migration
php scripts/create-migration.php migration_name

# Run migrations
php scripts/migrate-database.php
```

### Debugging

Enable debug mode in `.env`:
```
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/application.log
```

## Code Style Guidelines

### PHP Standards

- Follow PSR-12 coding standard
- Use PHPDoc comments
- Type hint function parameters and returns
- Use meaningful variable names

Good:
```php
/**
 * Authenticate user with credentials
 *
 * @param string $email User email
 * @param string $password User password
 * @return array User data with token
 */
public function authenticate(string $email, string $password): array
{
    // Implementation
}
```

Bad:
```php
function auth($e, $p) {
    // Implementation
}
```

### Error Handling

Always use try-catch blocks:
```php
try {
    $result = $someOperation();
    Response::success($result);
} catch (Exception $e) {
    Logger::error('Operation failed', ['error' => $e->getMessage()]);
    Response::error('Operation failed', 500);
}
```

## Testing Guidelines

### Unit Tests

Test individual classes in isolation:
```php
class ValidatorTest extends TestCase
{
    public function testEmailValidation()
    {
        $result = Validator::validate(['email' => 'test@example.com'], [
            'email' => 'required|email'
        ]);
        $this->assertTrue($result);
    }
}
```

### Integration Tests

Test API endpoints:
```php
class AuthTest extends TestCase
{
    public function testUserCanLogin()
    {
        $response = $this->post('/api/v1/auth/login', [
            'email' => 'admin@wifight.local',
            'password' => 'admin123'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }
}
```

## Useful Commands

```bash
# Run development server
php -S localhost:8000

# Run tests
vendor/bin/phpunit

# Code style check
vendor/bin/phpcs --standard=PSR12 backend/

# Fix code style
vendor/bin/phpcbf --standard=PSR12 backend/

# Static analysis
vendor/bin/phpstan analyse backend/ --level=5

# Clear logs
rm -rf storage/logs/*.log

# Database backup
bash scripts/backup-database.sh
```

## Resources

- [CLAUDE.md](../../CLAUDE.md) - Project guidelines for Claude Code
- [API Documentation](../api/ENDPOINTS.md)
- [Testing Guide](TESTING.md)
- [Database Schema](DATABASE.md)

## Getting Help

- Check existing issues
- Read the FAQ
- Ask in discussions
- Create a new issue

Happy coding!
