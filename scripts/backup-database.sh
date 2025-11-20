#!/bin/bash

# WiFight ISP System - Database Backup Script

BACKUP_DIR="storage/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/wifight_backup_$TIMESTAMP.sql"

# Load .env variables
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

echo "Backing up database to: $BACKUP_FILE"

# Perform backup
mysqldump -h "${DB_HOST:-localhost}" \
          -u "${DB_USERNAME:-root}" \
          -p"${DB_PASSWORD}" \
          "${DB_DATABASE:-wifight_isp}" > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "✓ Database backup successful"
    
    # Compress backup
    gzip "$BACKUP_FILE"
    echo "✓ Backup compressed: ${BACKUP_FILE}.gz"
    
    # Delete backups older than 30 days
    find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
    echo "✓ Old backups cleaned up"
    
    exit 0
else
    echo "✗ Database backup failed"
    exit 1
fi
