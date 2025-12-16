# Microsoft Teams Notification Setup Guide

## Overview

This guide explains how to configure Microsoft Teams notifications for the Laguna Partners Portal. The system can send notifications to Teams channels for:

1. **Purchase Order Updates** - When a vendor updates a Purchase Order
2. **Invoice Submissions** - When a vendor submits an invoice

## Prerequisites

- **Microsoft Teams Account** with at least one team and channel
- **Admin Access** to the Laguna Partners Portal
- **Webhook URL** from Microsoft Teams (generated using Incoming Webhooks connector)

## Architecture Overview

### Components

1. **TeamsService.php** - Core service class that handles sending notifications to Teams
2. **Database Table** - `teams_webhook_config` stores webhook URLs and configuration
3. **Admin Interface** - Admin page at `/admin/teams-webhook.php` for managing webhooks
4. **API Endpoint** - `/api/teams-webhook-config.php` for configuration management
5. **Integration Points**:
   - Purchase Orders API (`/api/purchase-orders.php`) - sends PO update notifications
   - Invoices API (`/api/invoices.php`) - sends invoice submission notifications

## Setup Instructions

### Step 1: Create Incoming Webhook in Microsoft Teams

1. **Open Microsoft Teams** and navigate to your desired channel
2. Click the **"More options" (...)** button in the channel header
3. Select **"Connectors"**
4. Search for **"Incoming Webhook"**
5. Click **"Configure"**
6. Enter a **Name** for the webhook (e.g., "Laguna Partners Portal")
7. Optionally upload an image for the webhook
8. Click **"Create"**
9. **Copy the webhook URL** - you'll need this in the next steps

**Example Webhook URL Format:**
```
https://outlook.webhook.office.com/webhookb2/[your-team-id]/IncomingWebhook/[webhook-key]
```

### Step 2: Deploy Database Changes

Execute the SQL migration to create the webhook configuration table:

```sql
-- Option 1: Execute the SQL file directly
mysql -u [DB_USER] -p [DB_NAME] < database/add_teams_webhook_config.sql

-- Option 2: Or run these commands via PHPMyAdmin or another MySQL client
CREATE TABLE IF NOT EXISTS `teams_webhook_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notification_type` VARCHAR(100) NOT NULL UNIQUE,
    `webhook_url` LONGTEXT NOT NULL,
    `channel_id` VARCHAR(255),
    `channel_name` VARCHAR(255),
    `is_active` BOOLEAN DEFAULT 1,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by_user_id` INT,
    `updated_by_user_id` INT,
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_is_active` (`is_active`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `teams_webhook_config` (notification_type, description, is_active) VALUES
('po_vendor_update', 'Notification sent when a vendor updates a Purchase Order', 0),
('invoice_submitted', 'Notification sent when a vendor submits an invoice', 0);
```

### Step 3: Configure Webhooks via Admin Interface

1. **Login as Admin** to the Laguna Partners Portal
2. Navigate to **Admin Dashboard** → **Microsoft Teams Webhooks**
   - Direct URL: `/admin/teams-webhook.php`
3. For each notification type:
   - Click **"Configure"** button
   - Enter the **Webhook URL** from Step 1
   - Enter a **Channel Name** (e.g., "PO Updates", "Invoice Submissions")
   - Optionally enter the **Channel ID**
   - Check **"Enable this webhook"** to activate
   - Click **"Test Webhook"** to verify connectivity
   - Click **"Save Changes"**

**Notification Types:**

- **po_vendor_update**: Sent when vendors update purchase order dates, vessel information, or factory dates
- **invoice_submitted**: Sent when vendors submit new invoices for review

### Step 4: Configure Environment Variables (Optional)

Add the following to your `.env` file to customize the Teams notification behavior:

```env
# Microsoft Teams Configuration
TEAMS_NOTIFICATIONS_ENABLED=true
APP_BASE_URL=https://your-domain.com/laguna_partner
```

The `APP_BASE_URL` is used to generate clickable links in Teams messages that point back to the portal.

## Notification Details

### Purchase Order Update Notification

**Triggered when:** A vendor updates a purchase order with one of the following changes:
- Port date
- Estimated delivery date
- Ship date
- Vessel name
- Vessel identifier
- Expected factory date

**Message includes:**
- PO Number
- Vendor Name
- Total Amount
- Current Status
- List of specific changes made
- Clickable link to view the PO in the portal

**Example:**
```
Purchase Order Update
Vendor: ABC Suppliers
- PO Number: PO-2025-001
- Total Amount: $50,000.00
- Status: Pending Received
- Vessel Name: Old value → New value
- Estimated Delivery Date: 2025-01-15 → 2025-01-20

[View PO] button
```

### Invoice Submission Notification

**Triggered when:** A vendor submits a new invoice

**Message includes:**
- Invoice Number
- Vendor Name
- Total Amount
- Invoice Date
- Status (always "Submitted")
- Notification that invoice is awaiting buyer review
- Clickable link to review the invoice in the portal

**Example:**
```
Invoice Submitted
Vendor: ABC Suppliers
- Invoice Number: INV-2025-0001
- Total Amount: $5,000.00
- Invoice Date: 2025-01-15
- Status: Submitted

Invoice is awaiting buyer review and approval.

[Review Invoice] button
```

## API Reference

### GET /api/teams-webhook-config.php?action=list

Retrieve all webhook configurations.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "notification_type": "po_vendor_update",
      "channel_name": "PO Updates",
      "is_active": 1,
      "description": "Notification sent when a vendor updates a Purchase Order",
      "created_at": "2025-01-15 10:00:00",
      "updated_at": "2025-01-15 10:00:00"
    }
  ]
}
```

### PUT /api/teams-webhook-config.php?action=update

Update a webhook configuration.

**Request Body:**
```json
{
  "id": 1,
  "webhook_url": "https://outlook.webhook.office.com/...",
  "channel_name": "PO Updates",
  "channel_id": "19:xxx@thread.tacv2",
  "is_active": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Webhook configuration updated successfully"
}
```

### POST /api/teams-webhook-config.php?action=test

Test a webhook URL to verify it's working.

**Request Body:**
```json
{
  "webhook_url": "https://outlook.webhook.office.com/..."
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Webhook test successful"
}
```

**Response (Failure):**
```json
{
  "success": false,
  "error": "Webhook test failed (HTTP 401): Invalid webhook URL"
}
```

## Troubleshooting

### Issue: Webhook test fails with "HTTP 401"

**Cause:** Invalid or expired webhook URL

**Solution:**
1. Generate a new webhook URL in Microsoft Teams
2. Verify the URL was copied completely (should be very long)
3. Test again with the new URL

### Issue: Webhook test fails with "HTTP 404"

**Cause:** Webhook URL is incorrect or the connector was deleted

**Solution:**
1. Check if the connector still exists in Teams
2. Create a new Incoming Webhook connector
3. Copy and test the new URL

### Issue: Messages not appearing in Teams

**Possible Causes:**
- Webhook is disabled (is_active = 0)
- Webhook URL is not configured
- Database table not created
- Webhook connector was deleted in Teams

**Solutions:**
1. Verify webhook is enabled in admin interface
2. Check Teams channel for connector status
3. Review logs in `/logs/teams-*.log`
4. Run database migration if table doesn't exist

### Issue: Webhook configuration page shows no webhooks

**Cause:** Database table doesn't exist

**Solution:** Run the database migration SQL:
```bash
mysql -u [DB_USER] -p [DB_NAME] < database/add_teams_webhook_config.sql
```

## Logging

Teams notification activity is logged to: `/logs/teams-YYYY-MM-DD.log`

Log entries include:
- Webhook URL retrieval
- Message sending attempts
- HTTP response codes
- Any errors that occur

**Example log entry:**
```
[2025-01-15 10:30:45] Sending Teams notification for type: po_vendor_update
[2025-01-15 10:30:46] Teams notification sent successfully (HTTP 200) for type: po_vendor_update
```

## Security Considerations

### Webhook URL Security

1. **Keep webhook URLs secret** - They provide direct access to your Teams channel
2. **Rotate webhooks periodically** - Delete old webhooks and create new ones in Teams
3. **Monitor access** - Review Teams connector activity for unauthorized access
4. **Use HTTPS only** - All webhook URLs must use HTTPS protocol

### Database Security

1. **Webhook URLs are never exposed** in API responses to non-admin users
2. **All configuration changes are logged** with user ID and timestamp
3. **Only admins can configure webhooks** - enforced at API level

## Performance Considerations

- **Async sending** - Notifications are sent but don't block PO/invoice updates
- **Timeout** - Each webhook request has a 30-second timeout
- **Retry logic** - Failed notifications are logged but not retried (can be added if needed)
- **Scalability** - Can handle multiple webhooks and high notification volume

## Files Modified/Created

### New Files
- `src/TeamsService.php` - Core Teams notification service
- `public/api/teams-webhook-config.php` - Configuration API endpoint
- `public/admin/teams-webhook.php` - Admin configuration interface
- `database/add_teams_webhook_config.sql` - Database migration
- `TEAMS_NOTIFICATION_SETUP.md` - This documentation

### Modified Files
- `public/api/purchase-orders.php` - Added Teams notification on vendor updates
- `public/api/invoices.php` - Added Teams notification on invoice submission

## Version History

- **v1.0.0** - Initial release (Jan 2025)
  - Support for PO vendor update notifications
  - Support for invoice submission notifications
  - Admin configuration interface
  - Webhook testing functionality

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review logs at `/logs/teams-*.log`
3. Verify database migration was applied
4. Ensure webhook URL is still active in Microsoft Teams
