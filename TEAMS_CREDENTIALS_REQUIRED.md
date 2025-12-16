# Microsoft Teams Notification - Credentials & Configuration Required

## Overview

This document specifies all credentials, configuration values, and permissions needed to set up the Microsoft Teams notification feature.

## Required Credentials

### 1. Microsoft Teams Webhook URLs (Required)

**What it is:** Unique URLs that allow the Laguna Partners Portal to post messages to specific Teams channels.

**Where to get it:**
1. In Microsoft Teams, go to a channel
2. Click **...** (More options) → **Connectors**
3. Search for and select **"Incoming Webhook"**
4. Click **Configure**
5. Enter a name and click **Create**
6. **Copy the webhook URL** (it will be long, starting with `https://outlook.webhook.office.com/...`)

**Format:**
```
https://outlook.webhook.office.com/webhookb2/[GUID1]@[GUID2]/IncomingWebhook/[LONG_ENCODED_KEY]
```

**Example:**
```
https://outlook.webhook.office.com/webhookb2/12ab34cd-5678-9012-3456-789012345678@11223344-5566-7788-99aa-bbccddeeff00/IncomingWebhook/MjQ4OTMyODI5MzI4QzQ4N0MyMzQ1NjcxMTEyMzQ1Ng==
```

**Needed For:**
- `po_vendor_update` notification type (1 URL required)
- `invoice_submitted` notification type (1 URL required)
- Can use same channel for both or different channels

**Security Level:** **HIGHLY SENSITIVE**
- Treat like a password
- Provides direct access to Teams channels
- Keep in secure location
- Rotate periodically for security

### 2. Database Credentials (Required)

**What it is:** MySQL database access to apply schema migration.

**Who needs it:** System administrator deploying the feature

**Required permissions:**
- CREATE TABLE
- INSERT INTO existing tables
- SELECT from existing tables

**Typical credentials format:**
```
Host: localhost or database server hostname
Port: 3306 (default)
Username: root or database admin user
Password: database password
Database: laguna_partner (or your database name)
```

**Used for:**
- Creating `teams_webhook_config` table
- Storing webhook URLs
- Storing configuration state (active/inactive)

## Configuration Requirements

### 1. Microsoft Teams Setup

**Prerequisites:**
- [ ] Active Microsoft Teams account
- [ ] At least one team with access
- [ ] At least one channel per notification type (or shared channel)
- [ ] Ability to add connectors to channels

**Access Needed:**
- [ ] Can create/modify channel connectors
- [ ] Can view channel ID (optional)

### 2. Laguna Partners Portal

**Prerequisites:**
- [ ] Portal is running and accessible
- [ ] Admin user account exists
- [ ] Vendors have active accounts
- [ ] Purchase orders exist in system
- [ ] Invoices feature is implemented

**Admin Access Required:**
- [ ] Can access `/admin/teams-webhook.php`
- [ ] Can configure system settings
- [ ] Can test webhooks

### 3. Environment Configuration (Optional)

**File:** `.env` (in project root)

**Optional variables to add:**
```env
# Base URL for generating links in Teams messages
# If not set, defaults to current domain
APP_BASE_URL=https://your-domain.com/laguna_partner

# Teams notifications enabled (defaults to true)
TEAMS_NOTIFICATIONS_ENABLED=true
```

**Database Configuration (should already exist):**
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=laguna_partner
DB_USER=root
DB_PASS=yourpassword
```

## Step-by-Step Credential Collection

### Before Starting Setup

Create a spreadsheet or document with this information:

| Item | Value | Status |
|------|-------|--------|
| PO Updates Webhook URL | `[paste URL here]` | [ ] Obtained |
| Invoice Updates Webhook URL | `[paste URL here]` | [ ] Obtained |
| Database Host | localhost | [ ] Verified |
| Database Port | 3306 | [ ] Verified |
| Database Name | laguna_partner | [ ] Verified |
| Database User | root | [ ] Verified |
| Database Password | `[paste here]` | [ ] Verified |
| Admin Username | admin | [ ] Verified |
| APP_BASE_URL | `https://...` | [ ] Verified |

### Gathering Microsoft Teams Webhooks

**PO Vendor Update Webhook:**

1. [ ] Create Teams channel: "_PO Updates_" (or use existing)
2. [ ] Generate webhook in channel
3. [ ] Name it: "Laguna Partners - PO Updates"
4. [ ] Copy URL: `https://outlook.webhook.office.com/...`
5. [ ] Paste above in table
6. [ ] **KEEP THIS SECRET** - don't share via email/chat

**Invoice Submission Webhook:**

1. [ ] Create Teams channel: "_Invoices_" (or use existing)
2. [ ] Generate webhook in channel
3. [ ] Name it: "Laguna Partners - Invoices"
4. [ ] Copy URL: `https://outlook.webhook.office.com/...`
5. [ ] Paste above in table
6. [ ] **KEEP THIS SECRET** - don't share via email/chat

### Verifying Database Access

**Test MySQL connection:**

```bash
# Command line
mysql -h localhost -u root -p

# When prompted, enter password
# Then run:
USE laguna_partner;
SELECT COUNT(*) FROM users;
```

If successful, connection is working.

### Verifying Admin Access

1. [ ] Login to portal as admin user
2. [ ] Verify you can access admin dashboard
3. [ ] Verify you can access `/admin/teams-webhook.php` (should be empty initially)

## Validation Checklist

Before proceeding with setup, verify:

**Credentials Available:**
- [ ] PO webhook URL obtained from Teams
- [ ] Invoice webhook URL obtained from Teams
- [ ] Database credentials verified
- [ ] Admin user can login

**System Requirements Met:**
- [ ] MySQL database is running
- [ ] PHP 8.1+ is installed
- [ ] cURL extension is enabled (already used by system)
- [ ] Database character set is utf8mb4

**Access Verified:**
- [ ] Can connect to database
- [ ] Can login as admin
- [ ] Can access admin dashboard

## Credential Storage

### Safe Storage Recommendations

1. **Webhook URLs:**
   - Store in secure password manager (LastPass, 1Password, etc.)
   - Store in encrypted team wiki or documentation
   - **Never** commit to git repository
   - **Never** send via unencrypted email
   - **Never** share in chat/Slack

2. **Database Credentials:**
   - Store in secure location
   - Already in `.env` file (which is in .gitignore)
   - Use environment-specific `.env` files

3. **Admin Credentials:**
   - Use strong passwords
   - Store in secure password manager
   - Change default credentials after setup

## Rotation Schedule

### Webhook URLs (Recommended Quarterly)

1. Create new webhook in Teams
2. Copy new URL
3. Update in admin panel
4. Test new webhook
5. Delete old connector from Teams

**Why rotate?** Improves security, limits damage if URL is leaked

### Database Credentials (Recommended Annually)

1. Create new database user
2. Grant permissions
3. Update `.env` file
4. Update credentials in Docker/deployment
5. Remove old user

## Troubleshooting Credential Issues

### "Webhook test fails with HTTP 401"
- [ ] Verify webhook URL copied completely
- [ ] Check URL doesn't have extra spaces
- [ ] Regenerate webhook in Teams
- [ ] Copy new URL

### "Webhook test fails with HTTP 404"
- [ ] Connector may have been deleted in Teams
- [ ] Create new connector
- [ ] Copy new webhook URL

### "Cannot connect to database"
- [ ] Verify host is correct (localhost vs IP address)
- [ ] Verify port is correct (usually 3306)
- [ ] Verify database name exists
- [ ] Verify username/password are correct
- [ ] Check if MySQL service is running

### "Admin page shows error"
- [ ] Verify you're logged in as admin
- [ ] Check database connection
- [ ] Check PHP error logs
- [ ] Verify tables were created

## Security Best Practices

### 1. Webhook URL Security
```
DO:
- Keep URLs secret
- Store in secure password manager
- Use HTTPS only
- Rotate periodically

DON'T:
- Commit to version control
- Send via email/chat
- Share with non-admins
- Leave exposed in code comments
```

### 2. Database Security
```
DO:
- Use strong passwords
- Store credentials in .env (not in code)
- Limit user permissions
- Audit access logs

DON'T:
- Use default credentials
- Commit credentials to git
- Share database access widely
- Use simple passwords
```

### 3. Admin Access Control
```
DO:
- Limit admin user count
- Use strong admin passwords
- Require HTTPS login
- Monitor admin activity

DON'T:
- Share admin credentials
- Use shared admin accounts
- Allow weak passwords
- Disable authentication
```

## Post-Setup Verification

After setup is complete:

1. [ ] Test webhook URLs work
2. [ ] Verify notifications appear in Teams
3. [ ] Verify links in messages work
4. [ ] Check logs for errors
5. [ ] Confirm disabled webhooks don't send

## Support Contacts

For issues with:

**Microsoft Teams:**
- Support: https://support.microsoft.com/teams
- Webhook docs: https://docs.microsoft.com/connectors/teams

**Laguna Partners Portal:**
- Admin: [Your admin email]
- Tech support: [Your support email]

## Compliance Notes

- [ ] Webhook URLs classified as sensitive credentials
- [ ] Access logged and auditable
- [ ] Database changes tracked
- [ ] All configuration changes include user/timestamp
- [ ] Supports audit requirements

---

**Prepared By:** _______________  
**Date:** _______________  
**Reviewed By:** _______________  
**Approved By:** _______________
