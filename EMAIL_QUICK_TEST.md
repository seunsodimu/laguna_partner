# Quick Email Test Guide

## Immediate Test (No Docker Required)

### 1. Run Configuration Verification
```bash
php scripts/verify-email-config.php
```

**Expected Output**:
- ✓ All credentials marked as "SET"
- ✓ Logs directory is writable
- ✓ Test log file created successfully
- ✓ cURL support enabled
- ✓ SHA256 hash support enabled

### 2. Check Email Logs After Any Operation

```bash
# View today's email logs
tail logs/email-$(date +%Y-%m-%d).log

# Watch real-time
tail -f logs/email-$(date +%Y-%m-%d).log
```

**Expected Log Pattern**:
```
[2025-01-15 10:30:45] Attempting to send OTP to user@example.com
[2025-01-15 10:30:45] Send method called with provider: ses, to: user@example.com
[2025-01-15 10:30:45] Sending email via AWS SES to user@example.com (Region: us-east-2) with subject: Your OTP
[2025-01-15 10:30:46] SES request successful. MessageId: 0000014d-9f5e-0113-aaaa-234bb5c4cccf
[2025-01-15 10:30:46] Email sent successfully via SES to user@example.com
```

---

## What Was Fixed

| Issue | Before | After |
|-------|--------|-------|
| **Log Creation** | Silent failures, no logs | Detailed error reporting |
| **AWS Signatures** | Wrong timezone (local time) | Correct UTC timestamps |
| **SES Source** | `"Name <email@domain>"` | `email@domain` (AWS requirement) |
| **Error Details** | Minimal logging | Full traces and debugging info |

---

## Configuration Validation

### Checklist (.env file)
```bash
# Run this to see current values
grep -E "EMAIL_PROVIDER|AWS_|NOTIFICATION_FROM" .env
```

**Required values**:
- `EMAIL_PROVIDER=ses`
- `AWS_ACCESS_KEY_ID=AKIA...` (20+ chars)
- `AWS_SECRET_ACCESS_KEY=...` (40+ chars)
- `AWS_SES_REGION=us-east-2` (or your region)
- `NOTIFICATION_FROM_EMAIL=noreply@...`
- `NOTIFICATION_FROM_NAME=...`

---

## Testing Email Sending

### Method 1: Trigger via Login Page
1. Open `http://localhost/admin-buyer-login.php`
2. Request OTP for a test email
3. Check logs for delivery confirmation

### Method 2: Direct Script Test
```bash
php -r "
require_once 'vendor/autoload.php';
require_once 'src/EmailService.php';
\$service = new \LagunaPartners\EmailService();
\$result = \$service->sendOTP('your-email@example.com', '123456');
echo \$result ? 'Email sent!' : 'Email failed!';
"
```

### Method 3: Check Cron Job Output
```bash
# View cron job logs
tail logs/cron.log

# Sync jobs include email sending
tail logs/email-$(date +%Y-%m-%d).log
```

---

## Troubleshooting One-Liners

### Are credentials loaded?
```bash
php -r "require_once 'vendor/autoload.php'; echo 'AWS Key: ' . ($_ENV['AWS_ACCESS_KEY_ID'] ?? 'NOT SET');"
```

### Can we write logs?
```bash
php -r "echo is_writable('./logs') ? 'YES' : 'NO';"
```

### What's the current timezone?
```bash
php -r "echo date_default_timezone_get() . ' - ' . date('Y-m-d H:i:s') . ' - UTC: ' . gmdate('Y-m-d H:i:s');"
```

### Test AWS credentials format
```bash
php scripts/verify-email-config.php | grep -A 5 "AWS SES"
```

---

## Common Test Scenarios

### Scenario 1: User requests OTP login
- Opens login page → Requests OTP
- EmailService triggers automatically
- Check: `logs/email-YYYY-MM-DD.log` for delivery status

### Scenario 2: Vendor updates purchase order
- API endpoint called via web interface
- Email sent to buyer automatically (if configured)
- Check: Both activity log AND email log

### Scenario 3: Scheduled cron job
- `sync-purchase-orders.php` runs every 4 hours
- May send multiple emails per run
- Check: `logs/cron.log` for cron execution
- Check: `logs/email-YYYY-MM-DD.log` for email details

---

## Log Location Reference

```
logs/
├── email-2025-11-04.log      ← EmailService (daily rotation)
├── email-2025-11-05.log      
├── cron.log                   ← Cron job output (continuous)
└── app-2025-11-04.log        ← Application errors
```

---

## Success Indicators

✅ All tests passing:
- [ ] Verification script shows all "SET" and "YES"
- [ ] Email logs created successfully in `/logs`
- [ ] SES requests logged with MessageId
- [ ] No "SignatureDoesNotMatch" errors
- [ ] No "Email credentials not configured" fallback messages

---

## Need Help?

### Check These First:
1. `php scripts/verify-email-config.php` - Diagnostic output
2. `tail logs/email-$(date +%Y-%m-%d).log` - Today's email attempts
3. `.env` file - AWS credentials present and not quoted
4. `grep EMAIL_PROVIDER .env` - Should show "ses" not "brevo"

### Review Full Docs:
- `EMAIL_SES_FIXES.md` - Technical details of fixes
- `DOCKER_EMAIL_SETUP.md` - Docker-specific configuration
- `src/EmailService.php` - Implementation details
