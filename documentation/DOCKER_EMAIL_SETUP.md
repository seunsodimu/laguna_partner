# Docker Email Service Configuration

## Issue Summary

SES emails were not sending and logs were not being created due to:
1. Silent failures in log file operations (no error visibility)
2. Incorrect UTC timezone handling in AWS SigV4 signature generation
3. Invalid SES Source format (display name + email instead of email only)
4. Insufficient error logging for debugging

## Fixed In Latest Build

The following changes have been made to the Docker image:

### 1. EmailService Log Handling
- ✅ Added error handling with `error_log()` output
- ✅ Directory writability validation
- ✅ File creation error reporting
- ✅ Uses `FILE_APPEND | LOCK_EX` for safer file operations

### 2. AWS SigV4 Signature Generation
- ✅ Changed all date functions to `gmdate()` for UTC timestamps
- ✅ Correct timestamp formatting for AWS signature validation

### 3. SES API Request Format
- ✅ Changed `Source` field to email-only format (AWS requirement)
- ✅ Removed display name from Source field
- ✅ Added proper quote handling from environment variables

### 4. Comprehensive Error Logging
- ✅ AWS credentials check with specific missing credential info
- ✅ SES request details (region, from address)
- ✅ cURL error details and response bodies
- ✅ Exception traces with full stack information
- ✅ Success logging with MessageId

## Docker Deployment

### 1. Ensure Logs Directory Permissions

```dockerfile
# In Dockerfile (already configured)
RUN mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs
```

### 2. Verify Volume Mounts

In `docker-compose.yml`:
```yaml
services:
  php:
    volumes:
      - ./logs:/var/www/html/logs  # Ensure this is mounted
```

### 3. Environment Variables in Docker

Ensure `.env` file is copied to container and contains:

```ini
EMAIL_PROVIDER=ses
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_SES_REGION=us-east-2
```

## Cron Job Configuration

The `/docker/crontab` is configured to:

1. **Redirect stdout & stderr** to log files
```bash
0 */4 * * * cd /var/www/html && php scripts/sync-purchase-orders.php >> /var/www/html/logs/cron.log 2>&1
```

2. **Create email logs** from within PHP
```
logs/email-YYYY-MM-DD.log  # Created by EmailService
logs/cron.log              # Created by cron redirects
```

## Testing in Docker

### 1. Check Logs Directory

```bash
# From host machine
docker exec laguna_partner_php ls -la /var/www/html/logs

# Expected output:
# -rw-r--r-- 1 www-data www-data   37 Nov 04 21:17 email-test-2025-11-04-221739.log
# -rw-r--r-- 1 www-data www-data 1234 Nov 04 21:30 cron.log
# -rw-r--r-- 1 www-data www-data  234 Nov 04 21:30 email-2025-11-04.log
```

### 2. Run Diagnostic Script

```bash
docker exec laguna_partner_php php /var/www/html/scripts/verify-email-config.php
```

### 3. Test Email Send

```bash
# Option A: Via web interface (send OTP)
docker exec laguna_partner_php curl http://localhost/admin-buyer-login.php

# Option B: Via CLI
docker exec laguna_partner_php php -r "
require_once 'vendor/autoload.php';
require_once 'src/EmailService.php';
\$email = new \LagunaPartners\EmailService();
\$result = \$email->sendOTP('test@example.com', '123456');
echo \$result ? 'Sent' : 'Failed';
"
```

### 4. View Real-Time Logs

```bash
# Email logs (live)
docker exec laguna_partner_php tail -f /var/www/html/logs/email-2025-11-04.log

# Cron logs (live)  
docker exec laguna_partner_php tail -f /var/www/html/logs/cron.log

# PHP error log (live)
docker exec laguna_partner_php tail -f /var/log/apache2/error.log
```

## Troubleshooting

### Symptom: No email logs appearing

**Diagnosis**:
```bash
docker exec laguna_partner_php ls -la /var/www/html/logs/
```

**Solutions**:
1. Check if `/logs` directory exists and is writable (should be 777)
2. Check PHP error log: `docker logs laguna_partner_php`
3. Run diagnostic: `docker exec laguna_partner_php php scripts/verify-email-config.php`

### Symptom: "MessageId: unknown" in logs

**Diagnosis**:
```bash
docker exec laguna_partner_php tail -f /var/www/html/logs/email-$(date +%Y-%m-%d).log
```

**Solutions**:
1. Check AWS SES is enabled in the region
2. Verify from_email is SES verified
3. Check IAM permissions have `ses:SendEmail`
4. Verify AWS credentials in `.env`

### Symptom: "SignatureDoesNotMatch" from AWS

**Causes**:
- Timezone mismatch (now fixed with gmdate)
- System clock skew (check `docker exec laguna_partner_php date` vs AWS time)

**Solutions**:
1. Verify system time: `docker exec laguna_partner_php date`
2. Check PHP timezone: `docker exec laguna_partner_php php -r "echo date_default_timezone_get();"`
3. Compare with AWS CloudWatch timestamp in error response

## Performance Notes

### Rate Limiting
- AWS SES default: 1 email/second in sandbox, 14 emails/second in production
- Cron jobs are throttled by scheduled intervals
- Each sync job can batch-send multiple emails

### Log Retention
- Email logs: Created daily (email-YYYY-MM-DD.log)
- Cron logs: Appended continuously (cron.log)
- Consider log rotation in production using logrotate

## Docker Build Command

```bash
# Rebuild with latest email service fixes
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Verify
docker exec laguna_partner_php php scripts/verify-email-config.php
```

## Production Checklist

- [ ] AWS SES production access enabled (not sandbox)
- [ ] Multiple email addresses verified in SES console
- [ ] IAM user has proper `ses:SendEmail` permissions
- [ ] `.env` file values for AWS credentials are correct
- [ ] System timezone is set correctly in php.ini
- [ ] Log rotation is configured (logrotate or similar)
- [ ] Email logs are monitored/alerting is set up
- [ ] Backup email provider (Brevo) is configured as fallback
- [ ] Test email sending before going live
- [ ] Monitor CloudWatch SES metrics (bounce rate, complaints, etc.)

## Security Considerations

### Before Production Deployment

1. **Move AWS Credentials**:
   - Remove credentials from `.env` 
   - Use AWS Secrets Manager or similar
   - Inject at runtime in Docker

2. **Log File Access**:
   - Consider encrypting email logs
   - Restrict log directory permissions
   - Don't expose logs in backups

3. **Email Content**:
   - Audit templates for sensitive data leaks
   - Consider PII masking in logs
   - Encrypt email data in database

## Related Documentation

- See `EMAIL_SES_FIXES.md` for complete technical details
- See `docker/crontab` for scheduled email tasks
- See `src/EmailService.php` for implementation
- See `.env.example` for configuration template
