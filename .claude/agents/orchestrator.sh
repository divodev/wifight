#!/bin/bash

# WiFight Development - Master Agent Orchestrator
# This script coordinates all subagents across all development phases

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║       WiFight ISP System - Development Orchestrator          ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Configuration
PROJECT_ROOT=$(pwd)
AGENTS_DIR=".claude/agents"
PLANS_DIR=".claude/plans"
LOG_DIR="storage/logs/agents"

# Create logs directory
mkdir -p "$LOG_DIR"

# Function to log with timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_DIR/orchestrator.log"
}

# Function to run agent phase
run_phase() {
    local phase=$1
    local agent=$2
    
    log "Starting Phase: $phase with Agent: $agent"
    
    if [ -f "$AGENTS_DIR/$agent/run-tasks.sh" ]; then
        bash "$AGENTS_DIR/$agent/run-tasks.sh" 2>&1 | tee -a "$LOG_DIR/${agent}-$(date '+%Y%m%d').log"
        
        if [ $? -eq 0 ]; then
            log "✓ Phase $phase completed successfully"
            return 0
        else
            log "✗ Phase $phase failed"
            return 1
        fi
    else
        log "✗ Agent script not found: $AGENTS_DIR/$agent/run-tasks.sh"
        return 1
    fi
}

# Display menu
echo "Select development phase to execute:"
echo ""
echo "1) Phase 1 - Foundation (Database + API)"
echo "2) Phase 2 - Controller Integration"
echo "3) Phase 3 - Core Features"
echo "4) Phase 4 - Frontend Development"
echo "5) Phase 5 - Testing & QA"
echo "6) Phase 6 - Deployment"
echo "7) Run All Phases (Full Development)"
echo "8) Custom Agent Execution"
echo "0) Exit"
echo ""
read -p "Enter choice [0-8]: " choice

case $choice in
    1)
        log "═══ Starting Phase 1: Foundation ═══"
        run_phase "Phase 1" "database"
        if [ $? -eq 0 ]; then
            run_phase "Phase 1" "api"
        fi
        ;;
    2)
        log "═══ Starting Phase 2: Controller Integration ═══"
        run_phase "Phase 2" "controllers"
        ;;
    3)
        log "═══ Starting Phase 3: Core Features ═══"
        # Run multiple agents for core features
        run_phase "Phase 3" "api"
        run_phase "Phase 3" "billing"
        run_phase "Phase 3" "vouchers"
        ;;
    4)
        log "═══ Starting Phase 4: Frontend Development ═══"
        run_phase "Phase 4" "frontend"
        ;;
    5)
        log "═══ Starting Phase 5: Testing & QA ═══"
        run_phase "Phase 5" "testing"
        ;;
    6)
        log "═══ Starting Phase 6: Deployment ═══"
        run_phase "Phase 6" "devops"
        ;;
    7)
        log "═══ Starting Full Development Cycle ═══"
        
        # Phase 1: Foundation
        run_phase "Phase 1" "database" || exit 1
        run_phase "Phase 1" "api" || exit 1
        run_phase "Phase 1" "security" || exit 1
        
        # Phase 2: Controllers
        run_phase "Phase 2" "controllers" || exit 1
        
        # Phase 3: Core Features
        run_phase "Phase 3" "api" || exit 1
        
        # Phase 4: Frontend
        run_phase "Phase 4" "frontend" || exit 1
        
        # Phase 5: Testing
        run_phase "Phase 5" "testing" || exit 1
        
        # Phase 6: Deployment
        run_phase "Phase 6" "devops" || exit 1
        
        log "═══ Full Development Cycle Completed ═══"
        ;;
    8)
        echo "Available agents:"
        ls -1 "$AGENTS_DIR" | grep -v "orchestrator.sh"
        read -p "Enter agent name: " agent_name
        read -p "Enter phase description: " phase_desc
        run_phase "$phase_desc" "$agent_name"
        ;;
    0)
        log "Exiting orchestrator"
        exit 0
        ;;
    *)
        log "Invalid choice"
        exit 1
        ;;
esac

echo ""
log "Orchestration completed. Check logs in: $LOG_DIR"
EOF

chmod +x .claude/agents/orchestrator.sh

6. Adding Development Plans to Claude Code
Step 6.1: Initialize Claude Code in Project
bash# Navigate to project root
cd /path/to/wifight-isp-system

# Initialize Claude Code
claude-code init

# This creates:
# .claude/
# ├── config.json
# ├── context/
# └── cache/
Step 6.2: Configure Claude Code
bash# Edit Claude Code configuration
cat > .claude/config.json << 'EOF'
{
  "version": "1.0",
  "project": {
    "name": "WiFight ISP System",
    "description": "Multi-vendor ISP billing and management system",
    "root": ".",
    "language": "php",
    "framework": "vanilla"
  },
  
  "model": {
    "name": "claude-sonnet-4-20250514",
    "temperature": 0.4,
    "max_tokens": 8000
  },
  
  "context": {
    "files": [
      ".claude/plans/**/*.md",
      "README.md",
      "database/schema/**/*.sql",
      "backend/**/*.php",
      "frontend/**/*.js",
      "docs/**/*.md"
    ],
    "exclude": [
      "vendor/**",
      "node_modules/**",
      "storage/logs/**",
      ".git/**"
    ],
    "max_file_size": "100kb"
  },
  
  "agents": {
    "directory": ".claude/agents",
    "manifest": ".claude/agents/agents.json",
    "enable_coordination": true
  },
  
  "output": {
    "directory": ".",
    "preserve_structure": true,
    "backup_existing": true
  },
  
  "logging": {
    "level": "info",
    "directory": "storage/logs/claude-code",
    "max_size": "10mb",
    "rotate": true
  },
  
  "git": {
    "auto_commit": false,
    "branch_per_agent": false,
    "commit_message_template": "[Claude Code - {agent}] {task}"
  }
}