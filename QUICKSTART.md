# Laguna Partners Portal - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Install Dependencies
```bash
composer install
```

### Step 2: Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your credentials:
```env
# Database
DB_NAME=laguna_partner
DB_USER=root
DB_PASS=

# NetSuite API (get from NetSuite > Setup > Integration > Manage Integrations)
NETSUITE_ACCOUNT_ID=your_account_id
NETSUITE_CONSUMER_KEY=your_consumer_key
NETSUITE_CONSUMER_SECRET=your_consumer_secret
NETSUITE_TOKEN_ID=your_token_id
NETSUITE_TOKEN_SECRET=your_token_secret

# Email (choose one)
EMAIL_PROVIDER=brevo
BREVO_API_KEY=your_api_key
```

### Step 3: Setup Database
```bash
mysql -u root -p
CREATE DATABASE laguna_partner;
USE laguna_partner;
SOURCE database/schema.sql;
EXIT;
```

### Step 4: Create Upload Directories
```bash
mkdir -p uploads/po_documents logs
chmod -R 777 uploads logs
```

### Step 5: Run Initial Sync
```bash
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php
php scripts/sync-items.php
```

### Step 6: Start Server
```bash
cd public
php -S localhost:8000
```

### Step 7: Login
Open http://localhost:8000

**Default Admin Login:**
1. Select "Admin" from dropdown
2. Enter email: `admin@lagunatools.com`
3. Click "Send OTP"
4. Check email for OTP code
5. Enter OTP and login

**Change Admin Email:**
```sql
UPDATE users SET email = 'your-email@company.com' WHERE type = 'admin';
```

---

## 🐳 Docker Quick Start

### One Command Deployment
```bash
docker-compose up -d
```

Access at:
- Application: http://localhost:8080
- phpMyAdmin: http://localhost:8081

### Run Sync Scripts
```bash
docker-compose exec app php scripts/sync-accounts.php
docker-compose exec app php scripts/sync-purchase-orders.php
docker-compose exec app php scripts/sync-items.php
```

---

## 📋 User Types & Access

### Vendor
- View open purchase orders (status B, E)
- Edit port date, delivery date, ship date (when status is B or E)
- Upload documents
- Add comments
- Changes trigger email to assigned buyer

### Dealer
- View all items
- Search by item name or SKU
- Subscribe to notifications:
  - In stock alerts
  - Out of stock alerts
  - Low stock alerts (custom threshold)

### Buyer
- View all purchase orders (all statuses)
- Filter by status, vendor, buyer, dates
- Edit all PO fields
- Approve vendor changes
- Sync approved changes to NetSuite
- Add comments

### Admin
- All buyer permissions
- Manual sync controls
- User management
- View sync logs
- View user activity logs
- Manage email templates

---

## 🔄 Automated Syncs (Cron Jobs)

Add to crontab:
```cron
# Sync accounts daily at 2 AM
0 2 * * * cd /path/to/laguna_partner && php scripts/sync-accounts.php

# Sync POs every 4 hours
0 */4 * * * cd /path/to/laguna_partner && php scripts/sync-purchase-orders.php

# Sync items every 6 hours
0 */6 * * * cd /path/to/laguna_partner && php scripts/sync-items.php

# Cleanup OTP codes daily at 3 AM
0 3 * * * cd /path/to/laguna_partner && php scripts/cleanup-otp.php
```

---

## 🔧 Common Tasks

### View Logs
```bash
tail -f logs/app-$(date +%Y-%m-%d).log
```

### Backup Database
```bash
mysqldump -u root -p laguna_partner > backup.sql
```

### Test NetSuite Connection
```bash
php -r "require 'vendor/autoload.php'; require 'src/NetSuiteClient.php'; \$config = require 'config/config.php'; \$ns = new LagunaPartners\NetSuiteClient(\$config['netsuite']['account_id'], \$config['netsuite']['consumer_key'], \$config['netsuite']['consumer_secret'], \$config['netsuite']['token_id'], \$config['netsuite']['token_secret']); echo 'NetSuite connected!';"
```

### Test Email Service
```bash
php -r "require 'vendor/autoload.php'; require 'src/EmailService.php'; \$email = new LagunaPartners\EmailService(); \$email->sendOTP('test@example.com', '123456'); echo 'Email sent!';"
```

---

## 🆘 Troubleshooting

### "Class not found" errors
```bash
composer dump-autoload
```

### Database connection failed
- Check `.env` database credentials
- Ensure MySQL is running
- Verify database exists

### NetSuite API errors
- Verify credentials in `.env`
- Check NetSuite integration is enabled
- Ensure token has proper permissions

### Email not sending
- Verify email provider credentials
- Check API key is valid
- Test with a simple email first

### Permission denied
```bash
chmod -R 777 uploads logs
```

---

## 📚 Next Steps

- Read full [README.md](README.md) for detailed documentation
- See [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment
- Configure email templates in admin panel
- Setup SSL certificate for production
- Configure firewall rules
- Setup monitoring and alerts

---

## 🎯 Key Features

✅ OTP-based authentication (no passwords)  
✅ Multi-account support for vendors/dealers  
✅ Real-time NetSuite synchronization  
✅ Email notifications for PO updates  
✅ Document upload and management  
✅ Comment/conversation system  
✅ Item notification subscriptions  
✅ Responsive mobile-ready interface  
✅ Comprehensive audit logging  
✅ Docker deployment ready  

---

## 📞 Support

For detailed documentation, see [README.md](README.md)

For deployment instructions, see [DEPLOYMENT.md](DEPLOYMENT.md)