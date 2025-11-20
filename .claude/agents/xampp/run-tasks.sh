#!/bin/bash

echo "═════════════════════════════════════"
echo "  WiFight XAMPP Deployment Agent"
echo "═════════════════════════════════════"

CONFIG_FILE=".claude/agents/xampp/agent-config.json"

# Detect operating system
detect_os() {
    if [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "win32" ]]; then
        echo "windows"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macos"
    else
        echo "linux"
    fi
}

OS=$(detect_os)
echo "Detected OS: $OS"
echo ""

# Task 1: Installation Guide
echo "Task 1: Creating XAMPP installation guide..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-001" \
  --output="docs/deployment/" \
  --prompt="Create comprehensive XAMPP installation guide for WiFight ISP system covering Windows, Linux, and macOS with step-by-step instructions, screenshots references, and troubleshooting."

# Task 2: Virtual Host Configuration
echo "Task 2: Creating Apache virtual host configuration..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-002" \
  --output="xampp/apache/conf/extra/" \
  --prompt="Create Apache virtual host configuration for WiFight with proper DocumentRoot, ServerName, and directory permissions. Include SSL configuration for HTTPS."

# Task 3: PHP Configuration
echo "Task 3: Creating PHP configuration..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-003" \
  --output="xampp/php/" \
  --prompt="Create optimized PHP configuration for WiFight including required extensions, memory limits, upload sizes, and Redis support."

# Task 4: Deployment Script
echo "Task 4: Creating deployment script..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-004" \
  --output="scripts/" \
  --prompt="Create automated deployment script that copies WiFight files to XAMPP htdocs, sets permissions, and configures environment."

# Task 5: Database Import Script
echo "Task 5: Creating database import script..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-005" \
  --output="scripts/" \
  --prompt="Create script to import WiFight database schema and seed data into XAMPP MySQL using command line and phpMyAdmin methods."

# Task 6: Redis Integration Guide
echo "Task 6: Creating Redis integration guide..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-006" \
  --output="docs/deployment/" \
  --prompt="Create comprehensive guide for installing and integrating Redis with XAMPP on Windows, Linux, and macOS including PHP Redis extension setup."

# Task 7: Testing Checklist
echo "Task 7: Creating testing checklist..."
claude-code task \
  --agent="xampp-agent" \
  --config="$CONFIG_FILE" \
  --task="xampp-007" \
  --output="docs/deployment/" \
  --prompt="Create comprehensive testing checklist to verify XAMPP WiFight deployment including Apache, MySQL, Redis, and application functionality tests."

echo ""
echo "═════════════════════════════════════"
echo "  XAMPP Agent tasks completed!"
echo "═════════════════════════════════════"