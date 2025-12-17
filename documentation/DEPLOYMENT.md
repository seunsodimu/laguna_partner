# Laguna Partners Portal - Deployment Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Local Development Setup](#local-development-setup)
3. [Docker Deployment](#docker-deployment)
4. [AWS EC2 Deployment](#aws-ec2-deployment)
5. [Post-Deployment Configuration](#post-deployment-configuration)
6. [Monitoring and Maintenance](#monitoring-and-maintenance)

---

## Prerequisites

### Required Software
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer
- Docker & Docker Compose (for containerized deployment)
- Git

### Required Accounts
- NetSuite account with API access
- Brevo account (for email) OR Amazon SES
- AWS account (for EC2 deployment)

---

## Local Development Setup

### 1. Clone the Repository
```bash
git clone <repository-url>
cd laguna_partner
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your configuration:
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=laguna_partner
DB_USER=root
DB_PASS=

# NetSuite API
NETSUITE_ACCOUNT_ID=your_account_id
NETSUITE_CONSUMER_KEY=your_consumer_key
NETSUITE_CONSUMER_SECRET=your_consumer_secret
NETSUITE_TOKEN_ID=your_token_id
NETSUITE_TOKEN_SECRET=your_token_secret

# Email Provider (Brevo)
EMAIL_PROVIDER=brevo
BREVO_API_KEY=your_brevo_api_key

# OR Amazon SES
# EMAIL_PROVIDER=ses
# AWS_SES_KEY=your_aws_key
# AWS_SES_SECRET=your_aws_secret
# AWS_SES_REGION=us-east-1

# Application
APP_URL=http://localhost
APP_DEBUG=true
```

### 4. Setup Database
```bash
mysql -u root -p < database/schema.sql
```

Or import via phpMyAdmin.

### 5. Create Required Directories
```bash
mkdir -p uploads/po_documents
mkdir -p logs
chmod -R 777 uploads
chmod -R 777 logs
```

### 6. Run Initial Sync
```bash
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php
php scripts/sync-items.php
```

### 7. Start Development Server
```bash
cd public
php -S localhost:8000
```

Access at: http://localhost:8000

---

## Docker Deployment

### 1. Configure Environment
Ensure `.env` file is properly configured (see above).

### 2. Build and Start Containers
```bash
docker-compose up -d --build
```

This will start:
- **app**: PHP/Apache application (port 8080)
- **db**: MySQL database (port 3306)
- **phpmyadmin**: Database management (port 8081)

### 3. Access the Application
- Application: http://localhost:8080
- phpMyAdmin: http://localhost:8081

### 4. View Logs
```bash
# Application logs
docker-compose logs -f app

# Database logs
docker-compose logs -f db

# All logs
docker-compose logs -f
```

### 5. Run Commands Inside Container
```bash
# Access container shell
docker-compose exec app bash

# Run sync scripts
docker-compose exec app php scripts/sync-accounts.php
docker-compose exec app php scripts/sync-purchase-orders.php
docker-compose exec app php scripts/sync-items.php
```

### 6. Stop Containers
```bash
docker-compose down

# Stop and remove volumes (WARNING: deletes database)
docker-compose down -v
```

---

## AWS EC2 Deployment

### 1. Launch EC2 Instance

**Recommended Specifications:**
- Instance Type: t3.medium (2 vCPU, 4 GB RAM)
- OS: Ubuntu 22.04 LTS
- Storage: 30 GB SSD
- Security Group: Allow ports 22 (SSH), 80 (HTTP), 443 (HTTPS)

### 2. Connect to EC2 Instance
```bash
ssh -i your-key.pem ubuntu@your-ec2-ip
```

### 3. Install Docker
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Add user to docker group
sudo usermod -aG docker ubuntu
newgrp docker
```

### 4. Clone Repository
```bash
cd /home/ubuntu
git clone <repository-url> laguna_partner
cd laguna_partner
```

### 5. Configure Environment
```bash
cp .env.example .env
nano .env
```

Update with production values:
```env
APP_URL=https://your-domain.com
APP_DEBUG=false
DB_HOST=db
DB_ROOT_PASSWORD=strong_root_password
DB_PASS=strong_user_password
```

### 6. Deploy with Docker
```bash
docker-compose up -d --build
```

### 7. Setup SSL with Let's Encrypt (Optional but Recommended)

Install Certbot:
```bash
sudo apt install certbot python3-certbot-apache -y
```

Get SSL certificate:
```bash
sudo certbot --apache -d your-domain.com
```

### 8. Configure Firewall
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## Post-Deployment Configuration

### 1. Verify Database Connection
```bash
docker-compose exec app php -r "require 'vendor/autoload.php'; require 'src/Database.php'; use LagunaPartners\Database; \$db = Database::getInstance(); echo 'Database connected successfully!';"
```

### 2. Test NetSuite Connection
```bash
docker-compose exec app php scripts/sync-accounts.php
```

### 3. Test Email Service
Login to the portal and request an OTP. Verify email delivery.

### 4. Setup Cron Jobs (Non-Docker Deployment)

If not using Docker, setup cron jobs manually:
```bash
crontab -e
```

Add:
```cron
0 2 * * * cd /path/to/laguna_partner && php scripts/sync-accounts.php >> logs/cron.log 2>&1
0 */4 * * * cd /path/to/laguna_partner && php scripts/sync-purchase-orders.php >> logs/cron.log 2>&1
0 */6 * * * cd /path/to/laguna_partner && php scripts/sync-items.php >> logs/cron.log 2>&1
0 3 * * * cd /path/to/laguna_partner && php scripts/cleanup-otp.php >> logs/cron.log 2>&1
```

### 5. Create Admin User

Login with default admin credentials:
- Email: admin@lagunatools.com
- Password: Generate OTP via email

**IMPORTANT**: Change admin email in database after first login:
```sql
UPDATE users SET email = 'your-admin@email.com' WHERE type = 'admin';
```

---

## Monitoring and Maintenance

### Application Logs
```bash
# View application logs
tail -f logs/app-$(date +%Y-%m-%d).log

# View cron logs
tail -f logs/cron.log

# View sync logs
docker-compose exec db mysql -u root -p -e "USE laguna_partner; SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 10;"
```

### Database Backup
```bash
# Backup database
docker-compose exec db mysqldump -u root -p laguna_partner > backup_$(date +%Y%m%d).sql

# Restore database
docker-compose exec -T db mysql -u root -p laguna_partner < backup_20240101.sql
```

### Update Application
```bash
# Pull latest changes
git pull origin main

# Rebuild containers
docker-compose down
docker-compose up -d --build

# Run migrations if any
docker-compose exec app php scripts/migrate.php
```

### Monitor Disk Space
```bash
df -h
du -sh /home/ubuntu/laguna_partner/uploads
du -sh /home/ubuntu/laguna_partner/logs
```

### Clean Old Logs
```bash
# Delete logs older than 30 days
find logs/ -name "*.log" -mtime +30 -delete
```

### Monitor Container Health
```bash
docker-compose ps
docker stats
```

---

## Troubleshooting

### Issue: Cannot connect to database
**Solution**: Check database credentials in `.env` and ensure database container is running.

### Issue: NetSuite API errors
**Solution**: Verify NetSuite credentials and ensure API access is enabled in NetSuite.

### Issue: Email not sending
**Solution**: Check email provider credentials and ensure API keys are valid.

### Issue: Permission denied errors
**Solution**: 
```bash
sudo chown -R www-data:www-data uploads logs
sudo chmod -R 777 uploads logs
```

### Issue: Cron jobs not running
**Solution**: Check cron logs and ensure cron service is running:
```bash
docker-compose exec app service cron status
```

---

## Security Checklist

- [ ] Change default admin email
- [ ] Use strong database passwords
- [ ] Enable HTTPS with SSL certificate
- [ ] Set `APP_DEBUG=false` in production
- [ ] Restrict database access to localhost only
- [ ] Setup firewall rules
- [ ] Regular security updates
- [ ] Monitor access logs
- [ ] Backup database regularly
- [ ] Use environment variables for sensitive data

---

## Support

For issues or questions, contact the development team or refer to the main README.md file.