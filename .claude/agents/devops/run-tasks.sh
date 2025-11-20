#!/bin/bash

# WiFight DevOps Agent - Task Execution Script

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║        WiFight ISP System - DevOps Agent (Infrastructure)    ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

PROJECT_ROOT=$(pwd)
LOG_DIR="storage/logs/devops"
BACKUP_DIR="storage/backups"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

mkdir -p "$LOG_DIR"
mkdir -p "$BACKUP_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_DIR/devops-agent.log"
}

task_header() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " TASK: $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

task_header "Checking DevOps Tools"
log "Verifying required tools..."

tools=("docker" "docker-compose" "git")
for tool in "${tools[@]}"; do
    if command -v $tool &> /dev/null; then
        echo -e "${GREEN}✓${NC} $tool is installed"
    else
        echo -e "${RED}✗${NC} $tool is not installed"
    fi
done

task_header "Validating Docker Configuration"
log "Checking Docker files..."

if [ -f "Dockerfile" ]; then
    echo -e "${GREEN}✓${NC} Dockerfile found"
else
    echo -e "${YELLOW}⚠${NC} Dockerfile not found"
fi

if [ -f "docker-compose.yml" ]; then
    echo -e "${GREEN}✓${NC} docker-compose.yml found"
else
    echo -e "${YELLOW}⚠${NC} docker-compose.yml not found"
fi

task_header "Checking CI/CD Configuration"
if [ -d ".github/workflows" ]; then
    echo -e "${GREEN}✓${NC} GitHub Actions workflows found"
    ls -1 .github/workflows/
else
    echo -e "${YELLOW}⚠${NC} No CI/CD workflows found"
fi

task_header "Checking Environment Files"
if [ -f ".env.example" ]; then
    echo -e "${GREEN}✓${NC} .env.example found"
else
    echo -e "${YELLOW}⚠${NC} .env.example not found"
fi

task_header "Checking Deployment Scripts"
for script in scripts/deploy-*.sh; do
    if [ -f "$script" ]; then
        echo -e "${GREEN}✓${NC} $(basename $script)"
    fi
done

log "DevOps agent execution completed"
echo -e "${GREEN}═══ DevOps Check Complete ═══${NC}"
