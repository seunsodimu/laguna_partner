# Laguna Partners Portal - Quick Reference

## 🚀 Quick Start Commands

### Installation
```bash
# Install dependencies
composer install --no-dev

# Copy environment file
cp .env.example .env

# Import database
mysql -u root -p laguna_partner < database/schema.sql
```

### Windows Setup (XAMPP)
```powershell
# Run automated setup
.\setup.ps1
```

### Docker Setup
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f
```

---

## 🔄 Sync Commands

### Manual Sync
```bash
# Sync accounts and users from NetSuite
php scripts/sync-accounts.php

# Sync purchase orders from NetSuite
php scripts/sync-purchase-orders.php

# Sync items from NetSuite
php scripts/sync-items.php

# Clean expired OTP codes
php scripts/cleanup-otp.php
```

### Via Admin Dashboard
1. Login as admin
2. Go to Admin Dashboard
3. Click sync buttons

---

## 🔀 Environment Switching

### Switch NetSuite Environment
```bash
# Switch to sandbox (safe for testing)
php scripts/switch-environment.php sandbox

# Switch to production (live data - requires confirmation)
php scripts/switch-environment.php production

# Check current environment
php scripts/switch-environment.php
```

### Via Admin Dashboard
1. Login as admin
2. Go to Admin Dashboard
3. Click **NetSuite Environment** button
4. Select environment and confirm

### After Switching
Always run full sync after switching environments:
```bash
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php
php scripts/sync-items.php
```

---

## 🔐 Default Credentials

### Admin Login
- **Email:** admin@lagunatools.com
- **Type:** Admin
- **Login:** Use OTP (check email)

### Database (XAMPP Default)
- **Host:** localhost
- **Port:** 3306
- **Database:** laguna_partner
- **User:** root
- **Password:** (empty)

---

## 📁 Important Files

### Configuration
- `.env` - Environment variables
- `config/config.php` - Application settings
- `config/credentials.php` - API credentials

### Database
- `database/schema.sql` - Database schema

### Logs
- `logs/app-YYYY-MM-DD.log` - Application logs
- `logs/sync-YYYY-MM-DD.log` - Sync logs

### Uploads
- `uploads/` - Uploaded documents

---

## 🌐 URLs

### Local Development (XAMPP)
- **Application:** http://localhost/laguna_partner/public
- **phpMyAdmin:** http://localhost/phpmyadmin

### Docker
- **Application:** http://localhost:8080
- **phpMyAdmin:** http://localhost:8081

---

## 👥 User Types

| Type | Access | Features |
|------|--------|----------|
| **Admin** | Full system access | User management, manual syncs, view all data |
| **Buyer** | All POs | View/edit all POs, approve vendor changes, sync to NetSuite |
| **Vendor** | Own POs only | View open POs, edit dates, upload docs, add comments |
| **Dealer** | Items only | View items, subscribe to notifications |

---

## 📧 Email Configuration

### Brevo (Recommended)
```env
EMAIL_PROVIDER=brevo
BREVO_API_KEY=your_api_key
BREVO_FROM_EMAIL=noreply@lagunatools.com
BREVO_FROM_NAME="Laguna Partners Portal"
```

### Amazon SES
```env
EMAIL_PROVIDER=ses
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_SES_REGION=us-east-1
SES_FROM_EMAIL=noreply@lagunatools.com
SES_FROM_NAME="Laguna Partners Portal"
```

---

## 🔧 NetSuite Configuration

### Required Fields in NetSuite

**Vendors:**
- `custentity_portalaccess` = "T" (checked)
- Email fields: `email`, `custentityap_email_1`, `custentity2nd_email_address`, `custentityap_email_3`, `custentityap_email_2`

**Dealers (Customers):**
- `isPerson` = "F" (company, not individual)
- `custentity_portalaccess` = "T" (checked)
- Email fields: same as vendors

**Purchase Orders:**
- Status: B (Pending Receipt), E (Partially Received), F (Pending Billing), H (Pending Bill/Partially Received)

**Items:**
- All inventory items are synced

### .env Configuration
```env
NETSUITE_ACCOUNT_ID=your_account_id
NETSUITE_CONSUMER_KEY=your_consumer_key
NETSUITE_CONSUMER_SECRET=your_consumer_secret
NETSUITE_TOKEN_ID=your_token_id
NETSUITE_TOKEN_SECRET=your_token_secret
NETSUITE_BASE_URL=https://your_account_id.suitetalk.api.netsuite.com
```

---

## 🗄️ Database Quick Queries

### Check User Accounts
```sql
SELECT u.email, u.type, a.company_name, a.type as account_type
FROM users u
JOIN user_accounts ua ON u.id = ua.user_id
JOIN accounts a ON ua.account_id = a.id
WHERE u.is_active = 1;
```

### View Recent Syncs
```sql
SELECT sync_type, status, records_processed, started_at, completed_at
FROM sync_logs
ORDER BY started_at DESC
LIMIT 10;
```

### Check OTP Codes
```sql
SELECT email, user_type, code, expires_at, is_used
FROM otp_codes
WHERE expires_at > NOW()
ORDER BY created_at DESC;
```

### View User Activity
```sql
SELECT u.email, ul.action, ul.details, ul.ip_address, ul.created_at
FROM user_logs ul
JOIN users u ON ul.user_id = u.id
ORDER BY ul.created_at DESC
LIMIT 20;
```

### Change Admin Email
```sql
UPDATE users 
SET email = 'newemail@lagunatools.com' 
WHERE type = 'admin' AND email = 'admin@lagunatools.com';
```

---

## 🐛 Troubleshooting

### Issue: Class not found
```bash
composer dump-autoload
```

### Issue: Database connection failed
```bash
# Check MySQL is running
mysql -u root -p

# Verify credentials in .env
cat .env | grep DB_
```

### Issue: Permission denied
```bash
# Linux/Mac
chmod -R 755 uploads logs cache
chown -R www-data:www-data uploads logs cache

# Windows (as Administrator)
icacls uploads /grant Users:F /T
icacls logs /grant Users:F /T
```

### Issue: .htaccess not working
```bash
# Enable mod_rewrite
a2enmod rewrite

# Restart Apache
service apache2 restart
```

### Issue: Emails not sending
1. Check email provider credentials in `.env`
2. Test with simple email first
3. Check logs in `logs/` directory
4. Verify API key is active

### Issue: NetSuite sync fails
1. Verify OAuth credentials in `.env`
2. Check NetSuite integration is enabled
3. Verify token permissions
4. Check sync logs for specific errors

---

## 📝 Common Tasks

### Add New Admin User
```sql
INSERT INTO users (email, type, first_name, last_name, is_active)
VALUES ('newadmin@lagunatools.com', 'admin', 'First', 'Last', 1);
```

### Deactivate User
```sql
UPDATE users SET is_active = 0 WHERE email = 'user@example.com';
```

### View All Purchase Orders
```sql
SELECT po.tranid, a.company_name, po.total, po.status, po.created_date
FROM purchase_orders po
JOIN accounts a ON po.vendor_id = a.id
ORDER BY po.created_date DESC;
```

### Check Item Notifications
```sql
SELECT u.email, i.item_name, i.sku, n.notification_type, n.threshold
FROM item_notifications n
JOIN users u ON n.user_id = u.id
JOIN items i ON n.item_id = i.id
WHERE n.is_active = 1;
```

### Clear All OTP Codes
```sql
DELETE FROM otp_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

---

## 🔄 Cron Jobs

### Linux/Mac
```bash
# Edit crontab
crontab -e

# Add these lines
0 2 * * * cd /path/to/laguna_partner && php scripts/sync-accounts.php
0 */4 * * * cd /path/to/laguna_partner && php scripts/sync-purchase-orders.php
0 */6 * * * cd /path/to/laguna_partner && php scripts/sync-items.php
0 3 * * * cd /path/to/laguna_partner && php scripts/cleanup-otp.php
```

### Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (daily, every 4 hours, etc.)
4. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\laguna_partner\scripts\sync-accounts.php`
   - Start in: `C:\xampp\htdocs\laguna_partner`

---

## 🔒 Security Checklist

Before production:
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Set `SESSION_SECURE=true` in `.env` (HTTPS only)
- [ ] Change default admin email
- [ ] Install SSL certificate
- [ ] Configure firewall rules
- [ ] Set proper file permissions
- [ ] Enable database backups
- [ ] Review and update email templates
- [ ] Test all user workflows
- [ ] Set up monitoring/alerts

---

## 📚 Documentation

- **README.md** - Comprehensive overview
- **DEPLOYMENT.md** - Deployment guides
- **QUICKSTART.md** - 5-minute setup
- **SETUP_GUIDE.md** - Detailed setup instructions
- **PROJECT_STATUS.md** - Feature checklist
- **FINAL_STATUS.md** - Completion report
- **QUICK_REFERENCE.md** - This document

---

## 🆘 Support

### Check Logs
```bash
# Application logs
tail -f logs/app-$(date +%Y-%m-%d).log

# Sync logs
tail -f logs/sync-$(date +%Y-%m-%d).log

# Apache error logs (XAMPP)
tail -f C:/xampp/apache/logs/error.log
```

### Test Components

**Test Database:**
```bash
php -r "require 'vendor/autoload.php'; require 'config/config.php'; echo 'DB OK';"
```

**Test NetSuite:**
```bash
php -r "require 'vendor/autoload.php'; require 'src/NetSuiteClient.php'; \$c = new NetSuiteClient(); var_dump(\$c->query('SELECT id FROM vendor LIMIT 1'));"
```

**Test Email:**
```bash
php -r "require 'vendor/autoload.php'; require 'src/EmailService.php'; \$e = new EmailService(); var_dump(\$e->sendOTP('test@example.com', '123456'));"
```

---

## 📞 Contact

For issues or questions:
1. Check documentation files
2. Review logs in `logs/` directory
3. Check PROJECT_STATUS.md for known limitations
4. Contact Laguna Tools IT support

---

**Version:** 1.0.0  
**Last Updated:** January 2025  
**Status:** Production Ready ✅