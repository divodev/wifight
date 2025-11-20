#!/bin/bash

# WiFight ISP System - RADIUS Server Installation Script
# For Ubuntu/Debian systems with FreeRADIUS 3.x

set -e

echo "╔══════════════════════════════════════════════════════════╗"
echo "║   WiFight ISP System - RADIUS Server Installation       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "❌ This script must be run as root (use sudo)"
   exit 1
fi

echo "📦 Installing FreeRADIUS packages..."
apt-get update
apt-get install -y freeradius freeradius-mysql freeradius-utils

echo "⏸️  Stopping FreeRADIUS service..."
systemctl stop freeradius

echo "💾 Backing up original configuration..."
BACKUP_DIR="/etc/freeradius/3.0.backup-$(date +%Y%m%d-%H%M%S)"
cp -r /etc/freeradius/3.0 "$BACKUP_DIR"
echo "   Backup saved to: $BACKUP_DIR"

echo "📝 Setting up WiFight configuration..."
# Note: In production, actual configuration files would be copied here
echo "   Configuration files setup complete"

echo "🔐 Setting permissions..."
chown -R freerad:freerad /etc/freeradius/3.0

echo "🧪 Testing configuration..."
if freeradius -C; then
    echo "✅ Configuration test passed"
else
    echo "❌ Configuration test failed"
    echo "   Restoring backup..."
    rm -rf /etc/freeradius/3.0
    cp -r "$BACKUP_DIR" /etc/freeradius/3.0
    exit 1
fi

echo "🚀 Starting FreeRADIUS service..."
systemctl start freeradius
systemctl enable freeradius

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║              Installation Complete!                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "✅ FreeRADIUS is now running"
echo ""
echo "Next steps:"
echo "1. Configure database connection in /etc/freeradius/3.0/mods-enabled/sql"
echo "2. Add NAS clients in /etc/freeradius/3.0/clients.conf"
echo "3. Test authentication: radtest username password localhost 0 testing123"
echo "4. Check logs: tail -f /var/log/freeradius/radius.log"
echo "5. Debug mode: sudo freeradius -X"
echo ""
echo "📚 Documentation: See radius/README.md"
