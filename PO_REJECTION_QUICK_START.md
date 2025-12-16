# PO Rejection Feature - Quick Start Guide

## What's New
Vendors can now **reject purchase orders** with a required explanation message. The buyer receives both **email** and **Teams** notifications.

## Quick Implementation Checklist

### ✅ Code Changes (Already Implemented)
- [x] API endpoint for rejection: `POST /api/purchase-orders.php`
- [x] Vendor dashboard UI with reject button and modal
- [x] Email notification to buyer
- [x] Teams webhook notification to PO channel
- [x] Activity logging

### ⚙️ Database Setup (Required on First Deploy)

**Run migrations:**
```bash
# 1. Add rejection columns (if using automation)
php database/add_po_rejection.php

# OR manually execute:
mysql> ALTER TABLE purchase_orders 
       ADD COLUMN rejection_reason LONGTEXT AFTER expected_factory_date,
       ADD COLUMN rejected_at TIMESTAMP NULL AFTER rejection_reason;
```

**Add email template:**
```bash
# Execute SQL file or run manually:
mysql> INSERT INTO email_templates (name, subject, body, variables) 
       SELECT 'po_rejection', 'Purchase Order {{po_number}} Rejected', 
              '...email body html...', '...'
```

**Configure Teams (optional):**
If using Teams, ensure `po_vendor_update` webhook is configured:
1. Admin Panel → Teams Webhook Configuration
2. Notification Type = `po_vendor_update`
3. Paste your PO channel webhook URL
4. Mark as Active

(PO rejections use the same webhook as vendor PO updates)

## Files Modified

| File | Changes |
|------|---------|
| `database/schema.sql` | Added rejection_reason, rejected_at columns |
| `public/api/purchase-orders.php` | Added reject_po action handler |
| `public/vendor/dashboard.php` | Added rejection modal and UI buttons |
| `src/EmailService.php` | Added sendPORejection() method |
| `src/TeamsService.php` | Added sendPORejection() method |

## New Files Created

| File | Purpose |
|------|---------|
| `database/add_po_rejection.php` | Migration script for DB columns |
| `database/add_po_rejection_template.sql` | Email template SQL |
| `database/add_teams_webhook_config.sql` | Teams config table SQL |
| `PO_REJECTION_FEATURE.md` | Detailed documentation |

## API Usage

**Reject a Purchase Order:**
```bash
curl -X POST http://localhost/api/purchase-orders.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "reject_po",
    "po_id": 123,
    "rejection_reason": "Cannot fulfill order due to supply constraints"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Purchase order rejected successfully"
}
```

## User Experience

### Vendor Workflow
1. Click "Reject PO" button on open purchase order
2. Modal appears requesting rejection reason
3. Enter reason (required field)
4. Click "Reject PO" to confirm
5. PO is marked as rejected
6. Buyer receives notification

### What Buyer Sees
- Email with rejection details and reason
- Teams channel notification (if configured)
- Activity log entry
- PO can be re-issued if needed

## Validation Rules

✅ **Vendor can reject if:**
- User is a vendor
- PO belongs to their account
- PO status is "Pending Received" (B) or "Partially Received" (E)
- Rejection reason is provided and not empty

❌ **Cannot reject:**
- If already billed or closed
- If not the vendor assigned to PO
- Without a rejection message

## Testing Checklist

Before going live, test:
- [ ] Vendor sees "Reject PO" button on open POs only
- [ ] Cannot reject without entering reason
- [ ] Rejection saves successfully
- [ ] Email sent to buyer
- [ ] Teams notification appears (if configured)
- [ ] Activity log records rejection
- [ ] PO details show rejection info
- [ ] Buyer can view rejection reason

## Troubleshooting

**"Rejection reason is required" error:**
- Ensure textarea value is not empty
- Check for whitespace-only entries

**Email not sent:**
- Verify email provider configured (Brevo/SES)
- Check email template exists in database
- Review logs in `/logs/email-*.log`

**Teams notification not sent:**
- Verify Teams webhook configured in admin
- Check notification type is `po_rejection`
- Review logs in `/logs/teams-*.log`

**Cannot see Reject button:**
- Only shows for POs in status B or E
- Check user is logged in as vendor
- PO must belong to active account

## Support

For issues or questions, refer to:
- Detailed docs: `PO_REJECTION_FEATURE.md`
- Email logs: `logs/email-*.log`
- Teams logs: `logs/teams-*.log`
- Activity log: Database activity_log table
