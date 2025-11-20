# WiFight ISP System - Administrator Guide

## Overview

This guide covers administrative tasks for managing the WiFight ISP system.

## Getting Started

### Logging In

1. Navigate to your WiFight installation URL
2. Enter your admin credentials
3. Click "Login"

Default credentials:
- Email: admin@wifight.local
- Password: admin123

**IMPORTANT**: Change the default password immediately!

## Dashboard Overview

The admin dashboard provides:
- System statistics
- Active sessions count
- Revenue overview
- Recent activities
- System health status

## User Management

### Creating Users

1. Navigate to Users > Create User
2. Fill in user details:
   - Name
   - Email
   - Password
   - Role (Admin/Reseller/User)
   - Phone (optional)
3. Click "Create User"

### User Roles

**Admin**:
- Full system access
- Manage all users and resellers
- Configure system settings
- View all reports

**Reseller**:
- Manage own customers
- Assign controllers
- Generate vouchers
- View own reports

**User**:
- View own profile
- View subscription status
- Make payments
- View usage history

### Managing Users

**Edit User**:
1. Go to Users > All Users
2. Click "Edit" next to user
3. Update details
4. Click "Save"

**Delete User**:
1. Go to Users > All Users
2. Click "Delete" next to user
3. Confirm deletion

**Suspend User**:
1. Edit user
2. Change status to "Suspended"
3. Save

## Controller Management

### Adding a Controller

1. Navigate to Controllers > Add Controller
2. Select controller type:
   - MikroTik RouterOS
   - TP-Link Omada
   - Ruijie Networks
   - Cisco Meraki
3. Enter connection details
4. Click "Test Connection"
5. If successful, click "Save"

### Controller Types

**MikroTik RouterOS**:
- Host: IP address
- Port: 8728 (default)
- Username: admin username
- Password: admin password

**TP-Link Omada**:
- Host: Controller URL
- Username: admin username
- Password: admin password
- Site ID: Site identifier

**Ruijie Networks**:
- Host: API endpoint
- API Key: Your API key
- API Secret: Your secret key

**Cisco Meraki**:
- API Key: Dashboard API key
- Network ID: Network identifier

### Testing Controllers

Test connection:
1. Go to Controllers > All Controllers
2. Click "Test" next to controller
3. View connection status

### Troubleshooting Controllers

**Connection Failed**:
- Verify IP address/hostname
- Check firewall rules
- Verify credentials
- Ensure API is enabled

**Authentication Failed**:
- Verify username/password
- Check API key validity
- Verify permissions

## Plan Management

### Creating Plans

1. Go to Plans > Create Plan
2. Enter plan details:
   - Name (e.g., "Basic 10Mbps")
   - Download speed (kbps)
   - Upload speed (kbps)
   - Data limit (MB, optional)
   - Duration (days)
   - Price
   - Currency
3. Click "Create Plan"

### Plan Examples

**Unlimited Plan**:
- Name: Unlimited 20Mbps
- Download: 20480 kbps (20 Mbps)
- Upload: 10240 kbps (10 Mbps)
- Data Limit: Leave empty
- Duration: 30 days
- Price: 50.00

**Limited Data Plan**:
- Name: 50GB Package
- Download: 10240 kbps
- Upload: 5120 kbps
- Data Limit: 51200 MB (50 GB)
- Duration: 30 days
- Price: 25.00

## Session Management

### Viewing Active Sessions

1. Go to Sessions > Active Sessions
2. View all connected users
3. See real-time statistics:
   - Username
   - IP address
   - MAC address
   - Data used
   - Session duration
   - Controller

### Disconnecting Sessions

**Single Session**:
1. Find session in Active Sessions
2. Click "Disconnect"
3. Confirm action

**Multiple Sessions**:
1. Select sessions with checkboxes
2. Click "Bulk Disconnect"
3. Confirm action

### Session Reports

View historical sessions:
1. Go to Reports > Session History
2. Filter by:
   - Date range
   - User
   - Controller
3. Export to CSV/PDF

## Payment Management

### Viewing Payments

1. Go to Payments > All Payments
2. View payment list with:
   - User
   - Amount
   - Method
   - Status
   - Date

### Payment Methods

**Stripe**:
- Configure in Settings > Payments
- Enter Stripe API keys
- Enable/disable

**PayPal**:
- Configure in Settings > Payments
- Enter PayPal credentials
- Enable/disable

**Manual/Cash**:
- Admin records payment manually
- Useful for cash/bank transfer

### Processing Manual Payments

1. Go to Payments > Record Payment
2. Select user
3. Select plan
4. Enter amount
5. Select payment method (Cash/Bank Transfer)
6. Add notes (optional)
7. Click "Record Payment"

## Voucher Management

### Generating Vouchers

1. Go to Vouchers > Generate
2. Select plan
3. Enter quantity (max 100)
4. Enter prefix (optional, e.g., "WIFI")
5. Click "Generate"
6. Export or print vouchers

### Voucher Format

Generated vouchers:
```
Code: WIFI-ABC123
Plan: Basic 10Mbps
Valid Until: Not Redeemed
```

### Managing Vouchers

**View All Vouchers**:
- Go to Vouchers > All Vouchers
- Filter by status (Unused/Used/Expired)

**Delete Voucher**:
- Find voucher
- Click "Delete"
- Confirm

**Export Vouchers**:
- Select vouchers
- Click "Export"
- Choose format (CSV/PDF)

## Reports

### Available Reports

**Revenue Report**:
- Total revenue by period
- Payment method breakdown
- Revenue by plan

**User Growth Report**:
- New users per period
- Active vs inactive users
- User distribution by role

**Usage Report**:
- Data usage statistics
- Most active users
- Controller utilization

**Session Report**:
- Total sessions
- Average session duration
- Peak usage times

### Generating Reports

1. Go to Reports > Report Type
2. Select date range
3. Apply filters
4. Click "Generate"
5. Export (CSV/PDF/Excel)

## System Settings

### General Settings

1. Go to Settings > General
2. Configure:
   - Site name
   - Admin email
   - Timezone
   - Currency
   - Language

### Email Settings

1. Go to Settings > Email
2. Configure SMTP:
   - Host
   - Port
   - Username
   - Password
   - From address
3. Test email configuration

### Security Settings

**Password Policy**:
- Minimum length
- Require uppercase
- Require numbers
- Require symbols

**Session Settings**:
- Session timeout
- JWT expiration
- Refresh token expiration

**API Settings**:
- Enable/disable API
- Rate limiting
- CORS settings

## Backup and Maintenance

### Database Backup

**Manual Backup**:
1. Go to Settings > Backup
2. Click "Backup Now"
3. Download backup file

**Automatic Backup**:
1. Go to Settings > Backup
2. Enable automatic backups
3. Set schedule (daily/weekly)
4. Set retention period

**Restore Backup**:
1. Go to Settings > Backup
2. Click "Restore"
3. Upload backup file
4. Confirm restoration

### System Maintenance

**Clear Logs**:
1. Go to Settings > Maintenance
2. Click "Clear Logs"
3. Confirm

**Clear Cache**:
1. Go to Settings > Maintenance
2. Click "Clear Cache"
3. Confirm

**Update System**:
1. Backup database
2. Pull latest code
3. Run migrations
4. Clear cache

## Troubleshooting

### Common Issues

**Users Can't Connect**:
1. Check controller status
2. Verify user subscription is active
3. Check session limits
4. Test controller connectivity

**Payments Not Processing**:
1. Verify payment gateway credentials
2. Check API keys
3. Review payment logs
4. Test in sandbox mode

**Slow Performance**:
1. Check server resources
2. Optimize database
3. Enable Redis caching
4. Review slow query log

### Getting Help

- Check system logs
- Review FAQ
- Contact support
- Submit bug report

## Best Practices

1. **Regular Backups**: Schedule automated daily backups
2. **Monitor Sessions**: Review active sessions regularly
3. **Update Regularly**: Keep system updated
4. **Security**: Use strong passwords, enable 2FA
5. **Performance**: Monitor system resources
6. **Documentation**: Keep notes of custom configurations

## Support

For administrative support:
- Email: support@yourcompany.com
- Documentation: docs/
- GitHub Issues: Report bugs and feature requests
