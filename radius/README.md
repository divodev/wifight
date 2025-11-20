# WiFight ISP System - RADIUS Server Setup

This directory contains FreeRADIUS 3.x configuration files for the WiFight ISP billing system.

## Overview

The RADIUS server handles:
- User authentication for network access
- Session accounting (bandwidth usage tracking)
- Dynamic authorization (CoA) for real-time session control
- Integration with WiFight database

## Directory Structure

```
radius/
├── README.md                    # This file
├── install.sh                   # Installation script
├── clients.conf                 # NAS client configuration
├── dictionary.wifight           # Custom VSAs
├── mods-enabled/
│   ├── sql                     # SQL module configuration
│   └── sqlcounter              # Bandwidth counters
├── sql/mysql/
│   ├── queries.conf            # SQL queries for auth & accounting
│   └── schema.sql              # RADIUS tables (if needed)
└── sites-enabled/
    ├── default                 # Main virtual server
    └── coa                     # Change of Authorization server
```

## Installation

### Ubuntu/Debian

```bash
# Install FreeRADIUS
sudo apt-get update
sudo apt-get install -y freeradius freeradius-mysql freeradius-utils

# Stop FreeRADIUS
sudo systemctl stop freeradius

# Backup original configuration
sudo cp -r /etc/freeradius/3.0 /etc/freeradius/3.0.backup

# Copy WiFight configuration files
sudo cp -r radius/* /etc/freeradius/3.0/

# Set permissions
sudo chown -R freerad:freerad /etc/freeradius/3.0/

# Test configuration
sudo freeradius -X

# If test passes, start service
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

## Configuration

### 1. Database Connection

Edit `mods-enabled/sql`:

```conf
sql {
    driver = "rlm_sql_mysql"
    server = "localhost"
    port = 3306
    login = "wifight_radius"
    password = "your_secure_password"
    radius_db = "wifight_isp"
}
```

### 2. NAS Clients

Add your controllers to `clients.conf`:

```conf
client mikrotik-router {
    ipaddr = 192.168.1.1
    secret = your_shared_secret_here
    nastype = mikrotik
}
```

### 3. Testing

Test user authentication:

```bash
radtest username password localhost 0 testing123
```

Test with specific NAS:

```bash
echo "User-Name=testuser,User-Password=testpass,NAS-IP-Address=192.168.1.1" | radclient localhost:1812 auth testing123
```

## Integration with WiFight

The RADIUS server integrates with WiFight through:

1. **Database**: Queries `users`, `subscriptions`, `plans`, `sessions` tables
2. **API**: Management endpoints at `/api/v1/radius/*`
3. **CoA**: Automatic disconnect/bandwidth updates via CoA

## Custom Attributes (VSAs)

WiFight uses custom VSAs for bandwidth limiting:

- `WiFight-Bandwidth-Up` - Upload bandwidth in Kbps
- `WiFight-Bandwidth-Down` - Download bandwidth in Kbps
- `WiFight-Session-Timeout` - Session timeout in seconds
- `WiFight-Data-Limit` - Data limit in MB

## Monitoring

Check RADIUS server status:

```bash
# Service status
sudo systemctl status freeradius

# Real-time debug
sudo freeradius -X

# View logs
tail -f /var/log/freeradius/radius.log
```

## Troubleshooting

### Authentication Issues

1. Check RADIUS is running: `sudo systemctl status freeradius`
2. Check logs: `tail -f /var/log/freeradius/radius.log`
3. Test in debug mode: `sudo freeradius -X`
4. Verify database connection in SQL module
5. Check NAS client secret matches

### Accounting Issues

1. Verify SQL accounting queries
2. Check `radius_accounting` table permissions
3. Review accounting logs
4. Test interim-update packets

### Performance Issues

1. Increase thread pool size in `radiusd.conf`
2. Optimize SQL connection pool
3. Enable caching for authorization
4. Monitor database query performance

## Security Considerations

1. **Strong Secrets**: Use 32+ character random secrets for NAS clients
2. **Firewall**: Restrict RADIUS ports (1812, 1813, 3799) to known NAS IPs
3. **Database**: Use dedicated RADIUS database user with minimal permissions
4. **Logs**: Regular log rotation and monitoring
5. **TLS**: Enable SQL over TLS for remote database connections

## Performance Tuning

For high-traffic deployments (10,000+ users):

1. Increase thread pool: `thread pool { start_servers = 50, max_servers = 256 }`
2. Enable caching: Configure `cache` module for auth results
3. SQL connection pooling: Increase `num` and `spare` in SQL module
4. Optimize queries: Add proper indexes to database
5. Disable unnecessary modules

## Support

- Documentation: https://freeradius.org/documentation/
- WiFight API Docs: `/api/v1/docs`
- Issues: Check application logs and RADIUS debug mode
