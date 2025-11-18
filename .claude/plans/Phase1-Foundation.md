# Phase 1: Foundation Setup

## Objectives
1. Design and implement complete database schema
2. Set up authentication system with JWT
3. Create basic API structure
4. Configure development environment

## Database Schema Tasks
### 1.1 Core Tables
- [ ] Create `users` table with roles
- [ ] Create `controllers` table for multi-vendor support
- [ ] Create `plans` table with bandwidth & pricing
- [ ] Create `sessions` table for active connections
- [ ] Create `subscriptions` table for recurring billing
- [ ] Create `payments` table with transaction tracking
- [ ] Create `vouchers` table for prepaid codes
- [ ] Create `radius_accounting` table
- [ ] Create `audit_logs` table
- [ ] Create `notifications` table
- [ ] Create `system_settings` table

### 1.2 Indexes & Constraints
- [ ] Add primary keys to all tables
- [ ] Create foreign key relationships
- [ ] Add indexes for common queries
- [ ] Set up database triggers for audit logging

### 1.3 Database Functions
- [ ] Create stored procedures for session management
- [ ] Create views for reporting
- [ ] Set up automatic cleanup jobs

## Authentication System Tasks
### 2.1 JWT Implementation
- [ ] Create JWT utility class
- [ ] Implement token generation
- [ ] Implement token validation
- [ ] Set up token refresh mechanism
- [ ] Add token blacklist for logout

### 2.2 User Management
- [ ] Create user registration endpoint
- [ ] Create login endpoint
- [ ] Create password reset flow
- [ ] Implement role-based access control (RBAC)
- [ ] Add 2FA support

### 2.3 Security Features
- [ ] Implement password hashing (bcrypt)
- [ ] Add rate limiting to auth endpoints
- [ ] Set up CORS configuration
- [ ] Implement CSRF protection
- [ ] Add input validation & sanitization

## API Structure Tasks
### 3.1 Core API Files
- [ ] Create Response utility class
- [ ] Create Logger utility class
- [ ] Create Validator utility class
- [ ] Set up error handling middleware
- [ ] Create database connection class

### 3.2 API Endpoints Foundation
- [ ] Set up routing structure
- [ ] Create base controller class
- [ ] Implement API versioning
- [ ] Add request/response logging
- [ ] Set up API documentation structure

## Development Environment
### 4.1 Local Setup
- [ ] Create .env.example file
- [ ] Set up XAMPP/Docker configuration
- [ ] Create database seeding scripts
- [ ] Set up local HTTPS (optional)

### 4.2 Development Tools
- [ ] Configure PHP CodeSniffer
- [ ] Set up PHPUnit for testing
- [ ] Configure Xdebug for debugging
- [ ] Create development documentation

## Success Criteria
- ✓ All database tables created and indexed
- ✓ Users can register and login
- ✓ JWT tokens are generated and validated
- ✓ Basic API structure is functional
- ✓ Development environment is fully configured

## Estimated Time: 2 Weeks

## Dependencies
- MySQL 8.0+
- PHP 8.0+
- Composer packages installed
- Development server running

## Next Phase
Once foundation is complete, proceed to Phase 2: Controller Integration
EOF

# Create plans for all other phases
# Phase 2: Controller Integration
cat > .claude/plans/Phase2-Controllers.md << 'EOF'
# Phase 2: Multi-Vendor Controller Integration

## Objectives
1. Implement Controller Abstraction Layer (CAL)
2. Integrate MikroTik RouterOS API
3. Integrate TP-Link Omada Controller
4. Integrate Ruijie Networks Controller
5. Integrate Cisco Meraki Dashboard

## Week 3: Controller Abstraction Layer & MikroTik

### 3.1 Controller Abstraction Layer
- [ ] Create ControllerInterface with standardized methods
- [ ] Create ControllerFactory for instantiation
- [ ] Implement controller configuration storage
- [ ] Create controller status monitoring
- [ ] Add controller test connection utility

### 3.2 MikroTik RouterOS Integration
- [ ] Install pear/net_routeros library
- [ ] Create MikrotikController class
- [ ] Implement connect() method
- [ ] Implement authenticateUser() method
- [ ] Implement disconnectUser() method
- [ ] Implement getActiveSessions() method
- [ ] Implement getUserSession() method
- [ ] Implement updateBandwidth() method
- [ ] Implement getControllerStatus() method
- [ ] Implement createRadiusProfile() method
- [ ] Create bandwidth queue management
- [ ] Implement IP pool management
- [ ] Add hotspot user management
- [ ] Create MikroTik-specific error handling
- [ ] Write unit tests for MikroTik integration

### 3.3 MikroTik API Endpoints
- [ ] POST /api/v1/controllers/mikrotik/test
- [ ] POST /api/v1/controllers/mikrotik/authenticate
- [ ] POST /api/v1/controllers/mikrotik/disconnect
- [ ] GET /api/v1/controllers/mikrotik/sessions
- [ ] PUT /api/v1/controllers/mikrotik/bandwidth

## Week 4: Omada Controller Integration

### 4.1 Omada SDN Controller
- [ ] Create OmadaController class
- [ ] Implement Omada API authentication (token-based)
- [ ] Implement connect() with session management
- [ ] Implement guest user creation
- [ ] Implement MAC-based authentication
- [ ] Implement bandwidth limiting
- [ ] Implement session termination
- [ ] Implement site management
- [ ] Get active clients from controller
- [ ] Create external portal integration
- [ ] Handle Omada API rate limiting
- [ ] Implement error handling for Omada
- [ ] Write unit tests for Omada integration

### 4.2 Omada Configuration
- [ ] Document external portal setup
- [ ] Create portal parameter parser
- [ ] Implement redirect URL handling
- [ ] Set up RADIUS profile (optional)
- [ ] Create Omada controller health check

### 4.3 Omada API Endpoints
- [ ] POST /api/v1/controllers/omada/test
- [ ] POST /api/v1/controllers/omada/authenticate
- [ ] POST /api/v1/controllers/omada/disconnect
- [ ] GET /api/v1/controllers/omada/clients
- [ ] GET /api/v1/controllers/omada/sites

## Week 5: Ruijie Networks Integration

### 5.1 Ruijie Cloud Controller
- [ ] Create RuijieController class
- [ ] Implement OAuth2 authentication
- [ ] Implement guest user management
- [ ] Implement bandwidth policy creation
- [ ] Implement session monitoring
- [ ] Get online users from API
- [ ] Implement AP management integration
- [ ] Handle Ruijie API pagination
- [ ] Implement Ruijie-specific error codes
- [ ] Write unit tests for Ruijie integration

### 5.2 Ruijie API Endpoints
- [ ] POST /api/v1/controllers/ruijie/test
- [ ] POST /api/v1/controllers/ruijie/authenticate
- [ ] POST /api/v1/controllers/ruijie/disconnect
- [ ] GET /api/v1/controllers/ruijie/online-users
- [ ] GET /api/v1/controllers/ruijie/aps

## Week 6: Cisco Meraki Integration

### 6.1 Meraki Dashboard API
- [ ] Create MerakiController class
- [ ] Implement API key authentication
- [ ] Implement splash page authorization
- [ ] Implement group policy assignment
- [ ] Implement client tracking
- [ ] Get network clients
- [ ] Implement SSID management
- [ ] Handle Meraki API rate limiting (5 req/sec)
- [ ] Implement webhook support for events
- [ ] Write unit tests for Meraki integration

### 6.2 Meraki Configuration
- [ ] Document splash page setup
- [ ] Create custom splash page template
- [ ] Implement billing integration
- [ ] Set up group policies for plans
- [ ] Create Meraki health monitoring

### 6.3 Meraki API Endpoints
- [ ] POST /api/v1/controllers/meraki/test
- [ ] POST /api/v1/controllers/meraki/authorize
- [ ] DELETE /api/v1/controllers/meraki/deauthorize
- [ ] GET /api/v1/controllers/meraki/clients
- [ ] POST /api/v1/controllers/meraki/policy

## Integration Testing
### 6.1 Cross-Controller Tests
- [ ] Test controller switching for multi-vendor deployments
- [ ] Test session migration between controllers
- [ ] Test bandwidth updates across all types
- [ ] Test simultaneous connections to multiple controllers
- [ ] Load testing with multiple active sessions

### 6.2 Error Handling
- [ ] Test offline controller handling
- [ ] Test API timeout scenarios
- [ ] Test malformed API responses
- [ ] Test authentication failures
- [ ] Test network connectivity issues

## Documentation
- [ ] Create API documentation for each controller
- [ ] Document configuration requirements
- [ ] Create troubleshooting guide
- [ ] Document controller-specific limitations
- [ ] Create integration examples

## Success Criteria
- ✓ All 4 controller types fully integrated
- ✓ Users can connect via any controller type
- ✓ Bandwidth limiting works on all controllers
- ✓ Sessions can be monitored across all types
- ✓ All unit tests passing
- ✓ Integration tests passing

## Estimated Time: 4 Weeks

## Dependencies
- Phase 1 completed (Database & API foundation)
- Access to test controllers for each vendor
- API credentials for each controller type

## Next Phase
Once controller integration is complete, proceed to Phase 3: Core Features


# Continue with remaining phases...