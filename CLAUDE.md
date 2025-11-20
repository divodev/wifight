# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WiFight is a multi-vendor ISP billing and management system supporting MikroTik, TP-Link Omada, Ruijie Networks, and Cisco Meraki controllers. The system is built with PHP backend, provides a RESTful API, and follows a phased development approach.

## Development Commands

### Initial Setup
```bash
# First-time initialization (checks requirements, creates .env, sets up database)
php init.php

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Import database schema
mysql -u root -p wifight_isp < database/schema.sql
```

### Development Server
```bash
# Start PHP development server
php -S localhost:8000

# Or use XAMPP Apache server (default: http://localhost/)
```

### Testing & Validation
```bash
# Validate PHP syntax for all backend files
php -l backend/config/database.php
php -l backend/utils/*.php
php -l backend/api/**/*.php

# Validate composer configuration
composer validate --no-check-all --no-check-publish

# Run tests (when implemented in Phase 5)
vendor/bin/phpunit
vendor/bin/phpunit tests/controllers/  # Controller-specific tests
```

### Database Operations
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE wifight_isp"

# Import schema
mysql -u root -p wifight_isp < database/schema.sql

# Export database backup
mysqldump -u root -p wifight_isp > storage/backups/backup-$(date +%Y%m%d).sql
```

### Maintenance
```bash
# Clear application logs
rm -rf storage/logs/*.log

# Check system health
curl http://localhost/api/v1/health
```

## Architecture

### Multi-Tier Structure

**Backend Layer (PHP):**
- `backend/api/` - RESTful API endpoints (routes via `backend/api/index.php`)
- `backend/config/` - Database and system configuration
- `backend/utils/` - Core utility classes (Response, Logger, JWT, Validator)
- `backend/services/controllers/` - Network controller integrations
- `backend/models/` - Data models (future implementation)

**API Routing:**
The system uses a central router (`backend/api/index.php`) that:
1. Parses URLs in format: `/api/v1/{resource}/{action}`
2. Routes to handlers in `backend/api/v1/{resource}.php`
3. Provides CORS support and centralized error handling
4. All API responses use standardized JSON format via `Response` utility

**Database Layer:**
- Schema in `database/schema.sql` with 11 core tables
- PDO-based connection handler with transaction support
- Foreign key constraints enforce referential integrity
- Includes triggers, views, and stored procedures for automation

### Controller Abstraction Layer

The system uses a Factory pattern for multi-vendor controller support:

```php
// All controllers implement ControllerInterface
interface ControllerInterface {
    public function connect(array $credentials);
    public function authenticateUser(string $mac, string $username, array $plan);
    public function disconnectUser(string $mac);
    public function getActiveSessions();
    // ... standard methods
}

// Factory creates controller instances
$controller = ControllerFactory::create('mikrotik', $config);
$controller = ControllerFactory::createFromDatabase($dbRecord);
```

**Supported Controllers:**
- `mikrotik` - MikroTik RouterOS (requires: host, username, password, port)
- `omada` - TP-Link Omada SDN (requires: host, username, password, site_id)
- `ruijie` - Ruijie Networks (requires: host, api_key, api_secret)
- `meraki` - Cisco Meraki (requires: api_key, network_id)

### Authentication Flow

1. User credentials validated via `POST /api/v1/auth/login`
2. JWT token pair generated (access + refresh tokens)
3. Access token passed in `Authorization: Bearer {token}` header
4. `JWT` utility class validates token and extracts user data
5. Endpoints check user role and permissions before executing

### Core Utility Classes

**Response** (`backend/utils/Response.php`):
- Standardizes all API responses
- Methods: `success()`, `error()`, `paginated()`, `validationError()`
- Auto-applies security headers

**JWT** (`backend/utils/JWT.php`):
- Generates/validates JWT tokens using firebase/php-jwt
- Supports token refresh mechanism
- Helper methods: `getTokenFromHeader()`, `createTokenPair()`

**Validator** (`backend/utils/Validator.php`):
- Rule-based input validation
- Rules: `required`, `email`, `min`, `max`, `numeric`, `mac`, `ip`, `url`, `in`, `regex`
- Password strength validation
- Input sanitization methods

**Logger** (`backend/utils/Logger.php`):
- Daily log files in `storage/logs/`
- Log levels: DEBUG, INFO, WARNING, ERROR, CRITICAL
- Special methods: `logRequest()`, `logQuery()`

**Database** (`backend/config/database.php`):
- PDO connection manager
- Transaction support
- Migration execution
- Connection testing

## Development Phases & Workflow

The project follows a 6-phase development plan (`.claude/workflows/full-development.yaml`):

### Phase 1: Foundation ✅ COMPLETE
- Database schema (11 tables)
- Authentication system (JWT)
- Core API endpoints (health, auth, users)
- Utility classes

### Phase 2: Controller Integration (CURRENT)
**Tasks:**
- ctrl-002: Implement MikroTik RouterOS integration
- ctrl-003: Implement Omada Controller integration
- ctrl-004: Implement Ruijie Networks integration
- ctrl-005: Implement Cisco Meraki integration

**When implementing controllers:**
1. Extend placeholder classes in `backend/services/controllers/`
2. Implement all `ControllerInterface` methods
3. Use vendor-specific API libraries (see composer.json)
4. Test with real hardware/controllers when possible
5. Update `ControllerFactory::getSupportedTypes()` with configuration requirements

### Phase 3: Core Features
- Session management endpoints
- Billing system
- Payment gateway integration
- Plan management
- Subscription handling

### Phase 4: User Interfaces
- Admin dashboard (frontend/)
- User self-service portal (portal/)
- Captive portal pages

### Phase 5: Testing & QA
- Unit tests (PHPUnit)
- Integration tests
- Load testing

### Phase 6: Deployment
- Docker containerization
- CI/CD pipeline
- Production deployment scripts

## Specialized Agents

The project uses 13 specialized development agents (`.claude/agents/agents.json`):

**Priority 1-2 (Foundation):**
- `database-agent` - Schema design, migrations, optimization
- `api-agent` - RESTful endpoint development
- `security-agent` - Authentication, encryption, security audits
- `radius-agent` - RADIUS server integration

**Priority 3 (Integration):**
- `controller-agent` - Network controller integrations
- `billing-agent` - Payment systems, subscriptions
- `performance-agent` - Query optimization, caching
- `integration-agent` - Third-party API integrations

**Priority 4+ (UI/Testing/Deployment):**
- `frontend-agent` - Dashboard and portal development
- `analytics-agent` - Business intelligence, reporting
- `testing-agent` - Test suite creation
- `devops-agent` - Infrastructure, CI/CD
- `documentation-agent` - Technical documentation

**Agent Dependencies:**
- `api-agent` depends on `database-agent`
- `controller-agent` depends on `api-agent` + `radius-agent`
- `frontend-agent` depends on `api-agent` + `analytics-agent`
- `testing-agent` depends on all development agents

## API Endpoints

### Current Endpoints (Phase 1)

**Authentication:**
- `POST /api/v1/auth/login` - User login (returns JWT token pair)
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/refresh` - Refresh access token
- `POST /api/v1/auth/logout` - Token invalidation

**Users:**
- `GET /api/v1/users` - List users (paginated, admin/reseller only)
- `GET /api/v1/users/:id` - Get user details
- `POST /api/v1/users` - Create user (admin only)
- `PUT /api/v1/users/:id` - Update user
- `DELETE /api/v1/users/:id` - Delete user (admin only)
- `GET /api/v1/users/me` - Get current user profile
- `PUT /api/v1/users/me` - Update own profile

**System:**
- `GET /api/v1/health` - Health check (database, storage, system info)

### Future Endpoints (Phases 2-3)
- `/api/v1/controllers/*` - Controller CRUD operations
- `/api/v1/plans/*` - Internet plan management
- `/api/v1/sessions/*` - Active session monitoring
- `/api/v1/payments/*` - Payment processing
- `/api/v1/vouchers/*` - Voucher generation
- `/api/v1/subscriptions/*` - Subscription management

## Database Schema

**Core Tables:**
- `users` - User accounts (roles: admin, reseller, user)
- `controllers` - Network controller configurations
- `plans` - Internet service plans (bandwidth, duration, pricing)
- `sessions` - Active user sessions
- `subscriptions` - Recurring billing
- `payments` - Transaction records
- `vouchers` - Prepaid access codes
- `radius_accounting` - RADIUS accounting data
- `audit_logs` - Security audit trail
- `notifications` - User notifications
- `system_settings` - Configuration key-value store

**Default Credentials:**
- Email: `admin@wifight.local`
- Password: `admin123`
- **Must be changed in production**

## Important Configuration

### Environment Variables (.env)
Required variables:
- `DB_*` - Database connection (host, database, username, password)
- `JWT_SECRET` - Secret key for JWT signing (min 32 characters)
- `JWT_EXPIRATION` - Access token lifetime (default: 3600 seconds)
- `APP_DEBUG` - Debug mode (true/false)
- `LOG_LEVEL` - Logging level (debug, info, warning, error, critical)

Optional variables:
- `RADIUS_SERVER` - RADIUS server IP
- `REDIS_HOST` - Redis for caching
- Payment gateway credentials (Stripe, PayPal)
- Email/SMS service credentials

### Security Considerations

**When implementing features:**
1. All passwords use `password_hash()` with PASSWORD_BCRYPT
2. All database queries use prepared statements (PDO)
3. Input validation via `Validator` class before processing
4. User input sanitized via `Validator::sanitize()`
5. Role-based access control enforced in all endpoints
6. All API responses include security headers (X-Frame-Options, XSS-Protection)

**Role Hierarchy:**
- `admin` - Full system access
- `reseller` - Can manage their own users/controllers
- `user` - Limited to own profile and sessions

## Development Notes

### Adding New API Endpoints

1. Create handler in `backend/api/v1/{resource}.php`
2. Implement route parsing (use global `$parts` array)
3. Validate JWT token at start: `$jwt->validate($token)`
4. Use `Response` class for all outputs
5. Log actions with `Logger` class
6. Validate input with `Validator` class

### Adding Controller Support

1. Create class in `backend/services/controllers/`
2. Implement `ControllerInterface`
3. Register in `ControllerFactory::create()` switch
4. Update `ControllerFactory::getSupportedTypes()`
5. Add required composer packages
6. Test with physical hardware

### Code Style

- Use PHPDoc comments for all classes/methods
- Follow PSR-12 coding standards
- Include error handling (try-catch blocks)
- Log errors before throwing exceptions
- Return consistent data structures

## Workflow Execution

To run the full development workflow:
```bash
# Execute specific phase
claude workflow run .claude/workflows/full-development.yaml --phase="Phase 2: Controller Integration"

# The workflow validates each phase before proceeding
```

Each phase has validation criteria that must pass before advancing.

---

## Best Practices & Generic Development Advice

### Code Quality Standards

**Write Clean, Readable Code:**
- Use descriptive variable and function names
- Keep functions small and focused (single responsibility principle)
- Avoid deep nesting (max 3-4 levels)
- Use early returns to reduce complexity
- Extract magic numbers and strings into named constants
- Add meaningful comments for complex logic, not obvious code

**Error Handling:**
- Always use try-catch blocks for operations that may fail
- Provide helpful, user-friendly error messages
- Log errors with context (user ID, request details, timestamps)
- Never expose sensitive information in error messages
- Return appropriate HTTP status codes (400, 401, 403, 404, 500, etc.)
- Handle edge cases and validate all inputs

**Code Organization:**
- Group related functionality together
- Keep files focused on a single purpose
- Use consistent file and folder naming conventions
- Avoid circular dependencies
- Keep classes and files under 300-400 lines when possible

### Security Best Practices

**Authentication & Authorization:**
- Always validate user permissions before sensitive operations
- Never trust client-side validation alone
- Implement rate limiting on authentication endpoints
- Use secure session management
- Implement proper logout functionality
- Store sensitive data (passwords, API keys) encrypted
- Never log sensitive information (passwords, tokens, credit cards)

**Input Validation:**
- Validate ALL user inputs (never trust user data)
- Use whitelist validation (allow known good) over blacklist (block known bad)
- Sanitize inputs before displaying to prevent XSS
- Use parameterized queries for ALL database operations
- Validate file uploads (type, size, content)
- Implement CSRF protection for state-changing operations

**API Security:**
- Use HTTPS in production (never HTTP for sensitive data)
- Implement proper CORS policies
- Use strong JWT secrets (minimum 32 characters, random)
- Rotate secrets regularly
- Implement API rate limiting
- Version your APIs (/api/v1/, /api/v2/)
- Never include sensitive information in URLs (use request body)

**Data Protection:**
- Encrypt sensitive data at rest
- Use prepared statements to prevent SQL injection
- Implement proper access controls (RBAC)
- Sanitize output to prevent XSS attacks
- Validate and sanitize file paths to prevent directory traversal
- Keep dependencies updated to patch security vulnerabilities

### Testing Practices

**Unit Testing:**
- Write tests for all new utilities and business logic
- Aim for at least 70-80% code coverage
- Test edge cases and error conditions
- Use meaningful test names that describe what's being tested
- Keep tests independent and isolated
- Mock external dependencies (database, APIs, file system)

**Integration Testing:**
- Test API endpoints with actual requests
- Test database operations with test database
- Test authentication and authorization flows
- Test error scenarios (invalid inputs, missing data)
- Verify response formats and status codes

**Testing Guidelines:**
- Write tests before fixing bugs (reproduce first)
- Test both success and failure paths
- Don't test framework code, test your code
- Keep tests fast (mock slow operations)
- Run tests before committing code

### Database Best Practices

**Query Optimization:**
- Always use indexes on frequently queried columns
- Avoid SELECT * (specify needed columns)
- Use LIMIT for pagination
- Optimize JOIN operations
- Use EXPLAIN to analyze slow queries
- Cache frequently accessed data

**Data Integrity:**
- Use foreign key constraints to maintain referential integrity
- Use transactions for operations that must complete together
- Validate data before inserting into database
- Use appropriate data types (don't store numbers as strings)
- Set proper default values
- Use NOT NULL constraints where appropriate

**Migration Best Practices:**
- Make migrations reversible when possible
- Test migrations on copy of production data
- Back up database before running migrations
- Keep migrations small and focused
- Never modify existing migrations that have been deployed

### API Development Guidelines

**RESTful Design:**
- Use appropriate HTTP methods (GET, POST, PUT, DELETE)
- Use plural nouns for resources (/users, not /user)
- Use HTTP status codes correctly
- Version your APIs from the start
- Implement pagination for list endpoints
- Return consistent response structures

**Request/Response Handling:**
- Validate request payloads before processing
- Return meaningful error messages with error codes
- Include request IDs for debugging
- Implement proper HTTP caching headers
- Use compression for large responses
- Document all endpoints (OpenAPI/Swagger)

**Performance:**
- Implement response caching where appropriate
- Use database connection pooling
- Limit response payload size
- Implement efficient pagination
- Use asynchronous processing for long operations
- Monitor API response times

### Documentation Standards

**Code Documentation:**
- Document all public APIs with PHPDoc
- Include parameter types, return types, and descriptions
- Document complex algorithms and business logic
- Keep comments up to date with code changes
- Use TODO/FIXME comments for temporary solutions
- Document assumptions and constraints

**API Documentation:**
- Document all endpoints (path, method, parameters)
- Provide request/response examples
- Document error responses and codes
- Include authentication requirements
- Document rate limits and quotas
- Keep API docs in sync with code

**Project Documentation:**
- Maintain accurate README with setup instructions
- Document environment variables and configuration
- Create troubleshooting guides for common issues
- Document deployment procedures
- Keep architecture diagrams updated
- Document breaking changes

### Version Control Best Practices

**Commit Guidelines:**
- Write clear, descriptive commit messages
- Use conventional commit format (feat:, fix:, docs:, etc.)
- Make atomic commits (one logical change per commit)
- Don't commit sensitive information (passwords, API keys)
- Don't commit generated files (vendor/, node_modules/)
- Review changes before committing

**Branching Strategy:**
- Use feature branches for new development
- Keep main/master branch stable and deployable
- Use descriptive branch names (feature/user-auth, fix/login-bug)
- Delete branches after merging
- Rebase feature branches to keep history clean
- Use pull requests for code review

**Code Review:**
- Review all code before merging to main
- Check for security vulnerabilities
- Verify tests are included and passing
- Check code style and consistency
- Look for performance issues
- Ensure documentation is updated

### Performance Optimization

**General Optimization:**
- Profile before optimizing (measure, don't guess)
- Optimize the bottlenecks, not everything
- Use caching strategically (Redis, Memcached)
- Minimize database queries (use eager loading)
- Use background jobs for heavy operations
- Implement pagination for large datasets

**Frontend Performance:**
- Minimize HTTP requests
- Compress assets (CSS, JS, images)
- Use CDN for static assets
- Implement lazy loading for images
- Minimize and bundle JavaScript/CSS
- Use browser caching

**Backend Performance:**
- Use database query optimization
- Implement connection pooling
- Cache expensive computations
- Use asynchronous processing
- Optimize file I/O operations
- Monitor memory usage

### Debugging & Troubleshooting

**Debugging Strategies:**
- Read error messages carefully
- Check logs first (application, web server, database)
- Reproduce the issue consistently
- Isolate the problem (binary search approach)
- Use debugger and breakpoints
- Add strategic logging for complex flows

**Common Issues:**
- Check file permissions (storage/, cache/)
- Verify environment variables are loaded
- Check database connection credentials
- Verify API endpoints and routes
- Check for CORS issues
- Verify JWT token expiration

**Logging Best Practices:**
- Log at appropriate levels (debug, info, warning, error)
- Include context (user ID, request ID, timestamp)
- Don't log sensitive information
- Rotate logs to prevent disk fill
- Monitor error rates
- Use structured logging (JSON format)

### Deployment Guidelines

**Pre-Deployment Checklist:**
- Run all tests and ensure they pass
- Check for security vulnerabilities
- Review environment configuration
- Test on staging environment first
- Back up database before deployment
- Plan rollback strategy

**Production Best Practices:**
- Never debug in production (disable debug mode)
- Use environment-specific configurations
- Implement monitoring and alerting
- Set up error tracking (Sentry, Bugsnag)
- Use process managers (systemd, supervisor)
- Implement graceful shutdown

**Monitoring:**
- Monitor application performance (response times)
- Track error rates and types
- Monitor resource usage (CPU, memory, disk)
- Set up health check endpoints
- Monitor database performance
- Track business metrics

### Communication & Collaboration

**When Working on Tasks:**
- Clarify requirements before starting
- Ask questions early rather than making assumptions
- Provide progress updates on long-running tasks
- Document decisions and rationale
- Share knowledge with team members
- Report blockers immediately

**Code Collaboration:**
- Write self-documenting code
- Keep pull requests focused and small
- Respond to code review feedback promptly
- Explain complex code in comments
- Help teammates with code reviews
- Share learnings and best practices

### Continuous Improvement

**Learning & Growth:**
- Stay updated on security vulnerabilities
- Learn from production incidents
- Refactor code regularly
- Keep dependencies updated
- Review and improve code quality metrics
- Document lessons learned

**Code Maintenance:**
- Refactor when you see duplication
- Fix technical debt incrementally
- Update documentation as code evolves
- Remove unused code and dependencies
- Keep testing coverage high
- Monitor and improve performance

### Common Pitfalls to Avoid

**Security Pitfalls:**
- Never store passwords in plain text
- Don't trust user input
- Don't use weak encryption algorithms
- Never commit secrets to version control
- Don't expose debug information in production
- Don't use default credentials

**Code Quality Pitfalls:**
- Don't repeat yourself (DRY principle)
- Avoid premature optimization
- Don't ignore error handling
- Avoid tight coupling
- Don't skip code reviews
- Avoid over-engineering solutions

**Database Pitfalls:**
- Don't use SELECT * in production code
- Avoid N+1 query problems
- Don't forget database indexes
- Avoid storing large blobs in database
- Don't modify production data directly
- Avoid running migrations without backups

**API Pitfalls:**
- Don't break backward compatibility without versioning
- Avoid inconsistent response formats
- Don't ignore rate limiting
- Avoid returning too much data
- Don't forget to validate inputs
- Avoid exposing internal errors to clients

### Tools & Resources

**Recommended Tools:**
- **Linting:** PHP_CodeSniffer, PHPStan
- **Testing:** PHPUnit, Mockery
- **Debugging:** Xdebug, var_dump(), error_log()
- **API Testing:** Postman, Insomnia, curl
- **Database:** MySQL Workbench, phpMyAdmin
- **Version Control:** Git, GitHub/GitLab
- **Monitoring:** New Relic, Datadog, ELK Stack

**Learning Resources:**
- PHP documentation (php.net)
- PSR standards (php-fig.org)
- OWASP security guidelines
- RESTful API design principles
- Database optimization guides
- Framework-specific documentation
