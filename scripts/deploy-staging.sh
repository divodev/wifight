#!/bin/bash

echo "═══════════════════════════════════════════════════════════════"
echo "  WiFight ISP System - Staging Deployment"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Configuration
APP_DIR="/var/www/wifight-isp-staging"
LOG_FILE="$APP_DIR/storage/logs/deployment.log"

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo "Step 1: Pull latest code..."
cd $APP_DIR
git pull origin develop

echo ""
echo "Step 2: Install dependencies..."
composer install --optimize-autoloader

echo ""
echo "Step 3: Rebuild Docker containers..."
docker-compose -f docker-compose.staging.yml down
docker-compose -f docker-compose.staging.yml build
docker-compose -f docker-compose.staging.yml up -d

echo ""
echo "Step 4: Health check..."
sleep 5
curl -f http://staging.localhost/api/v1/health > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Health check passed"
else
    echo -e "${RED}✗${NC} Health check failed"
fi

echo ""
echo -e "${GREEN}═══ Staging Deployment Complete ═══${NC}"
