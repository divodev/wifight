# WiFight ISP Billing & Management System

Multi-vendor ISP billing system with support for MikroTik, TP-Link Omada, Ruijie, and Cisco Meraki controllers.

## ✨ Features
- 🎛️ Multi-vendor controller integration (MikroTik, Omada, Ruijie, Meraki)
- 🔐 RADIUS authentication & accounting
- 💳 Automated billing & subscriptions
- 👤 User self-service portal
- 📊 Admin dashboard
- 🎫 Voucher generation & management
- 📡 Real-time session monitoring
- 🔒 JWT-based authentication
- 📝 Comprehensive audit logging

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Composer
- Apache/Nginx web server

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/wifight-isp-system.git
   cd wifight-isp-system
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Run initialization script**
   ```bash
   php init.php
   ```
   This will:
   - Create `.env` file from template
   - Check system requirements
   - Set up directory structure
   - Initialize the database (optional)

4. **Configure environment**
   - Edit `.env` file with your database credentials
   - Update JWT secret key
   - Configure RADIUS server (if needed)

5. **Access the system**
   - Web interface: `http://localhost/`
   - API health check: `http://localhost/api/v1/health`
   - Default admin: `admin@wifight.local` / `admin123`

## 📁 Project Structure

```
wifight-isp-system/
├── backend/
│   ├── api/              # API endpoints
│   │   └── v1/          # API version 1
│   ├── config/          # Configuration files
│   ├── models/          # Data models
│   ├── services/        # Business logic
│   │   └── controllers/ # Controller integrations
│   └── utils/           # Utility classes
├── database/
│   ├── schema.sql       # Database schema
│   └── migrations/      # Database migrations
├── frontend/            # Admin dashboard
├── portal/              # User portal
├── storage/
│   ├── logs/           # Application logs
│   ├── uploads/        # File uploads
│   └── backups/        # Database backups
├── .claude/
│   ├── agents/         # Agent configurations
│   └── plans/          # Development plans
├── .env.example        # Environment template
├── init.php            # Initialization script
└── index.php           # Main entry point
```

## 🔧 Configuration

### Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE wifight_isp"

# Import schema
mysql -u root -p wifight_isp < database/schema.sql
```

### Web Server Configuration

**Apache (.htaccess included)**
- Mod_rewrite enabled
- AllowOverride All

**Nginx**
```nginx
location /api {
    rewrite ^/api/(.*)$ /backend/api/index.php last;
}
```

## 📚 API Documentation

### Authentication
```bash
# Login
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@wifight.local","password":"admin123"}'

# Register
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"user1","email":"user@example.com","password":"SecurePass123!","full_name":"John Doe"}'
```

### Health Check
```bash
curl http://localhost/api/v1/health
```

## 🎯 Development Phases

- ✅ **Phase 1: Foundation** - Database, Auth, Core APIs
- 🔄 **Phase 2: Controllers** - Multi-vendor integration
- ⏳ **Phase 3: Core Features** - Billing, Plans, Subscriptions
- ⏳ **Phase 4: User Interfaces** - Admin & User portals
- ⏳ **Phase 5: Testing & QA** - Comprehensive testing
- ⏳ **Phase 6: Deployment** - Docker, CI/CD

See `.claude/plans/` for detailed phase documentation.

## 🔒 Security

- Change default admin password immediately
- Use strong JWT secret in production
- Enable HTTPS
- Configure firewall rules
- Regular security audits
- Keep dependencies updated

## 🛠️ Development

### Available Commands
```bash
# Run initialization
php init.php

# Start development server
php -S localhost:8000

# Run tests (coming soon)
vendor/bin/phpunit

# Clear logs
rm -rf storage/logs/*.log
```

### Using Specialized Agents

The system includes specialized development agents:
- **database-agent**: Database schema & migrations
- **api-agent**: API endpoint development
- **controller-agent**: Controller integrations
- **frontend-agent**: UI development
- **security-agent**: Security implementation
- **testing-agent**: Test creation
- **devops-agent**: Deployment & infrastructure

See `.claude/agents/agents.json` for configuration.

## 📖 Documentation
- Development plans: `.claude/plans/`
- API documentation: `/docs/api/`
- User guide: `/docs/user-guide.md`
- Admin guide: `/docs/admin-guide.md`

## 🤝 Contributing
Contributions are welcome! Please read the contributing guidelines first.

## 📝 License
This project is licensed under the MIT License.

## 🆘 Support
- Documentation: `.claude/plans/`
- Issues: GitHub Issues
- Email: support@wifight.local

## 🙏 Acknowledgments
- MikroTik RouterOS API
- TP-Link Omada SDK
- Ruijie Networks API
- Cisco Meraki Dashboard API