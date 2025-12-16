# Microsoft Teams Notification - Quick Start

## Credentials/Setup Required

### 1. Microsoft Teams Incoming Webhook URLs

You need to generate webhook URLs from Microsoft Teams for each notification type:

**For PO Vendor Updates:**
- Navigate to your Teams channel (e.g., "Vendor Updates" channel)
- Click **...** → **Connectors** → **Incoming Webhook**
- Click **Configure** and give it a name
- **Copy the webhook URL**
- Keep this URL secure and secret

**For Invoice Submissions:**
- Repeat the same process for another channel (e.g., "Invoices" channel)
- Or use the same channel if you prefer all notifications there

**For Vendor Messaging to Accounting Team:**
- Navigate to your Teams channel (e.g., "Accounting Messages" channel)
- Click **...** → **Connectors** → **Incoming Webhook**
- Click **Configure** and give it a name (e.g., "Accounting Messages")
- **Copy the webhook URL**

**For Vendor Messaging to Buyer Team:**
- Navigate to your Teams channel (e.g., "Buyer Messages" channel)
- Click **...** → **Connectors** → **Incoming Webhook**
- Click **Configure** and give it a name (e.g., "Buyer Messages")
- **Copy the webhook URL**

### 2. Environment Configuration

No additional `.env` variables are required, but you can optionally add:

```env
# Optional: Used to generate portal links in Teams messages
APP_BASE_URL=https://your-domain.com/laguna_partner
```

If not set, it defaults to `https://localhost`.

### 3. Database Setup

Execute this migration to create the webhook configuration table:

```bash
cd /path/to/laguna_partner
mysql -u root -p laguna_partner < database/add_teams_webhook_config.sql
```

Or run the SQL directly via PHPMyAdmin:
```sql
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
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `teams_webhook_config` (notification_type, description, is_active) VALUES
('po_vendor_update', 'Notification sent when a vendor updates a Purchase Order', 0),
('invoice_submitted', 'Notification sent when a vendor submits an invoice', 0);
```

## Configuration Steps

1. **Login to Admin Panel** as an admin user

2. **Go to Admin Dashboard** and click **Microsoft Teams Webhooks**
   - URL: `/admin/teams-webhook.php`

3. **Configure PO Vendor Updates:**
   - Click **Configure** next to "po_vendor_update"
   - Paste your webhook URL
   - Enter channel name (e.g., "Purchase Orders")
   - Check "Enable this webhook"
   - Click **Test Webhook** to verify it works
   - Click **Save Changes**

4. **Configure Invoice Submissions:**
   - Click **Configure** next to "invoice_submitted"
   - Paste your webhook URL (can be same or different channel)
   - Enter channel name (e.g., "Invoices")
   - Check "Enable this webhook"
   - Click **Test Webhook** to verify
   - Click **Save Changes**

5. **Configure Accounting Team Messages:**
   - Click **Configure** next to "message_accounting"
   - Paste your webhook URL for the accounting messages channel
   - Enter channel name (e.g., "Accounting Messages")
   - Check "Enable this webhook"
   - Click **Test Webhook** to verify
   - Click **Save Changes**

6. **Configure Buyer Team Messages:**
   - Click **Configure** next to "message_buyer"
   - Paste your webhook URL for the buyer messages channel
   - Enter channel name (e.g., "Buyer Messages")
   - Check "Enable this webhook"
   - Click **Test Webhook** to verify
   - Click **Save Changes**

## What Gets Notified

### PO Vendor Update Notifications
Sent to Teams when a **vendor** updates a purchase order with:
- Port date changes
- Estimated delivery date changes
- Ship date changes
- Vessel name/identifier changes
- Expected factory date changes

### Invoice Submission Notifications
Sent to Teams when a **vendor** submits a new invoice

### Accounting Team Message Notifications
Sent to Teams when a **vendor** sends a message to the accounting team, including:
- Sender name and timestamp
- Message preview (first 200 characters)
- Vendor name
- Direct link to view conversation in portal

### Buyer Team Message Notifications
Sent to Teams when a **vendor** sends a message to the buyer team, including:
- Sender name and timestamp
- Message preview (first 200 characters)
- Vendor name
- Direct link to view conversation in portal

## Testing

### Testing PO and Invoice Notifications
1. Have an admin configure the webhooks as above
2. Have a vendor login and make a change to a Purchase Order
3. Within seconds, a notification should appear in your Teams channel
4. Click the **View PO** button in the notification to confirm links work

### Testing Message Notifications
1. Ensure message_accounting and message_buyer webhooks are configured
2. Have a vendor login to `/vendor/messages.php`
3. Create a new conversation (select Accounting Team or Buyer Team)
4. Send a message in the conversation
5. Within seconds, a notification should appear in the corresponding Teams channel
6. Verify the notification includes:
   - Sender name (vendor company name)
   - Message preview
   - Timestamp
   - "View Conversation" button that links to the message thread

## File Locations

| File | Purpose |
|------|---------|
| `src/TeamsService.php` | Core Teams notification service |
| `public/api/teams-webhook-config.php` | API for managing configurations |
| `public/admin/teams-webhook.php` | Admin interface for setup |
| `database/add_teams_webhook_config.sql` | Database migration |
| `logs/teams-*.log` | Teams notification logs |

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Webhook test fails | Check webhook URL is valid and not expired in Teams |
| Messages don't appear in Teams | Verify webhook is enabled in admin panel, check logs |
| 401 error | Regenerate webhook URL in Microsoft Teams |
| 404 error | Webhook connector was deleted, create new one in Teams |

## API Endpoints

```bash
# List all webhooks
GET /api/teams-webhook-config.php?action=list

# Get single webhook
GET /api/teams-webhook-config.php?action=get&id=1

# Test webhook
POST /api/teams-webhook-config.php?action=test
Body: {"webhook_url": "https://..."}

# Update webhook
PUT /api/teams-webhook-config.php?action=update
Body: {
  "id": 1,
  "webhook_url": "https://...",
  "channel_name": "PO Updates",
  "channel_id": "19:xxx@thread.tacv2",
  "is_active": 1
}
```

## Security Notes

- **Webhook URLs are sensitive** - they provide access to Teams channels
- **Only admins can configure** webhook URLs
- **URLs are never exposed** in API responses to non-admin users
- **All changes are logged** with user ID and timestamp
- **Keep webhook URLs secret** and rotate them periodically

## Support

For full details, see: `TEAMS_NOTIFICATION_SETUP.md`
