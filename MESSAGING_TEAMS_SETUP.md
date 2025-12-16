# Teams Messaging Notifications - Setup Guide

## Problem

New messages in vendor conversations were not triggering Teams notifications. The error in the logs showed:
```
No webhook URL configured for 'message_accounting' notification type
```

## Root Cause

The Teams webhook configuration table had been created, but the notification types for messaging (`message_accounting` and `message_buyer`) were never added to the database. Without these entries, the TeamsService couldn't send notifications when vendors messaged the accounting or buyer teams.

## Solution

### 1. Database Setup (Already Completed)

The following has been executed:

```php
// Created table
CREATE TABLE IF NOT EXISTS `teams_webhook_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notification_type` VARCHAR(100) NOT NULL UNIQUE,
    `webhook_url` LONGTEXT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

// Added entries
INSERT INTO `teams_webhook_config` (notification_type, webhook_url, is_active) VALUES
('po_vendor_update', '', 0),
('invoice_submitted', '', 0),
('message_accounting', '', 0),
('message_buyer', '', 0);
```

### 2. Admin Configuration Required

Now an admin must configure the Teams webhook URLs:

#### Option A: Via Admin Dashboard (Recommended)
1. Login as admin to the portal
2. Go to **Admin Dashboard** → **Microsoft Teams Webhooks**
3. For each notification type:
   - Click **Configure** or **Edit**
   - Paste your Microsoft Teams webhook URL
   - Test the webhook
   - Enable the notification type
   - Click **Save**

#### Option B: Via Database
If the admin interface isn't accessible, webhooks can be updated directly:

```sql
-- Update Accounting Messages webhook
UPDATE teams_webhook_config 
SET webhook_url = 'https://outlook.webhook.office.com/webhookb2/...',
    is_active = 1 
WHERE notification_type = 'message_accounting';

-- Update Buyer Messages webhook
UPDATE teams_webhook_config 
SET webhook_url = 'https://outlook.webhook.office.com/webhookb2/...',
    is_active = 1 
WHERE notification_type = 'message_buyer';
```

### 3. Create Teams Incoming Webhooks

For each notification type, you need to create a corresponding Microsoft Teams Incoming Webhook:

1. **For message_accounting channel:**
   - Go to your Teams workspace
   - Create or select a channel (e.g., "Accounting Messages")
   - Click **...** → **Connectors** → **Incoming Webhook**
   - Click **Configure** and name it "Laguna Partners - Accounting Messages"
   - Copy the webhook URL
   - Paste into admin panel under `message_accounting`

2. **For message_buyer channel:**
   - Create or select a channel (e.g., "Buyer Messages")
   - Click **...** → **Connectors** → **Incoming Webhook**
   - Click **Configure** and name it "Laguna Partners - Buyer Messages"
   - Copy the webhook URL
   - Paste into admin panel under `message_buyer`

## How It Works Now

When a vendor sends a message:

1. The message is created in the `messages` table
2. `MessagingService::sendMessage()` calls `notifyNewMessage()`
3. `notifyNewMessage()` calls `TeamsService::sendNewMessage()`
4. `TeamsService::sendNewMessage()` determines the webhook type:
   - `vendor_to_accounting` → sends to `message_accounting` webhook
   - `vendor_to_buyer` → sends to `message_buyer` webhook
5. A formatted Teams card is sent to the corresponding channel

## Notification Format

When a message is received, Teams shows:

```
[CARD]
New Accounting Message / New Buyer Message
From: [Vendor Company Name]

Vendor: [Company Name]
Time: [MM/DD/YYYY HH:MM:SS]

Message:
[First 200 characters of message...]

[Button: View Conversation]
```

## Verification

To verify everything is working:

1. **Check Teams Log:**
   ```
   logs/teams-2025-11-20.log (current date)
   ```
   Should see:
   ```
   [timestamp] Sending Teams notification for type: message_accounting
   [timestamp] Teams notification sent successfully (HTTP 200) for type: message_accounting
   ```

2. **Test Send:**
   - Vendor: Login and send a message to accounting/buyer team
   - Admin: Check Teams channel for notification
   - Click "View Conversation" button to verify link works

3. **Troubleshooting:**
   - If no notification appears, check logs for errors
   - Verify webhook URL is correct and still valid
   - Check webhook is enabled in admin panel
   - Verify `is_active = 1` in database

## Files Modified/Created

- `database/add_teams_webhook_config.sql` - Updated with INSERT statements
- `database/setup_teams_webhooks.php` - New migration script
- `TEAMS_QUICK_START.md` - Updated with messaging webhook instructions
- `MESSAGING_TEAMS_SETUP.md` - This file

## Next Steps

1. **Admin:** Go to Teams Webhook Configuration in admin panel
2. **Admin:** Configure webhooks for `message_accounting` and `message_buyer`
3. **Admin:** Test each webhook URL
4. **Vendor:** Test by creating a conversation and sending a message
5. **Verify:** Check that Teams notifications appear in configured channels
