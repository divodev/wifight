# WiFight ISP System - DevOps Plan

## Overview

This document outlines the DevOps strategy for the WiFight ISP System, including containerization, deployment automation, CI/CD pipelines, and production infrastructure management.

## Infrastructure Architecture

### Container Architecture

The system uses a multi-container Docker setup with the following services:

1. **PHP-FPM Application** - Runs the PHP backend
2. **Nginx Web Server** - HTTP/HTTPS reverse proxy
3. **MySQL Database** - Relational database
4. **Redis Cache** - Session and data caching
5. **FreeRADIUS Server** - RADIUS authentication

### Deployment Environments

| Environment | Purpose | Branch | Auto-Deploy |
|-------------|---------|--------|-------------|
| Development | Local development | feature/* | No |
| Staging | Pre-production testing | develop | Yes |
| Production | Live system | master | Manual approval |

## Docker Setup

### Building and Running

```bash
# Build Docker images
docker-compose build

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop all services
docker-compose down
```

### Container Health Checks

All containers include health checks that monitor:
- Application HTTP endpoint status
- Database connectivity
- Redis availability
- RADIUS server responsiveness

## CI/CD Pipeline

### Continuous Integration (ci.yml)

Triggers on: Push to master/develop, Pull Requests

**Stages:**
1. **Test** - Run PHPUnit test suite with MySQL and Redis
2. **Lint** - PHP_CodeSniffer (PSR-12) and PHPStan analysis
3. **Security** - Composer audit for vulnerabilities
4. **Build** - Build and validate Docker image

### Continuous Deployment (deploy.yml)

Triggers on: Push to master (manual approval for production)

**Stages:**
1. **Backup** - Database backup before deployment
2. **Deploy** - Pull code, rebuild containers, migrate database
3. **Health Check** - Verify application health
4. **Notify** - Send deployment notifications

## Deployment Scripts

### Production Deployment

```bash
bash scripts/deploy-production.sh
```

**Steps:**
1. Backup database
2. Pull latest code from master
3. Install Composer dependencies
4. Rebuild Docker containers
5. Run database migrations
6. Clear application cache
7. Run health check

### Staging Deployment

```bash
bash scripts/deploy-staging.sh
```

Similar to production but deploys from develop branch.

### Rollback

```bash
bash scripts/rollback.sh
```

**Steps:**
1. Stop containers
2. Rollback Git commit
3. Restore latest database backup
4. Restart containers

## Database Management

### Backups

**Automated Backups:**
- Schedule: Daily at 2:00 AM
- Retention: 30 days
- Location: `storage/backups/`
- Format: Compressed SQL (.sql.gz)

**Manual Backup:**
```bash
bash scripts/backup-database.sh
```

**Restore Backup:**
```bash
gunzip < storage/backups/backup.sql.gz | mysql -u root -p wifight_isp
```

### Migrations

Database migrations should be run after each deployment:

```bash
# Run migrations
php scripts/migrate-database.php
```

## Security Hardening

### SSL/TLS Configuration

**Let's Encrypt Setup:**
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal (cron)
0 3 * * * certbot renew --quiet
```

### Firewall Configuration

```bash
# UFW Firewall rules
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow 1812/udp  # RADIUS Auth
sudo ufw allow 1813/udp  # RADIUS Accounting
sudo ufw enable
```

### Environment Variables

Never commit `.env` files to version control. Use `.env.example` as a template.

**Required Variables:**
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `JWT_SECRET` (minimum 32 characters)
- `APP_ENV` (production, staging, development)
- `APP_DEBUG` (false in production)

## Monitoring and Logging

### Application Logging

Logs are stored in `storage/logs/`:
- `application.log` - General application logs
- `error.log` - Error logs
- `query.log` - Database queries (debug mode only)
- `access.log` - API access logs

### Container Logs

View real-time container logs:
```bash
docker-compose logs -f [service_name]
```

### Health Monitoring

Endpoint: `GET /api/v1/health`

Returns:
- Database connectivity status
- Redis connectivity status
- Disk space usage
- System uptime

## Performance Optimization

### Caching Strategy

1. **Redis Caching** - Session data, frequently accessed data
2. **HTTP Caching** - Static assets (1 year expiry)
3. **Opcache** - PHP opcode caching enabled

### Database Optimization

1. **Indexing** - All foreign keys indexed
2. **Query Optimization** - Use EXPLAIN for slow queries
3. **Connection Pooling** - PDO persistent connections

## Disaster Recovery

### Backup Strategy

1. **Database Backups** - Daily automated backups
2. **Code Backups** - Git version control
3. **Configuration Backups** - .env files backed up separately

### Recovery Procedures

**Complete System Failure:**
1. Provision new server
2. Install Docker and dependencies
3. Clone repository
4. Restore latest database backup
5. Configure environment variables
6. Start Docker containers

**Data Corruption:**
1. Stop application
2. Restore database from latest backup
3. Verify data integrity
4. Restart application

## Maintenance Tasks

### Daily
- Monitor error logs
- Check disk space
- Verify backups completed

### Weekly
- Review application performance
- Update dependencies (security patches)
- Test backup restoration

### Monthly
- Security audit
- Performance review
- Capacity planning

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker-compose logs [service]

# Rebuild container
docker-compose build --no-cache [service]
docker-compose up -d [service]
```

### Database Connection Issues

```bash
# Test MySQL connection
docker-compose exec db mysql -u root -p

# Check MySQL logs
docker-compose logs db
```

### High Memory Usage

```bash
# Check container resource usage
docker stats

# Restart specific service
docker-compose restart [service]
```

## Production Checklist

Before going live:

- [ ] SSL certificate installed and auto-renewal configured
- [ ] Firewall rules configured
- [ ] Database backups automated and tested
- [ ] Environment variables configured (.env)
- [ ] Debug mode disabled (APP_DEBUG=false)
- [ ] Error logging configured
- [ ] Health monitoring endpoint tested
- [ ] Load testing completed
- [ ] Security audit completed
- [ ] Documentation updated
- [ ] Rollback procedure tested
- [ ] Monitoring and alerting configured

## Resources

- Docker Documentation: https://docs.docker.com/
- Docker Compose: https://docs.docker.com/compose/
- GitHub Actions: https://docs.github.com/en/actions
- Let's Encrypt: https://letsencrypt.org/
- Nginx: https://nginx.org/en/docs/
