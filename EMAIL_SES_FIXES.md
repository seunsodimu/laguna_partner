# Email Service & SES Configuration Fixes

## Issues Found and Fixed

### 1. **Silent Log File Creation Failures** ❌ → ✅
**Problem**: 
- The `ensureLogDirectory()` and `log()` methods had no error handling
- `mkdir()` and `file_put_contents()` failures were silent
- When logs couldn't be created, there was zero visibility into why emails were failing

**Fixes Applied**:
- Added try-catch blocks around `mkdir()` with detailed error logging to PHP error log
- Added directory writability check in `ensureLogDirectory()`
- Wrapped `file_put_contents()` in try-catch with error reporting
- Used `FILE_APPEND | LOCK_EX` flags for safer file operations
- All failures now reported via `error_log()` for visibility

**Result**: Logs will now be created in `/logs/email-YYYY-MM-DD.log` with proper error messages if creation fails.

---

### 2. **AWS SES Timestamp Issues** ❌ → ✅
**Problem**:
- The SES API request used system timezone instead of UTC for timestamp calculations
- AWS SigV4 signature validation requires UTC time
- Signature mismatch caused SES requests to fail with authentication errors
- Line 306 in makeSESRequest: `$dateStamp = date('Ymd')` (wrong - uses system time)

**Fixes Applied**:
- Changed `date('Ymd')` → `gmdate('Ymd')` for UTC timestamps
- Changed `date('Ymd\THis\Z')` → `gmdate('Ymd\THis\Z')` for UTC time
- Added comment documenting UTC requirement for AWS SigV4
- Reordered assignments to ensure correct credential scope calculation

**Result**: AWS SigV4 signatures will now be calculated correctly, allowing SES authentication to pass.

---

### 3. **SES Source Format Issue** ❌ → ✅
**Problem**:
- AWS SES `Source` field was set to: `"Name <email@domain.com>"`
- AWS SES requires `Source` to be a **verified email address only**
- The format with display name caused SES validation errors
- Environment variables had quotes that weren't stripped

**Fixes Applied**:
- Changed `'Source' => "$fromName <$fromEmail>"` → `'Source' => $fromEmail`
- Added `trim($fromName, '"')` to strip quotes from environment variables
- Added documentation comment explaining AWS SES requirement
- Display name can still be used elsewhere in the email structure if needed

**Result**: SES will now accept the Source parameter without validation errors.

---

### 4. **Inadequate Error Logging for Debugging** ❌ → ✅
**Problem**:
- SES failures didn't log enough information for debugging
- cURL errors were logged minimally
- HTTP response errors didn't show full details
- Exception traces were not captured

**Fixes Applied**:
- Enhanced SES credential check logging to show which credential is missing
- Added region and detailed "From" information to send logs
- Added response body logging on cURL errors
- Changed `catch (\Exception $e)` → `catch (\Throwable $e)` for comprehensive error handling
- Added full stack trace to exception logs: `$e->getTraceAsString()`
- Added explicit success logs with MessageId
- Added HTTP status codes and full error messages in all failure scenarios

**Result**: Email logs will now contain detailed debugging information showing exactly where and why failures occur.

---

## Configuration Checklist

### Environment Variables Required (.env)

```ini
# Email Provider Selection
EMAIL_PROVIDER=ses                    # Options: 'brevo' or 'ses'

# AWS SES Configuration (if using SES)
AWS_ACCESS_KEY_ID=AKIA...            # AWS IAM Access Key
AWS_SECRET_ACCESS_KEY=...            # AWS IAM Secret Key
AWS_SES_REGION=us-east-2             # AWS Region where SES is verified
SES_FROM_EMAIL=noreply@domain.com    # MUST be SES verified address
SES_FROM_NAME="Your App Name"        # Display name for emails

# Notification Settings (used by all providers)
NOTIFICATION_FROM_EMAIL=...          # Used by app config
NOTIFICATION_FROM_NAME=...           # Used by app config
```

### AWS SES Prerequisites

1. **Verify Email Address**: The `SES_FROM_EMAIL` must be verified in AWS SES console
2. **Production Access**: Default sandbox allows 1 email per second. Request production access in SES console
3. **Check Region**: Ensure credentials are for the correct region specified in `AWS_SES_REGION`
4. **IAM Permissions**: User/Role needs `ses:SendEmail` permission
5. **Sandbox Mode Limitations**:
   - Can only send to verified addresses
   - Limited to 1 email/second rate
   - No attachments support

---

## Verification Steps

### 1. Run Diagnostic Script
```bash
php scripts/verify-email-config.php
```

This will check:
- Email provider configuration
- Credentials presence and format
- Log directory existence and permissions
- cURL and hash function availability
- Timezone settings (important for SigV4)
- Log file creation capability

### 2. Check Log Files
After sending a test email:
```bash
# View email logs
tail -f logs/email-YYYY-MM-DD.log
```

Expected log output:
```
[2024-01-15 10:30:45] Attempting to send OTP to test@example.com
[2024-01-15 10:30:45] Send method called with provider: ses, to: test@example.com
[2024-01-15 10:30:45] Sending email via AWS SES to test@example.com (Region: us-east-2) with subject: Your OTP Code
[2024-01-15 10:30:45] From: noreply@lagunatools.com, FromName: Laguna Partners Portal
[2024-01-15 10:30:46] SES request successful. MessageId: 0000014d-abcd-1234-abcd-1234abcd5678
[2024-01-15 10:30:46] Email sent successfully via SES to test@example.com (Message ID: 0000014d-abcd-1234-abcd-1234abcd5678)
```

### 3. Check PHP Error Log
If directory or file creation fails:
```bash
# On Docker
docker logs laguna_partner_php
# Or view error log directly
cat logs/php-error.log
```

### 4. Test Email Sending
Run a test through the login flow or create a test script:
```php
<?php
require_once 'vendor/autoload.php';
require_once 'src/EmailService.php';

$email = new \LagunaPartners\EmailService();
$result = $email->sendOTP('your-email@example.com', '123456');
echo $result ? "Email sent" : "Email failed";
?>
```

---

## Common Issues & Solutions

### Issue: "AWS SES credentials not configured"
**Solution**: Check `.env` file has:
- `AWS_ACCESS_KEY_ID` is set
- `AWS_SECRET_ACCESS_KEY` is set
- Values don't have surrounding quotes

### Issue: Emails fall back to Brevo/PHP mail
**Solution**:
1. Run `verify-email-config.php` to check credentials
2. Check if `EMAIL_PROVIDER=ses` in `.env`
3. Verify AWS region matches where SES is set up
4. Check PHP error log for cURL errors

### Issue: "MessageId: unknown"
**Solution**: AWS returned a response but without MessageId:
1. Check SES service status in AWS console
2. Verify credentials have correct IAM permissions
3. Check region setting

### Issue: No log files created
**Solution**:
1. Ensure `/logs` directory is writable: `chmod 755 logs`
2. Check PHP error log for file_put_contents errors
3. Run `verify-email-config.php` to test log creation
4. On Docker, check volume permissions

### Issue: "SignatureDoesNotMatch" from AWS
**Solution**:
- Timezone mismatch (now fixed in code)
- Clock skew between local system and AWS
- Check system time is synchronized: `date` vs AWS time in error

---

## Files Modified

1. **src/EmailService.php**
   - Enhanced error handling in `ensureLogDirectory()`
   - Improved error logging in `log()` method
   - Fixed timezone handling in `makeSESRequest()` (UTC)
   - Changed SES Source format to email-only
   - Enhanced exception handling with full traces
   - Improved all SES operation logging

## New Files

1. **scripts/verify-email-config.php** - Diagnostic tool for email configuration

---

## Testing Recommendations

1. **Unit Test**: Create a test email send through login page
2. **Monitor Logs**: Watch `/logs/email-YYYY-MM-DD.log` for detailed output
3. **AWS Console**: Check SES Send Statistics dashboard
4. **Fallback Test**: Ensure Brevo fallback works if SES fails
5. **Rate Limiting**: Test with batch emails to verify rate limiting

---

## Additional Notes

- **Security**: Credentials are visible in `.env` - consider using AWS Secrets Manager in production
- **Monitoring**: Consider setting up CloudWatch alerts for SES bounce/complaint rates
- **Cron Jobs**: SES logs from cron jobs will be in `/logs/cron.log` and `/logs/email-YYYY-MM-DD.log`
- **Timezone**: Ensure system timezone is set correctly (`date.timezone` in php.ini)
