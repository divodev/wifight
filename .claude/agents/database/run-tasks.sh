#!/bin/bash

# Database Agent Task Runner
echo "==================================="
echo "WiFight Database Agent"
echo "==================================="

# Load configuration
CONFIG_FILE=".claude/agents/database/agent-config.json"

# Task 1: Create complete schema
echo "Task 1: Creating database schema..."
claude-code task \
  --agent="database-agent" \
  --config="$CONFIG_FILE" \
  --task="db-001" \
  --context=".claude/plans/Phase1-Foundation.md" \
  --output="database/schema/complete-schema.sql" \
  --prompt="Create the complete MySQL database schema for the WiFight ISP system according to the development plan. Include all tables: users, controllers, plans, sessions, subscriptions, payments, vouchers, radius_accounting, audit_logs, notifications, and system_settings. Add proper indexes, foreign keys, and constraints. Include comprehensive comments."

# Task 2: Create migrations
echo "Task 2: Creating migration scripts..."
claude-code task \
  --agent="database-agent" \
  --config="$CONFIG_FILE" \
  --task="db-002" \
  --context="database/schema/complete-schema.sql" \
  --output="database/migrations/" \
  --prompt="Create individual migration files for each table in the WiFight database. Each migration should be reversible (up/down methods). Number them sequentially (001_create_users_table.sql, 002_create_controllers_table.sql, etc.)."

# Task 3: Create seed data
echo "Task 3: Creating seed data..."
claude-code task \
  --agent="database-agent" \
  --config="$CONFIG_FILE" \
  --task="db-003" \
  --context="database/schema/complete-schema.sql" \
  --output="database/seeds/" \
  --prompt="Create seed data for initial system setup including: 1) Admin user (email: admin@wifight.com, password: admin123 hashed with bcrypt) 2) Sample internet plans (1Mbps, 5Mbps, 10Mbps) 3) System settings 4) Sample voucher batch."

# Task 4: Create indexes
echo "Task 4: Creating additional indexes..."
claude-code task \
  --agent="database-agent" \
  --config="$CONFIG_FILE" \
  --task="db-004" \
  --context="database/schema/complete-schema.sql" \
  --output="database/schema/indexes.sql" \
  --prompt="Create additional performance indexes for the WiFight database. Focus on: 1) Composite indexes for common queries 2) Covering indexes for frequently accessed columns 3) Indexes for foreign keys 4) Full-text indexes where appropriate."

# Task 5: Create stored procedures
echo "Task 5: Creating stored procedures..."
claude-code task \
  --agent="database-agent" \
  --config="$CONFIG_FILE" \
  --task="db-005" \
  --context="database/schema/complete-schema.sql" \
  --output="database/schema/procedures.sql" \
  --prompt="Create stored procedures for WiFight: 1) sp_create_session 2) sp_end_session 3) sp_cleanup_expired_sessions 4) sp_generate_voucher_batch 5) sp_calculate_user_balance 6) sp_get_revenue_report."

echo "==================================="
echo "Database Agent tasks completed!"
echo "==================================="