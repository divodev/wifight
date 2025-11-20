#!/bin/bash

echo "==================================="
echo "WiFight RADIUS Agent"
echo "==================================="

CONFIG_FILE=".claude/agents/radius/agent-config.json"

# Task 1: FreeRADIUS Configuration
echo "Task 1: Configuring FreeRADIUS server..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-001" \
  --output="radius/" \
  --prompt="Create complete FreeRADIUS 3.x configuration for WiFight ISP:

1. radiusd.conf - Main configuration:
   - Set authentication and accounting ports
   - Configure thread pool for high concurrency
   - Enable necessary modules
   - Set up logging
   - Performance tuning for 10,000+ users

2. proxy.conf - Proxy configuration (if needed)

3. Dictionary - Custom VSAs (Vendor Specific Attributes):
   - Bandwidth-Max-Up
   - Bandwidth-Max-Down
   - Session-Expiry
   - Data-Limit

Include installation script for Ubuntu/Debian and comprehensive documentation."

# Task 2: SQL Module Configuration
echo "Task 2: Configuring SQL module..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-002" \
  --context="database/schema/complete-schema.sql" \
  --output="radius/mods-enabled/" \
  --prompt="Configure FreeRADIUS SQL module for WiFight database integration:

1. sql configuration file:
   - MySQL connection settings
   - Connection pool configuration
   - Query files location
   - Accounting table mapping
   - Safe characters for SQL
   - TLS/SSL connection (optional)

2. SQLcounter module for bandwidth limiting:
   - Daily bandwidth counter
   - Monthly bandwidth counter
   - Session time counter

3. SQL-IP-Pool module for dynamic IP assignment

Map to WiFight tables: users, sessions, radius_accounting, subscriptions."

# Task 3: RADIUS Queries
echo "Task 3: Creating RADIUS SQL queries..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-003" \
  --context="database/schema/complete-schema.sql,radius/mods-enabled/sql" \
  --output="radius/sql/mysql/" \
  --prompt="Create SQL queries for FreeRADIUS WiFight integration:

1. queries.conf:
   - authorize_check_query: Verify user credentials
   - authorize_reply_query: Get reply attributes (bandwidth, session timeout)
   - accounting_start_query: Record session start
   - accounting_update_query: Update session data (interim)
   - accounting_stop_query: Record session end
   - post-auth_query: Log authentication attempts

2. Query structure:
   - Check user subscription status (active/expired)
   - Retrieve plan details (bandwidth limits)
   - Check MAC address binding
   - Verify controller assignment
   - Calculate remaining data quota

3. Reply attributes based on plan:
   - Session-Timeout from subscription end_date
   - Filter-Id or Rate-Limit for bandwidth
   - Framed-IP-Address for IP assignment

Include stored procedures for complex logic."

# Task 4: NAS Clients Configuration
echo "Task 4: Configuring NAS clients..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-004" \
  --output="radius/clients.conf" \
  --prompt="Create NAS (Network Access Server) client configuration for all WiFight controllers:

Configure RADIUS clients for:

1. MikroTik Routers:
   - Client identifier
   - IP address/network
   - Shared secret (strong encryption)
   - NAS type: mikrotik
   - Enable CoA

2. TP-Link Omada Controllers:
   - Multiple site support
   - Dynamic client registration (optional)
   - NAS type: other

3. Ruijie Controllers:
   - Cloud controller IP ranges
   - NAS type: other

4. Cisco Meraki:
   - Meraki cloud IPs
   - NAS type: cisco

Include:
- Strong shared secrets (32+ characters)
- Rate limiting per client
- Connection limits
- Documentation on adding new controllers
- Security best practices"

# Task 5: RADIUS Accounting
echo "Task 5: Setting up RADIUS accounting..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-005" \
  --context="radius/mods-enabled/sql" \
  --output="radius/sites-enabled/default" \
  --prompt="Configure comprehensive RADIUS accounting for WiFight:

1. Accounting section in default virtual server:
   - Receive accounting packets
   - Process Start packets (new session)
   - Process Interim-Update packets (usage updates)
   - Process Stop packets (session end)
   - Log to SQL database
   - Update session statistics

2. Accounting attributes to capture:
   - Acct-Session-Id
   - Acct-Input-Octets (download)
   - Acct-Output-Octets (upload)
   - Acct-Session-Time
   - Acct-Terminate-Cause
   - NAS-IP-Address
   - Called-Station-Id (AP MAC)
   - Calling-Station-Id (User MAC)

3. Real-time session tracking:
   - Update active sessions table
   - Calculate bandwidth usage
   - Check quota limits
   - Trigger alerts on limits

4. Accounting redundancy:
   - Detail file backup
   - SQL transaction safety
   - Packet buffering on DB failure

Include accounting policy configuration and log rotation."

# Task 6: Dynamic Authorization (CoA)
echo "Task 6: Implementing Change of Authorization..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-006" \
  --output="radius/sites-enabled/coa" \
  --prompt="Configure RADIUS Change of Authorization (CoA) for real-time session control:

1. CoA virtual server (port 3799):
   - Receive CoA requests
   - Receive Disconnect-Request (DM)
   - Authentication of CoA requests
   - Session identification methods

2. CoA use cases:
   - Update bandwidth limits in real-time
   - Disconnect user sessions
   - Update session timeout
   - Apply new policies
   - Block/unblock users

3. Integration with WiFight API:
   - Endpoint to trigger CoA
   - Automatic CoA on plan changes
   - Automatic disconnect on subscription expiry
   - Bandwidth adjustment endpoint

4. NAS-specific CoA support:
   - MikroTik: Full CoA support
   - Omada: Disconnect support
   - Others: Test and document

Include CoA testing tools and documentation."

# Task 7: RADIUS Management API
echo "Task 7: Creating RADIUS management API..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-007" \
  --context="backend/api/" \
  --output="backend/api/radius/" \
  --prompt="Create REST API for RADIUS server management:

1. GET /api/v1/radius/status:
   - RADIUS server health check
   - Active sessions count
   - Authentication success rate
   - Accounting packet rate

2. POST /api/v1/radius/test-auth:
   - Test user authentication
   - Verify credentials
   - Check reply attributes

3. POST /api/v1/radius/disconnect:
   - Send Disconnect-Request
   - Terminate user session
   - Support for MAC or username

4. POST /api/v1/radius/coa:
   - Send CoA request
   - Update session attributes
   - Change bandwidth limits

5. GET /api/v1/radius/sessions:
   - List active RADIUS sessions
   - Session details with attributes
   - Real-time bandwidth usage

6. GET /api/v1/radius/logs:
   - Recent authentication attempts
   - Failed authentication logs
   - Accounting events

7. POST /api/v1/radius/reload:
   - Reload RADIUS configuration
   - Clear cache
   - Restart modules

Include admin authentication and rate limiting."

# Task 8: RADIUS Monitoring
echo "Task 8: Setting up RADIUS monitoring..."
claude-code task \
  --agent="radius-agent" \
  --config="$CONFIG_FILE" \
  --task="radius-008" \
  --output="radius/monitoring/" \
  --prompt="Create comprehensive RADIUS monitoring system:

1. monitoring-scripts.sh:
   - Check RADIUS process health
   - Monitor authentication rate
   - Track accounting packet rate
   - Monitor SQL connection pool
   - Alert on high error rates

2. log-analyzer.py:
   - Parse FreeRADIUS logs
   - Identify authentication failures
   - Analyze rejection reasons
   - Generate statistics

3. performance-metrics.sh:
   - Average response time
   - Packets per second
   - Active sessions
   - SQL query performance

4. Prometheus exporter:
   - Export RADIUS metrics
   - Authentication success/failure rates
   - Session statistics
   - Bandwidth metrics

5. Dashboard integration:
   - Real-time RADIUS statistics
   - Historical trends
   - Alert visualization

Include Grafana dashboard JSON and alerting rules."

echo "==================================="
echo "RADIUS Agent tasks completed!"
echo "==================================="