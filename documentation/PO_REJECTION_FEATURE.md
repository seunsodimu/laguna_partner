# Purchase Order Rejection Feature

## Overview
This feature allows vendors to reject purchase orders with a required message that gets sent to the buyer via email and Teams notification.

## Changes Made

### 1. Database Schema Updates

**File**: `database/schema.sql`
- Added `rejection_reason` column (LONGTEXT) to store the vendor's rejection message
- Added `rejected_at` column (TIMESTAMP) to track when the PO was rejected

**Migration Script**: `database/add_po_rejection.php`
- Automatically adds the new columns to the purchase_orders table if they don't exist

**Run on deployment:**
```bash
php database/add_po_rejection.php
```

### 2. API Endpoint

**File**: `public/api/purchase-orders.php`
- New action: `reject_po`
- **Method**: POST
- **Authentication**: Vendor only
- **Required Fields**:
  - `po_id`: The purchase order ID to reject
  - `rejection_reason`: The reason for rejection (required, non-empty)

**Request Example**:
```json
{
  "action": "reject_po",
  "po_id": 123,
  "rejection_reason": "We cannot fulfill this order due to supply constraints."
}
```

**Response**:
- Success: `{"success": true, "message": "Purchase order rejected successfully"}`
- Error: `{"success": false, "message": "Error description"}`

### 3. Vendor Dashboard Updates

**File**: `public/vendor/dashboard.php`

#### New Components:
1. **Reject Button**: Red "Reject PO" button appears when viewing PO details (only for editable POs with status B or E)
2. **Rejection Modal**: Modal dialog for entering rejection reason
   - Field: `rejectionReason` textarea (required)
   - Validates that reason is not empty
   - Shows confirmation before rejecting

#### New JavaScript Functions:
- `showRejectModal(poId)`: Opens the rejection modal
- `submitRejection()`: Submits the rejection request to the API

### 4. Email Notification

**File**: `src/EmailService.php`
- New method: `sendPORejection($buyerEmail, $poData, $rejectionReason)`
- Sends email to buyer with rejection details

**Email Template**: `po_rejection`
- Subject: "Purchase Order {{po_number}} Rejected"
- Variables available:
  - `{{po_number}}`: The PO number
  - `{{vendor_name}}`: Vendor company name
  - `{{total_amount}}`: PO total amount
  - `{{rejection_reason}}`: Reason for rejection
  - `{{rejected_date}}`: Date/time of rejection
  - `{{portal_link}}`: Link to view PO details

**SQL Migration**: `database/add_po_rejection_template.sql`

### 5. Teams Notification

**File**: `src/TeamsService.php`
- New method: `sendPORejection($poData, $rejectionReason)`
- Sends formatted notification to Teams PO Rejection channel
- Message includes:
  - PO Number
  - Vendor Name
  - Total Amount
  - Rejection Timestamp
  - Full Rejection Reason
  - Link to view PO

**Notification Type**: Uses existing `po_vendor_update` webhook
- Sends to the same PO channel as vendor PO updates
- Color: Red (#E81123) indicating rejection/error
- No additional configuration needed if po_vendor_update is already configured

## Deployment Steps

### Step 1: Database Schema Updates
```bash
# Add rejection columns to purchase_orders table
php database/add_po_rejection.php
```

### Step 2: Add Email Template
Execute the SQL migration to add the email template:
```sql
-- From database/add_po_rejection_template.sql
INSERT INTO email_templates (name, subject, body, variables) VALUES (...)
```

### Step 3: Code Deployment
Deploy the following files:
- `public/api/purchase-orders.php`
- `public/vendor/dashboard.php`
- `src/EmailService.php`
- `src/TeamsService.php`
- `database/schema.sql` (reference only)

## User Workflow

### Vendor Rejects a PO
1. Vendor logs in and navigates to Purchase Orders
2. Opens a PO in status "Pending Received" or "Partially Received"
3. Clicks "Reject PO" button (red button in PO details)
4. Modal appears with text area for rejection reason
5. Vendor enters reason and clicks "Reject PO"
6. System validates reason is not empty
7. PO is rejected and marked with timestamp

### Notifications Sent
1. **Email**: Buyer receives email with:
   - PO details
   - Vendor name and rejection reason
   - Link to view PO
2. **Teams**: Channel receives notification with:
   - Red-themed message card
   - Full rejection details
   - Link to view PO in portal

### Activity Logging
- Rejection is logged in activity log with:
  - User ID and action type
  - PO ID and transaction ID
  - Full rejection reason

## API Response Codes

| Code | Meaning |
|------|---------|
| 200 | Successful rejection |
| 400 | Missing required fields or empty reason |
| 403 | Access denied (not vendor or wrong PO vendor) |
| 404 | PO not found |
| 500 | Server error during rejection |

## Validation Rules

1. **User Type**: Only vendors can reject POs
2. **Vendor Access**: Can only reject own POs
3. **PO Status**: Can only reject POs in status 'B' (Pending Received) or 'E' (Partially Received)
4. **Rejection Reason**: Required and must not be empty/whitespace
5. **Unique Rejection**: Cannot reject an already rejected PO (will create new record, replacing previous)

## Database Queries

### Get Rejected POs
```sql
SELECT * FROM purchase_orders 
WHERE rejection_reason IS NOT NULL 
AND rejected_at IS NOT NULL
ORDER BY rejected_at DESC;
```

### Get Recent Rejections
```sql
SELECT po.id, po.tran_id, po.vendor_name, po.total_amount, po.rejection_reason, po.rejected_at
FROM purchase_orders po
WHERE rejected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY po.rejected_at DESC;
```

## Testing

### Manual Testing Checklist
- [ ] Vendor can see "Reject PO" button on open POs
- [ ] Rejection modal appears and accepts text input
- [ ] Cannot reject with empty reason (validation works)
- [ ] Rejection successful message appears
- [ ] PO list refreshes after rejection
- [ ] Email sent to buyer
- [ ] Teams notification sent (if configured)
- [ ] Activity log records rejection
- [ ] Buyer receives rejection details
- [ ] Link in email/Teams goes to correct PO

### Edge Cases
- [ ] Vendor cannot reject closed/billed POs
- [ ] Vendor cannot reject another vendor's POs
- [ ] Multiple rejections don't create duplicate records
- [ ] Special characters in rejection reason are handled
- [ ] Long rejection reasons display properly

## Rollback (if needed)

### Remove Rejection Feature
1. Remove `rejection_reason` and `rejected_at` columns from purchase_orders
2. Remove email template `po_rejection` from database
3. Revert code changes to API and dashboard

```sql
ALTER TABLE purchase_orders DROP COLUMN rejection_reason, DROP COLUMN rejected_at;
DELETE FROM email_templates WHERE name = 'po_rejection';
```

## Future Enhancements

- [ ] Ability to reject POs in other statuses
- [ ] Ability to modify rejection reason after initial rejection
- [ ] Reject with predefined reasons/templates
- [ ] Rejection history/timeline in PO details
- [ ] Buyer ability to respond to rejection
- [ ] Dashboard widget showing rejection stats
