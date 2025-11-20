#!/bin/bash

# Enhanced WiFight Development Orchestrator - 13 Agents
# Version: 2.0
# Description: Manages and executes all 13 specialized development agents

# Color codes for better visual feedback
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Log file for orchestrator
LOG_FILE="storage/logs/orchestrator-$(date +%Y%m%d).log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
    log_message "SUCCESS: $1"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
    log_message "ERROR: $1"
}

print_info() {
    echo -e "${CYAN}ℹ $1${NC}"
    log_message "INFO: $1"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
    log_message "WARNING: $1"
}

# Function to check if agent directory exists
check_agent_exists() {
    local agent_path="$1"
    if [ ! -f "$agent_path" ]; then
        print_error "Agent script not found: $agent_path"
        return 1
    fi
    return 0
}

# Create log directory if it doesn't exist
mkdir -p storage/logs

# Start logging
log_message "=== Orchestrator Started ==="

# Enhanced header
clear
echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  WiFight ISP System - Enhanced 13-Agent Orchestrator    ║${NC}"
echo -e "${CYAN}║                    Version 2.0                           ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Enhanced menu
echo -e "${BLUE}Select development phase:${NC}"
echo ""
echo "FOUNDATION (Phase 1)"
echo "  1) Database + Security (Parallel)"
echo "  2) API Layer"
echo ""
echo "CORE INTEGRATION (Phase 2)"
echo "  3) RADIUS Server Setup"
echo "  4) Multi-Vendor Controllers"
echo ""
echo "FEATURES (Phase 3)"
echo "  5) Billing & Payments"
echo "  6) Performance Optimization"
echo "  7) Integrations & Webhooks"
echo ""
echo "USER INTERFACES (Phase 4)"
echo "  8) Analytics & Reporting"
echo "  9) Frontend Development"
echo ""
echo "QUALITY & DEPLOYMENT (Phase 5-6)"
echo "  10) Testing & QA"
echo "  11) DevOps & Deployment"
echo ""
echo "AUTOMATION"
echo "  12) Run All Phases (Full Development)"
echo "  13) Run Specific Agent"
echo "  0) Exit"
echo ""
read -p "Enter choice [0-13]: " choice

case $choice in
    0)
        echo "Exiting orchestrator. Goodbye!"
        exit 0
        ;;
    1)
        echo "Running Foundation: Database + Security..."
        bash .claude/agents/database/run-tasks.sh &
        PID1=$!
        bash .claude/agents/security/run-tasks.sh &
        PID2=$!
        wait $PID1 $PID2
        echo "✓ Foundation phase completed"
        ;;
    2)
        echo "Building API Layer..."
        bash .claude/agents/api/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ API Layer completed"
        else
            echo "✗ API Layer failed"
            exit 1
        fi
        ;;
    3)
        echo "Setting up RADIUS Server..."
        bash .claude/agents/radius/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ RADIUS Server completed"
        else
            echo "✗ RADIUS Server failed"
            exit 1
        fi
        ;;
    4)
        echo "Implementing Multi-Vendor Controllers..."
        bash .claude/agents/controller/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Controller integration completed"
        else
            echo "✗ Controller integration failed"
            exit 1
        fi
        ;;
    5)
        echo "Implementing Billing & Payments..."
        bash .claude/agents/billing/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Billing & Payments completed"
        else
            echo "✗ Billing & Payments failed"
            exit 1
        fi
        ;;
    6)
        echo "Optimizing Performance..."
        bash .claude/agents/performance/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Performance optimization completed"
        else
            echo "✗ Performance optimization failed"
            exit 1
        fi
        ;;
    7)
        echo "Setting up Integrations..."
        bash .claude/agents/integration/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Integrations completed"
        else
            echo "✗ Integrations failed"
            exit 1
        fi
        ;;
    8)
        echo "Building Analytics System..."
        bash .claude/agents/analytics/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Analytics system completed"
        else
            echo "✗ Analytics system failed"
            exit 1
        fi
        ;;
    9)
        echo "Developing Frontend..."
        bash .claude/agents/frontend/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Frontend development completed"
        else
            echo "✗ Frontend development failed"
            exit 1
        fi
        ;;
    10)
        echo "Running Testing & QA..."
        bash .claude/agents/testing/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ Testing & QA completed"
        else
            echo "✗ Testing & QA failed"
            exit 1
        fi
        ;;
    11)
        echo "Deploying with DevOps..."
        bash .claude/agents/devops/run-tasks.sh
        if [ $? -eq 0 ]; then
            echo "✓ DevOps & Deployment completed"
        else
            echo "✗ DevOps & Deployment failed"
            exit 1
        fi
        ;;
    12)
        echo "═══ FULL DEVELOPMENT CYCLE ═══"
        echo "This will execute all 13 agents in optimal order..."

        # Phase 1: Foundation (Parallel)
        echo "Phase 1: Foundation (Database + Security + API)"
        bash .claude/agents/database/run-tasks.sh &
        PID1=$!
        bash .claude/agents/security/run-tasks.sh &
        PID2=$!
        wait $PID1 $PID2 || { echo "✗ Foundation failed"; exit 1; }

        # Phase 1: API
        bash .claude/agents/api/run-tasks.sh || { echo "✗ API failed"; exit 1; }
        echo "✓ Phase 1 completed"

        # Phase 2: RADIUS + Controllers (RADIUS first)
        echo "Phase 2: Core Integration (RADIUS + Controllers)"
        bash .claude/agents/radius/run-tasks.sh || { echo "✗ RADIUS failed"; exit 1; }
        bash .claude/agents/controller/run-tasks.sh || { echo "✗ Controllers failed"; exit 1; }
        echo "✓ Phase 2 completed"

        # Phase 3: Core Features (Can run in parallel)
        echo "Phase 3: Core Features (Billing + Performance + Integrations)"
        bash .claude/agents/billing/run-tasks.sh &
        PID1=$!
        bash .claude/agents/performance/run-tasks.sh &
        PID2=$!
        bash .claude/agents/integration/run-tasks.sh &
        PID3=$!
        wait $PID1 $PID2 $PID3 || { echo "✗ Core Features failed"; exit 1; }
        echo "✓ Phase 3 completed"

        # Phase 4: Analytics + Frontend
        echo "Phase 4: User Interfaces (Analytics + Frontend)"
        bash .claude/agents/analytics/run-tasks.sh || { echo "✗ Analytics failed"; exit 1; }
        bash .claude/agents/frontend/run-tasks.sh || { echo "✗ Frontend failed"; exit 1; }
        echo "✓ Phase 4 completed"

        # Phase 5: Testing
        echo "Phase 5: Testing & QA"
        bash .claude/agents/testing/run-tasks.sh || { echo "✗ Testing failed"; exit 1; }
        echo "✓ Phase 5 completed"

        # Phase 6: DevOps
        echo "Phase 6: Deployment"
        bash .claude/agents/devops/run-tasks.sh || { echo "✗ DevOps failed"; exit 1; }
        echo "✓ Phase 6 completed"

        # Documentation (Throughout)
        echo "Finalizing Documentation..."
        bash .claude/agents/documentation/run-tasks.sh || { echo "✗ Documentation failed"; exit 1; }

        echo "═══ ALL 13 AGENTS COMPLETED SUCCESSFULLY ═══"
        ;;
    13)
        echo ""
        echo "═══ RUN SPECIFIC AGENT ═══"
        echo "1) Database Agent"
        echo "2) Security Agent"
        echo "3) API Agent"
        echo "4) RADIUS Agent"
        echo "5) Controller Agent"
        echo "6) Billing Agent"
        echo "7) Performance Agent"
        echo "8) Integration Agent"
        echo "9) Analytics Agent"
        echo "10) Frontend Agent"
        echo "11) Testing Agent"
        echo "12) DevOps Agent"
        echo "13) Documentation Agent"
        echo ""
        read -p "Select agent [1-13]: " agent_choice

        case $agent_choice in
            1) bash .claude/agents/database/run-tasks.sh ;;
            2) bash .claude/agents/security/run-tasks.sh ;;
            3) bash .claude/agents/api/run-tasks.sh ;;
            4) bash .claude/agents/radius/run-tasks.sh ;;
            5) bash .claude/agents/controller/run-tasks.sh ;;
            6) bash .claude/agents/billing/run-tasks.sh ;;
            7) bash .claude/agents/performance/run-tasks.sh ;;
            8) bash .claude/agents/integration/run-tasks.sh ;;
            9) bash .claude/agents/analytics/run-tasks.sh ;;
            10) bash .claude/agents/frontend/run-tasks.sh ;;
            11) bash .claude/agents/testing/run-tasks.sh ;;
            12) bash .claude/agents/devops/run-tasks.sh ;;
            13) bash .claude/agents/documentation/run-tasks.sh ;;
            *) echo "Invalid agent selection"; exit 1 ;;
        esac

        if [ $? -eq 0 ]; then
            echo "✓ Agent completed successfully"
        else
            echo "✗ Agent failed"
            exit 1
        fi
        ;;
    *)
        echo "Invalid choice. Please select 0-13"
        exit 1
        ;;
esac