#!/bin/bash

# Controller Agent Task Runner
echo "==================================="
echo "WiFight Controller Agent"
echo "==================================="

CONFIG_FILE=".claude/agents/controllers/config.json"

# Task 1: Create Controller Abstraction Layer
echo "Task 1: Creating Controller Abstraction Layer..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-001" \
  --context=".claude/plans/Phase2-Controllers.md" \
  --output="backend/services/controllers/" \
  --prompt="Create the Controller Abstraction Layer for WiFight: 1) ControllerInterface.php - Define interface with methods: connect(), authenticateUser(), disconnectUser(), getActiveSessions(), getUserSession(), updateBandwidth(), getControllerStatus(), createRadiusProfile(), testConnection(). 2) ControllerFactory.php - Factory pattern to instantiate controllers by type (mikrotik, omada, ruijie, meraki). Include error handling and validation."

# Task 2: MikroTik Integration
echo "Task 2: Creating MikroTik integration..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-002" \
  --context="backend/services/controllers/ControllerInterface.php" \
  --output="backend/services/controllers/MikrotikController.php" \
  --prompt="Create complete MikroTik RouterOS integration implementing ControllerInterface. Use pear/net_routeros library. Implement: 1) Connect to RouterOS API 2) Create hotspot users 3) Manage IP bindings 4) Create bandwidth queues 5) Get active sessions 6) Disconnect users 7) Update bandwidth 8) RADIUS profile creation. Include comprehensive error handling and logging. Reference: https://help.mikrotik.com/docs/display/ROS/API"

# Task 3: Omada Integration
echo "Task 3: Creating Omada integration..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-003" \
  --context="backend/services/controllers/ControllerInterface.php" \
  --output="backend/services/controllers/OmadaController.php" \
  --prompt="Create complete TP-Link Omada SDN Controller integration implementing ControllerInterface. Use Omada API v2. Implement: 1) Token-based authentication 2) Guest user management 3) MAC-based authentication 4) Bandwidth limiting 5) Get active clients 6) Site management 7) External portal integration. Handle API pagination and rate limiting. Reference: https://support.omadanetworks.com/en/document/13080/"

# Task 4: Ruijie Integration
echo "Task 4: Creating Ruijie integration..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-004" \
  --context="backend/services/controllers/ControllerInterface.php" \
  --output="backend/services/controllers/RuijieController.php" \
  --prompt="Create complete Ruijie Networks Cloud Controller integration implementing ControllerInterface. Use OAuth2 authentication. Implement: 1) OAuth2 token flow 2) Guest user creation 3) Bandwidth policy management 4) Get online users 5) Session monitoring 6) AP management integration. Handle API pagination and Ruijie-specific error codes."

# Task 5: Meraki Integration
echo "Task 5: Creating Cisco Meraki integration..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-005" \
  --context="backend/services/controllers/ControllerInterface.php" \
  --output="backend/services/controllers/MerakiController.php" \
  --prompt="Create complete Cisco Meraki Dashboard integration implementing ControllerInterface. Use API key authentication. Implement: 1) Splash page authorization 2) Group policy assignment for bandwidth 3) Client tracking 4) Get network clients 5) SSID management 6) Handle rate limiting (5 req/sec). Reference: https://developer.cisco.com/meraki/"

# Task 6: Controller testing utilities
echo "Task 6: Creating controller testing utilities..."
claude-code task \
  --agent="controller-agent" \
  --config="$CONFIG_FILE" \
  --task="ctrl-006" \
  --context="backend/services/controllers/" \
  --output="backend/services/controllers/ControllerTester.php" \
  --prompt="Create ControllerTester utility class that can: 1) Test connection to any controller type 2) Validate API credentials 3) Run integration tests 4) Generate test reports 5) Simulate user authentication flow 6) Test bandwidth limiting 7) Measure API response times. Include comprehensive error reporting."

echo "==================================="
echo "Controller Agent tasks completed!"
echo "==================================="