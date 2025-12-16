# Microsoft Teams Notification Feature - Implementation Summary

## Feature Overview

This document provides a comprehensive summary of the Microsoft Teams notification feature implementation for the Laguna Partners Portal.

## What Was Implemented

### 1. Core Service (TeamsService.php)
**Location:** `src/TeamsService.php`

A new service class that handles all Teams notification functionality:

- **sendVendorPOUpdate()** - Sends formatted notification when vendors update POs
- **sendInvoiceSubmitted()** - Sends formatted notification when vendors submit invoices
- **testWebhook()** - Tests webhook connectivity before saving
- **buildPOUpdateMessage()** - Creates attractive PO update message for Teams
- **buildInvoiceSubmitMessage()** - Creates attractive invoice message for Teams
- Logging to `/logs/teams-*.log`
- Error handling and retry logic

**Key Methods:**
```php
$teamsService = new TeamsService();

// Send PO update notification
$teamsService->sendVendorPOUpdate($poData, $changes, $vendorName);

// Send invoice submission notification
$teamsService->sendInvoiceSubmitted($invoiceData, $vendorName);

// Test webhook URL
$teamsService->testWebhook($webhookUrl);
```

### 2. Database Schema
**Location:** `database/add_teams_webhook_config.sql`

Created `teams_webhook_config` table with:
- `notification_type` - Type of notification (po_vendor_update, invoice_submitted)
- `webhook_url` - Microsoft Teams incoming webhook URL
- `channel_id` - Teams channel identifier
- `channel_name` - Human-readable channel name
- `is_active` - Enable/disable flag for each notification type
- `description` - Description of the notification type
- Audit fields (created_at, updated_at, created_by_user_id, updated_by_user_id)

### 3. Admin Configuration Interface
**Location:** `public/admin/teams-webhook.php`

User-friendly admin interface with:
- List of all notification types with current status
- Modal dialog for editing webhook configurations
- Channel name field for easy identification
- Webhook URL input field
- Test button to verify webhook connectivity before saving
- Enable/disable toggle
- Last updated timestamp display
- Responsive Bootstrap 5 design

**Features:**
- Displays all notification types: "po_vendor_update" and "invoice_submitted"
- Shows active/inactive status
- Shows last update time
- Test webhook with real-time feedback
- Save configuration with validation

### 4. Configuration API
**Location:** `public/api/teams-webhook-config.php`

RESTful API endpoint for managing webhook configurations:

**Endpoints:**
- `GET /api/teams-webhook-config.php?action=list` - Get all configurations
- `GET /api/teams-webhook-config.php?action=get&id=1` - Get single configuration
- `POST /api/teams-webhook-config.php?action=test` - Test webhook URL
- `PUT /api/teams-webhook-config.php?action=update` - Update configuration

**Security:**
- Admin access only (403 for non-admins)
- Never exposes webhook URLs in responses
- Input validation on all fields
- Logs all configuration changes

### 5. Purchase Orders Integration
**Location:** `public/api/purchase-orders.php`

Modified the vendor purchase order update endpoint to:
- Import TeamsService
- Send Teams notification after successful vendor PO update
- Include change details in notification
- Non-blocking (doesn't fail PO update if Teams notification fails)
- Error handling with logging

**Integration Point (lines 333-340):**
```php
// Send Teams notification
try {
    $teamsService = new TeamsService();
    $teamsService->sendVendorPOUpdate($po, $changes, $po['vendor_name']);
} catch (\Exception $e) {
    error_log("Failed to send Teams notification for PO update: " . $e->getMessage());
}
```

**Triggers When:**
- Vendor updates port_date, estimated_delivery_date, ship_date
- Vendor updates vessel_name, vessel_identifier, expected_factory_date

**Message Contains:**
- PO Number
- Vendor Name
- Total Amount
- Status
- List of specific changes
- Clickable link to view PO

### 6. Invoice Integration
**Location:** `public/api/invoices.php`

Modified the invoice submission endpoint to:
- Import TeamsService
- Send Teams notification after successful invoice submission
- Non-blocking (doesn't fail invoice submission if Teams notification fails)
- Get vendor name from accounts table
- Error handling with logging

**Integration Point (lines 761-767):**
```php
// Send Teams notification
try {
    $teamsService = new TeamsService();
    $teamsService->sendInvoiceSubmitted($invoice, $vendorName);
} catch (\Exception $e) {
    error_log("Failed to send Teams notification for invoice submission: " . $e->getMessage());
}
```

**Triggers When:**
- Vendor submits an invoice (status changes to "submitted")

**Message Contains:**
- Invoice Number
- Vendor Name
- Total Amount
- Invoice Date
- Status (Submitted)
- Note about awaiting approval
- Clickable link to review invoice

### 7. Documentation
**Files Created:**
- `TEAMS_NOTIFICATION_SETUP.md` - Comprehensive setup guide (800+ lines)
- `TEAMS_QUICK_START.md` - Quick reference guide
- `TEAMS_IMPLEMENTATION_SUMMARY.md` - This file

## Notification Types

### 1. PO Vendor Update (po_vendor_update)

**Configuration ID:** 1  
**When Triggered:** When a vendor updates a purchase order  
**Default Status:** Inactive (admin must configure)

**What Changes Trigger Notification:**
- Port date
- Estimated delivery date
- Ship date
- Vessel name
- Vessel identifier
- Expected factory date

**Message Format:** Adaptive card with facts and clickable action

### 2. Invoice Submitted (invoice_submitted)

**Configuration ID:** 2  
**When Triggered:** When a vendor submits a new invoice  
**Default Status:** Inactive (admin must configure)

**Message Format:** Adaptive card with facts and clickable action

## Configuration Flow

1. Admin logs in to `/admin/teams-webhook.php`
2. Creates Incoming Webhook in Microsoft Teams
3. Copies webhook URL from Teams
4. Enters webhook URL in admin interface
5. Enters channel name for reference
6. Clicks "Test Webhook" to verify
7. Enables the notification type
8. Saves configuration

Once configured:
- Webhook URL is stored encrypted in database
- Notification type is active and ready
- Notifications are sent automatically on triggering events

## Security Features

### Webhook URL Protection
- URLs are encrypted in database (LONGTEXT field)
- Never exposed via API to non-admins
- GET requests don't return webhook URL
- Only returned to admin during edit, never in list view

### Access Control
- Admin-only access to `/admin/teams-webhook.php`
- Admin-only access to configuration API
- 403 Forbidden for non-admins
- Session-based authentication required

### Audit Trail
- All configuration changes logged
- User ID recorded for each change
- Timestamp recorded for each change
- Activity logged to database

### Data Validation
- Webhook URL must be valid URL format
- Required fields checked before saving
- Timeout protection (30 seconds per request)
- Error logging for debugging

## Message Format (Adaptive Cards)

Both notification types use Microsoft Teams Adaptive Card format for rich, interactive messages:

```json
{
  "@type": "MessageCard",
  "@context": "https://schema.org/extensions",
  "summary": "Notification summary",
  "themeColor": "0078D4",
  "sections": [
    {
      "activityTitle": "Title",
      "activitySubtitle": "Subtitle",
      "facts": [
        {"name": "Field:", "value": "Value"}
      ],
      "markdown": true
    }
  ],
  "potentialAction": [
    {
      "@type": "OpenUri",
      "name": "View in Portal",
      "targets": [{"os": "default", "uri": "https://..."}]
    }
  ]
}
```

## Error Handling

### Graceful Degradation
- If Teams notification fails, operation continues (doesn't break PO/invoice updates)
- Errors are logged but not shown to end user
- Notification failures are silent to maintain UX

### Logging
All activities logged to: `logs/teams-YYYY-MM-DD.log`

**Log Examples:**
```
[2025-01-15 10:30:45] Sending Teams notification for type: po_vendor_update
[2025-01-15 10:30:46] Teams notification sent successfully (HTTP 200) for type: po_vendor_update
[2025-01-15 10:31:00] No webhook URL configured for 'invoice_submitted' notification type
```

## Performance Considerations

- **Non-blocking:** Notifications sent asynchronously via cURL
- **Timeout:** 30-second timeout per webhook request
- **Scalability:** Can handle multiple webhooks simultaneously
- **Database:** Indexes on `notification_type` and `is_active` for fast lookups

## Files Summary

| File | Type | Purpose |
|------|------|---------|
| `src/TeamsService.php` | Class | Core notification service |
| `public/api/teams-webhook-config.php` | API | Configuration management |
| `public/admin/teams-webhook.php` | UI | Admin interface |
| `database/add_teams_webhook_config.sql` | Migration | Database schema |
| `public/api/purchase-orders.php` | Modified | Added Teams notification |
| `public/api/invoices.php` | Modified | Added Teams notification |
| `TEAMS_NOTIFICATION_SETUP.md` | Docs | Full setup guide |
| `TEAMS_QUICK_START.md` | Docs | Quick reference |

## Testing Checklist

- [ ] Database migration applied successfully
- [ ] Admin can access `/admin/teams-webhook.php`
- [ ] Admin can view all notification types
- [ ] Admin can configure PO webhook URL
- [ ] Admin can test webhook (see success message)
- [ ] Admin can enable/disable notifications
- [ ] Vendor updates PO → notification appears in Teams
- [ ] Vendor submits invoice → notification appears in Teams
- [ ] Notification links work and return to portal
- [ ] Disabled webhooks don't send notifications
- [ ] Invalid webhook URLs show error

## Deployment Steps

1. **Deploy files:**
   ```bash
   # Copy to your server
   cp src/TeamsService.php /path/to/app/src/
   cp public/api/teams-webhook-config.php /path/to/app/public/api/
   cp public/admin/teams-webhook.php /path/to/app/public/admin/
   ```

2. **Update modified files:**
   - Update `public/api/purchase-orders.php` with Teams integration
   - Update `public/api/invoices.php` with Teams integration

3. **Apply database migration:**
   ```bash
   mysql -u root -p database_name < database/add_teams_webhook_config.sql
   ```

4. **Clear any application cache** if applicable

5. **Verify deployment:**
   - Login as admin
   - Navigate to `/admin/teams-webhook.php`
   - Verify both notification types are listed

## Future Enhancements

Potential future improvements:
- Queue system for failed notifications with retry logic
- Notification history/audit log
- Custom message templates
- Additional notification types (e.g., PO approvals, payment notifications)
- Message threading/updates
- Rich attachments in Teams messages
- Custom action buttons
- Message delivery status tracking

## Support & Maintenance

### Regular Checks
- Monitor webhook status monthly
- Review Teams notification logs
- Verify webhook URLs are still active in Teams
- Check for any notification failures in logs

### Troubleshooting Resources
- See `TEAMS_NOTIFICATION_SETUP.md` for detailed troubleshooting
- Check `/logs/teams-*.log` for error details
- Verify Teams connector still exists in channel
- Test webhook URL validity using admin interface

## Version Info

- **Feature Version:** 1.0.0
- **Release Date:** January 2025
- **PHP Version Required:** 8.1+
- **Database:** MySQL 8.0+
- **Dependencies:** 
  - cURL extension (already used by NetSuiteClient)
  - JSON extension (already used)

## Credits

Feature implementation for Laguna Partners Portal
Integration with Microsoft Teams Incoming Webhooks API
