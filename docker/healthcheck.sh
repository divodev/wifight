#!/bin/sh

################################################################################
# WiFight ISP System - Health Check Script
################################################################################

# Check if PHP-FPM is running
if ! pgrep -x php-fpm > /dev/null; then
    echo "PHP-FPM is not running"
    exit 1
fi

# Check if Nginx is running
if ! pgrep -x nginx > /dev/null; then
    echo "Nginx is not running"
    exit 1
fi

# Check if application responds
if ! curl -sf http://localhost/api/v1/health > /dev/null; then
    echo "Application health check failed"
    exit 1
fi

echo "All health checks passed"
exit 0
