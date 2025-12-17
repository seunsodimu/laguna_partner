# Microsoft Teams Notification - Setup Checklist

Use this checklist to ensure proper installation and configuration of the Teams notification feature.

## Pre-Installation Requirements

- [ ] Microsoft Teams account with at least one team and channel
- [ ] Admin access to Laguna Partners Portal
- [ ] Admin access to Microsoft Teams channel
- [ ] Database root/admin access
- [ ] File system access to upload PHP files

## Step 1: Deploy New Files

- [ ] Copy `src/TeamsService.php` to your server
- [ ] Copy `public/api/teams-webhook-config.php` to your server
- [ ] Copy `public/admin/teams-webhook.php` to your server
- [ ] Copy `database/add_teams_webhook_config.sql` to your server (optional, for reference)

## Step 2: Update Existing Files

- [ ] Update `public/api/purchase-orders.php`:
  - [ ] Add `require_once __DIR__ . '/../../src/TeamsService.php';`
  - [ ] Add `use LagunaPartners\TeamsService;`
  - [ ] Add Teams notification code in handlePut() function (after email notification)
  
- [ ] Update `public/api/invoices.php`:
  - [ ] Add `require_once '../../src/TeamsService.php';`
  - [ ] Add `use LagunaPartners\TeamsService;`
  - [ ] Add Teams notification code in submitInvoice() function

**Verify:** Check that both files import TeamsService and have integration code

## Step 3: Database Migration

Choose one of the following methods:

### Method 1: Using MySQL Command Line
```bash
mysql -u your_db_user -p your_db_name < database/add_teams_webhook_config.sql
```

### Method 2: Using PHPMyAdmin
1. [ ] Open PHPMyAdmin
2. [ ] Select your database
3. [ ] Click "SQL" tab
4. [ ] Copy and paste the SQL from `database/add_teams_webhook_config.sql`
5. [ ] Click "Go"

### Method 3: Direct SQL Execution
1. [ ] Connect to MySQL as root/admin
2. [ ] Run the CREATE TABLE statement
3. [ ] Run the INSERT IGNORE statements

**Verify Migration:**
```sql
SELECT * FROM teams_webhook_config;
```
Should return 2 rows (po_vendor_update and invoice_submitted)

## Step 4: Create Microsoft Teams Webhooks

### For PO Vendor Updates Channel

1. [ ] Open Microsoft Teams
2. [ ] Create or select a channel for PO updates (e.g., "PO Updates")
3. [ ] Click the **...** (More options) button in channel header
4. [ ] Select **Connectors**
5. [ ] Search for **Incoming Webhook**
6. [ ] Click **Configure**
7. [ ] Enter name: "Laguna Partners - PO Updates"
8. [ ] Optionally upload image
9. [ ] Click **Create**
10. [ ] **COPY THE WEBHOOK URL** and save it somewhere safe
11. [ ] Click **Done**

**Verify:** URL should start with `https://outlook.webhook.office.com/...`

### For Invoice Submission Channel

1. [ ] Create or select a channel for invoices (e.g., "Invoices")
2. [ ] Repeat steps 3-11 above
3. [ ] Enter name: "Laguna Partners - Invoices"
4. [ ] **COPY THIS WEBHOOK URL** separately

**Important:** Keep these URLs secure and secret

## Step 5: Admin Configuration

1. [ ] Login to Portal as Admin user
2. [ ] Navigate to **Admin Dashboard** (or direct URL: `/admin/teams-webhook.php`)
3. [ ] You should see a page with two notification types

### Configure PO Vendor Update Webhook

1. [ ] Click **Configure** button next to "po_vendor_update"
2. [ ] Modal dialog opens
3. [ ] Channel Name field - enter: "PO Updates"
4. [ ] Channel ID field - leave empty (optional)
5. [ ] Webhook URL field - paste the **PO Updates webhook URL** from Step 4
6. [ ] Check box "Enable this webhook"
7. [ ] Click **Test Webhook** button
   - [ ] Should show "Success! Webhook test successful"
   - [ ] Check Teams channel - you should see a test message
8. [ ] Click **Save Changes**
9. [ ] Verify webhook now shows as "Active"

### Configure Invoice Submission Webhook

1. [ ] Click **Configure** button next to "invoice_submitted"
2. [ ] Modal dialog opens
3. [ ] Channel Name field - enter: "Invoices"
4. [ ] Channel ID field - leave empty (optional)
5. [ ] Webhook URL field - paste the **Invoices webhook URL** from Step 4
6. [ ] Check box "Enable this webhook"
7. [ ] Click **Test Webhook** button
   - [ ] Should show success message
   - [ ] Check Teams channel - you should see a test message
8. [ ] Click **Save Changes**
9. [ ] Verify webhook now shows as "Active"

## Step 6: Verification & Testing

### Test 1: Admin Interface Access

- [ ] Admin can access `/admin/teams-webhook.php`
- [ ] Both notification types are displayed
- [ ] Webhook status shows "Active" for configured ones
- [ ] Channel names are displayed correctly

### Test 2: Database Verification

Run this SQL query to verify configuration:
```sql
SELECT * FROM teams_webhook_config WHERE is_active = 1;
```

Expected result: 2 rows, both with `is_active = 1`

### Test 3: PO Vendor Update Notification

1. [ ] Login as a Vendor user
2. [ ] Navigate to a Purchase Order they can edit
3. [ ] Change the "Estimated Delivery Date" to tomorrow
4. [ ] Save changes
5. [ ] **Check Microsoft Teams channel** - should see notification within 5 seconds
6. [ ] Verify notification includes:
   - [ ] PO Number
   - [ ] Vendor Name
   - [ ] Total Amount
   - [ ] The specific change (date change)
   - [ ] "View PO" button that links to portal

### Test 4: Invoice Submission Notification

1. [ ] Login as a Vendor user
2. [ ] Create a new invoice draft
3. [ ] Add at least one line item
4. [ ] Submit the invoice
5. [ ] **Check Microsoft Teams channel** - should see notification within 5 seconds
6. [ ] Verify notification includes:
   - [ ] Invoice Number
   - [ ] Vendor Name
   - [ ] Total Amount
   - [ ] Invoice Date
   - [ ] Status: "Submitted"
   - [ ] "Review Invoice" button that links to portal

### Test 5: Error Handling

1. [ ] Disable a webhook in admin panel (uncheck "Enable")
2. [ ] Try to trigger that notification type
3. [ ] Verify notification does NOT appear in Teams
4. [ ] Verify operation still succeeds (PO/invoice update works)
5. [ ] Re-enable webhook
6. [ ] Try again - notification should appear

## Step 7: Monitoring & Logs

- [ ] Verify log file exists: `logs/teams-YYYY-MM-DD.log`
- [ ] Check log file contains entries
- [ ] Example log entry should look like:
  ```
  [2025-01-15 10:30:46] Teams notification sent successfully (HTTP 200)
  ```

## Step 8: Documentation

- [ ] Team members understand the feature
- [ ] Admins know how to configure webhooks
- [ ] Vendors understand what triggers notifications
- [ ] Buyers know where to find notifications

### Share with Team

- [ ] Link to `TEAMS_QUICK_START.md` - quick reference
- [ ] Link to `TEAMS_NOTIFICATION_SETUP.md` - full documentation
- [ ] Share admin dashboard URL: `/admin/teams-webhook.php`

## Step 9: Production Deployment (if applicable)

- [ ] All tests pass in staging/development
- [ ] Database backup created
- [ ] Maintenance window scheduled (if needed)
- [ ] Team notified of deployment
- [ ] Webhook URLs verified still active
- [ ] Post-deployment verification complete

## Step 10: Ongoing Maintenance

- [ ] Monitor Teams channels for notifications
- [ ] Weekly: Check that notifications are being delivered
- [ ] Monthly: Review `teams-*.log` files for errors
- [ ] Quarterly: Verify webhook URLs are still active
- [ ] As needed: Rotate webhook URLs for security

## Troubleshooting Reference

| Issue | Solution |
|-------|----------|
| "Admin page shows no webhooks" | Run database migration |
| "Webhook test fails (HTTP 401)" | Webhook URL expired - regenerate in Teams |
| "Webhook test fails (HTTP 404)" | Webhook connector deleted - create new one |
| "No notifications appear in Teams" | Check: webhook is enabled, URL is correct, logs for errors |
| "Cannot access admin page" | Verify: you're logged in as admin, not buyer/vendor/dealer |
| "Notifications appear but links broken" | Set APP_BASE_URL in `.env` or config |

## Rollback Instructions (if needed)

If you need to revert the feature:

1. Remove Teams webhook URLs from database:
   ```sql
   UPDATE teams_webhook_config SET webhook_url = '' WHERE 1=1;
   UPDATE teams_webhook_config SET is_active = 0 WHERE 1=1;
   ```

2. Or remove the integration code from:
   - `public/api/purchase-orders.php` (lines 333-340)
   - `public/api/invoices.php` (lines 761-767)

3. Optionally delete new files:
   - `src/TeamsService.php`
   - `public/api/teams-webhook-config.php`
   - `public/admin/teams-webhook.php`

## Success Criteria

- [ ] Both notification types configured
- [ ] Test messages received in Teams channels
- [ ] Admin can manage webhooks from admin panel
- [ ] Vendor PO updates trigger Teams notifications
- [ ] Vendor invoice submissions trigger Teams notifications
- [ ] Links in Teams messages work
- [ ] Disabled webhooks don't send notifications
- [ ] Logs show successful deliveries
- [ ] No errors in application logs

## Support Resources

- **Quick Start:** `TEAMS_QUICK_START.md`
- **Full Documentation:** `TEAMS_NOTIFICATION_SETUP.md`
- **Implementation Details:** `TEAMS_IMPLEMENTATION_SUMMARY.md`
- **Logs:** `logs/teams-*.log`

## Questions or Issues?

1. Check the troubleshooting section in `TEAMS_NOTIFICATION_SETUP.md`
2. Review logs at `/logs/teams-*.log`
3. Verify Teams connector still exists in channel
4. Test webhook URL using admin interface test button
5. Verify database table exists: `teams_webhook_config`

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Verification Complete:** _______________
