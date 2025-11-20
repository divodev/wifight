#!/bin/bash

echo "═══════════════════════════════════════════════════════════════"
echo "  WiFight ISP System - Production Deployment"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Configuration
APP_DIR="/var/www/wifight-isp"
BACKUP_DIR="$APP_DIR/storage/backups"
LOG_FILE="$APP_DIR/storage/logs/deployment.log"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

echo "Step 1: Backup database..."
bash scripts/backup-database.sh
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Database backed up successfully"
else
    echo -e "${RED}✗${NC} Database backup failed"
    exit 1
fi

echo ""
echo "Step 2: Pull latest code..."
git pull origin master
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Code updated successfully"
else
    echo -e "${RED}✗${NC} Git pull failed"
    exit 1
fi

echo ""
echo "Step 3: Install dependencies..."
composer install --no-dev --optimize-autoloader
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Dependencies installed"
else
    echo -e "${RED}✗${NC} Composer install failed"
    exit 1
fi

echo ""
echo "Step 4: Rebuild Docker containers..."
docker-compose down
docker-compose build
docker-compose up -d

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Containers rebuilt and started"
else
    echo -e "${RED}✗${NC} Docker deployment failed"
    exit 1
fi

echo ""
echo "Step 5: Run database migrations..."
# Add migration command here when available

echo ""
echo "Step 6: Clear cache..."
# Add cache clearing commands here

echo ""
echo "Step 7: Health check..."
sleep 5
curl -f http://localhost/api/v1/health > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Health check passed"
else
    echo -e "${YELLOW}⚠${NC} Health check warning"
fi

echo ""
echo -e "${GREEN}═══ Production Deployment Complete ═══${NC}"
log "Production deployment completed successfully"
