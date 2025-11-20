# WiFight ISP System - Installation Guide

## System Requirements

### Minimum Requirements
- PHP 8.1 or higher
- MySQL 8.0 or higher
- Redis (optional, for caching)
- 2GB RAM
- 20GB Disk Space
- Linux/Windows/macOS

### Recommended for Production
- PHP 8.2
- MySQL 8.0
- Redis 7.0
- 4GB+ RAM
- 50GB+ SSD
- Ubuntu 22.04 LTS

## Installation Methods

### Method 1: Docker (Recommended)

#### Prerequisites
- Docker 20.10+
- Docker Compose 2.0+

#### Steps

1. Clone the repository:
```bash
git clone https://github.com/yourusername/wifight-isp-system.git
cd wifight-isp-system
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Edit .env with your settings:
```bash
nano .env
```

4. Build and start containers:
```bash
docker-compose build
docker-compose up -d
```

5. Import database:
```bash
docker-compose exec app php init.php
```

6. Access the application:
- Web: http://localhost
- API: http://localhost/api/v1/health

### Method 2: Traditional (XAMPP/LAMP/WAMP)

#### Prerequisites
- XAMPP/LAMP/WAMP stack installed
- Composer installed
- Git installed

#### Steps

1. Clone to web directory:
```bash
cd C:\xampp\htdocs  # Windows
# or
cd /var/www/html    # Linux

git clone https://github.com/yourusername/wifight-isp-system.git
cd wifight-isp-system
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Configure environment:
```bash
cp .env.example .env
# Edit .env with database credentials
```

4. Create database:
```bash
mysql -u root -p -e "CREATE DATABASE wifight_isp"
```

5. Import schema:
```bash
mysql -u root -p wifight_isp < database/schema.sql
```

6. Set permissions:
```bash
chmod -R 755 storage
chmod -R 755 database
```

7. Start web server:
```bash
php -S localhost:8000
# Or use Apache/Nginx
```

## Post-Installation

### 1. Test Installation

Visit: http://localhost/api/v1/health

Expected response:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "database": "connected"
  }
}
```

### 2. Default Login

- Email: admin@wifight.local
- Password: admin123

**IMPORTANT**: Change this password immediately!

### 3. Configure Controllers

Add your network controllers via:
- Admin Panel > Controllers > Add New
- API: POST /api/v1/controllers

### 4. Create Plans

Add internet plans via:
- Admin Panel > Plans > Create Plan
- API: POST /api/v1/plans

## Troubleshooting

### Database Connection Failed

Check .env file:
```
DB_HOST=localhost
DB_DATABASE=wifight_isp
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Permission Errors

Fix storage permissions:
```bash
chmod -R 755 storage
chown -R www-data:www-data storage  # Linux
```

### Composer Errors

Clear composer cache:
```bash
composer clear-cache
composer install --no-cache
```

## Next Steps

1. Read the [Configuration Guide](CONFIGURATION.md)
2. Set up [SSL/TLS](SSL_SETUP.md)
3. Configure [Backups](BACKUP.md)
4. Review [Security Hardening](SECURITY.md)

## Support

For installation issues:
- Check FAQ: docs/user/FAQ.md
- GitHub Issues: https://github.com/yourusername/wifight-isp-system/issues
