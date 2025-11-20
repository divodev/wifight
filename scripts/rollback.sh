#!/bin/bash

echo "═══════════════════════════════════════════════════════════════"
echo "  WiFight ISP System - Rollback Deployment"
echo "═══════════════════════════════════════════════════════════════"
echo ""

RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m'

echo -e "${YELLOW}WARNING: This will rollback to the previous Git commit${NC}"
read -p "Are you sure you want to proceed? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Rollback cancelled"
    exit 0
fi

echo ""
echo "Step 1: Stop containers..."
docker-compose down

echo ""
echo "Step 2: Rollback code..."
git reset --hard HEAD~1

echo ""
echo "Step 3: Restore database from latest backup..."
LATEST_BACKUP=$(ls -t storage/backups/*.sql.gz | head -1)
if [ -f "$LATEST_BACKUP" ]; then
    echo "Restoring from: $LATEST_BACKUP"
    gunzip < "$LATEST_BACKUP" | mysql -u root -p wifight_isp
    echo -e "${GREEN}✓${NC} Database restored"
else
    echo -e "${RED}✗${NC} No backup found"
fi

echo ""
echo "Step 4: Restart containers..."
docker-compose up -d

echo ""
echo -e "${GREEN}═══ Rollback Complete ═══${NC}"
