#!/bin/bash

# API Agent Task Runner
echo "==================================="
echo "WiFight API Agent"
echo "==================================="

CONFIG_FILE=".claude/agents/api/config.json"

# Task 1: Create utility classes
echo "Task 1: Creating core utility classes..."
claude-code task \
  --agent="api-agent" \
  --config="$CONFIG_FILE" \
  --task="api-001" \
  --context=".claude/plans/Phase1-Foundation.md" \
  --output="backend/utils/" \
  --prompt="Create the following utility classes for WiFight: 1) JWT.php - Handle token generation, validation, refresh with blacklist support. Use firebase/php-jwt library. 2) Response.php - Standardized JSON responses with proper HTTP status codes. 3) Logger.php - File-based logging with levels (info, warning, error). Use monolog/monolog. 4) Validator.php - Input validation and sanitization."

# Task 2: Authentication endpoints
echo "Task 2: Creating authentication endpoints..."
claude-code task \
  --agent="api-agent" \
  --config="$CONFIG_FILE" \
  --task="api-002" \
  --context="backend/utils/" \
  --output="backend/api/auth/" \
  --prompt="Create authentication API endpoints: 1) login.php - Validate credentials, return JWT 2) register.php - Create new user with validation 3) logout.php - Add token to blacklist 4) refresh.php - Refresh expired token 5) forgot-password.php - Password reset flow. Include proper error handling, input validation, and rate limiting."

# Task 3: User management endpoints
echo "Task 3: Creating user management endpoints..."
claude-code task \
  --agent="api-agent" \
  --config="$CONFIG_FILE" \
  --task="api-003" \
  --context="backend/api/auth/" \
  --output="backend/api/users/" \
  --prompt="Create user management endpoints: 1) GET /users - List users with pagination 2) GET /users/{id} - Get single user 3) POST /users - Create user (admin only) 4) PUT /users/{id} - Update user 5) DELETE /users/{id} - Delete user (admin only) 6) GET /users/{id}/sessions - Get user's sessions. Include RBAC checks."

# Task 4: Controller endpoints
echo "Task 4: Creating controller management endpoints..."
claude-code task \
  --agent="api-agent" \
  --config="$CONFIG_FILE" \
  --task="api-004" \
  --output="backend/api/controllers/" \
  --prompt="Create controller management endpoints: 1) GET /controllers - List all controllers 2) GET /controllers/{id} - Get controller details + status 3) POST /controllers - Add new controller with connection test 4) PUT /controllers/{id} - Update controller 5) DELETE /controllers/{id} - Remove controller 6) POST /controllers/{id}/test - Test connection 7) GET /controllers/{id}/sessions - Get active sessions."

# Task 5: Plan endpoints
echo "Task 5: Creating plan management endpoints..."
claude-code task \
  --agent="api-agent" \
  --config="$CONFIG_FILE" \
  --task="api-005" \
  --output="backend/api/plans/" \
  --prompt="Create plan management endpoints: 1) GET /plans - List all plans 2) GET /plans/{id} - Get plan details 3) POST /plans - Create plan 4) PUT /plans/{id} - Update plan 5) DELETE /plans/{id} - Delete plan 6) POST /plans/{id}/subscribe - Subscribe user to plan."

echo "==================================="
echo "API Agent tasks completed!"
echo "==================================="