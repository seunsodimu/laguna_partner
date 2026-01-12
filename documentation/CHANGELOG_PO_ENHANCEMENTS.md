# Purchase Order Enhancements - Changelog

## Overview
This changelog documents comprehensive enhancements made to the Purchase Order system, including new fields, improved UI/UX for document uploads, and editable vendor quantities.

## Changes Summary

### 1. Database Schema Updates

#### New Purchase Order Fields
Three new fields added to `purchase_orders` table for better order tracking:

| Field | Type | NetSuite Field | Purpose |
|-------|------|-----------------|---------|
| `vessel_name` | VARCHAR(255) | custbodyvessel_name | Name of the vessel/carrier for shipment |
| `vessel_identifier` | VARCHAR(100) | custbodyvessel_identifier | Identification code for the vessel |
| `expected_factory_date` | DATE | custbodyexpected_factory_date | Expected date of manufacture/factory completion |

#### Document Type Field
New field added to `po_documents` table:

| Field | Type | Purpose |
|-------|------|---------|
| `document_type` | VARCHAR(50) | Categorizes uploaded documents (BOL, Invoice, Receipt, Bills, Other) |

**Files Modified:**
- `database/schema.sql` - Updated table definitions
- `database/migration_add_po_fields.sql` - Migration script for existing databases

### 2. Purchase Order Details Display

#### For Both Vendors and Buyers

**Vessel Information Section (read-only for non-editable statuses):**
- Vessel Name
- Vessel Identifier  
- Expected Factory Date

**Date Fields (editable when PO status is "Pending Received" or "Partially Received"):**
- Port Date
- Est. Delivery Date
- Ship Date

**Who can edit:**
- Vendors: Vessel fields, Port Date, Est. Delivery Date, Ship Date
- Buyers/Admins: All fields including Status and Memo

**Files Modified:**
- `public/vendor/dashboard.php` - Added vessel fields to PO details modal
- `public/buyer/dashboard.php` - Added vessel fields to PO details modal
- `public/api/purchase-orders.php` - Added new fields to editable fields list

### 3. Document Upload Enhancements

#### Document Type Dropdown

**Added dropdown to document upload form with options:**
- BOL (Bill of Lading)
- Invoice
- Receipt
- Bills
- Other (specify)

**Features:**
- Required field - users must select a document type before uploading
- "Other" option shows additional text input for custom specifications
- Selected document type is stored with the document for future reference
- Document type is displayed as a badge in the document list

**Files Modified:**
- `public/vendor/dashboard.php` - Added document type form field and display logic
- `public/api/upload.php` - Updated to accept and validate document_type field

### 4. Vendor Items Display - Editable Quantities

#### Changes to Items Tab

**Column Changes:**
- **Removed:** Rate column (no longer displayed)
- **Renamed:** "Vendor Qty" → "Vendor/Shipped QTY"
- **Made Editable:** Vendor/Shipped Qty column now shows input fields in edit mode

**Editing Capability:**
- Only available when PO status is "Pending Received" (B) or "Partially Received" (E)
- Shows read-only values for other statuses
- "Save Quantity Changes" button appears when in editable mode
- Each item quantity can be individually updated

**Display:**
| Column | Vendor View | Buyer View |
|--------|-------------|-----------|
| Item | Editable text inputs | Read-only |
| Original Qty | Read-only | Read-only |
| Vendor/Shipped Qty | **Editable** | Read-only |
| Amount | Read-only | Read-only |
| Rate | ❌ Removed | ❌ Removed |

**Files Modified:**
- `public/vendor/dashboard.php` - Updated items table with editable quantities
- `public/buyer/dashboard.php` - Updated items table without Rate column
- `public/api/purchase-orders.php` - Added `update_vendor_quantities` endpoint

### 5. API Enhancements

#### New Endpoint: Update Vendor Quantities

**Endpoint:** `POST /api/purchase-orders.php`
**Action:** `update_vendor_quantities`

**Request Format:**
```json
{
    "action": "update_vendor_quantities",
    "po_id": 123,
    "items": [
        {"item_id": 1, "vendor_quantity": "100.5"},
        {"item_id": 2, "vendor_quantity": "200"}
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Updated 2 item(s)"
}
```

**Features:**
- Only vendors can call this endpoint
- Updates po_items.vendor_quantity
- Marks PO as having vendor updates (has_vendor_updates = 1)
- Marks as not synced to NetSuite (is_synced_to_netsuite = 0)
- Logs activity with update details

#### Updated Endpoint: Upload Document

**Endpoint:** `POST /api/upload.php`
**New Parameter:** `document_type` (required)

**Supported Document Types:**
- BOL
- Invoice
- Receipt
- Bills
- Other: [custom specification]

**Example Request:**
```
POST /api/upload.php
Content-Type: multipart/form-data

po_id: 123
document_type: BOL
file: [binary file data]
comment: Shipment for order ABC-123
```

**Files Modified:**
- `public/api/upload.php` - Added document_type validation and storage
- `public/api/purchase-orders.php` - Added update_vendor_quantities handler

### 6. Frontend Updates

#### Vendor Dashboard (`public/vendor/dashboard.php`)

**New Functions:**
- `saveVendorQtyChanges(poId)` - Handles saving edited vendor quantities
  - Collects all edited quantities
  - Sends to API for storage
  - Refreshes modal on success
  
- Enhanced `uploadDocument(poId)` - Handles "Other" document type
  - Shows validation for "Other" specification field
  - Combines "Other" with specification text
  - Ensures document type is provided

**Event Handlers:**
- Document type dropdown change handler shows/hides "Other specification" input

#### Buyer Dashboard (`public/buyer/dashboard.php`)

**Updates:**
- Displays new vessel fields (read-only)
- Shows document types as badges on uploaded documents
- Can edit vessel and date fields (editable mode)
- Items tab displays vendor/shipped quantity without rate column

### 7. UI/UX Improvements

#### Document List Display
- Document type shown as colored badge (bg-info class)
- Positioned next to filename for easy identification
- Example: `document.pdf BOL (2.5 MB) - uploaded 2 days ago`

#### Form Organization
- Vessel information in first section
- Date information in second section
- Clear section headings with bold labels
- Proper spacing with mb-4 (margin-bottom) classes

#### Validation
- Document type is required during upload
- "Other" specification is required when "Other" is selected
- Frontend validation prevents invalid submissions

## Migration Guide

### For Existing Databases

Run the migration script to add new columns:

```bash
mysql -u root -p laguna_partner < database/migration_add_po_fields.sql
```

Or execute SQL directly:
```sql
-- Add vessel fields
ALTER TABLE `purchase_orders` ADD COLUMN `vessel_name` VARCHAR(255) AFTER `location`;
ALTER TABLE `purchase_orders` ADD COLUMN `vessel_identifier` VARCHAR(100) AFTER `vessel_name`;
ALTER TABLE `purchase_orders` ADD COLUMN `expected_factory_date` DATE AFTER `vessel_identifier`;

-- Add document type field
ALTER TABLE `po_documents` ADD COLUMN `document_type` VARCHAR(50) AFTER `file_type`;
ALTER TABLE `po_documents` ADD INDEX `idx_document_type` (`document_type`);
```

### For Docker Deployment

If using Docker, rebuild and restart containers:
```bash
docker-compose down
docker-compose up -d
```

The database initialization will include new columns.

## Testing Checklist

### Vendor Functions
- [ ] Can view vessel fields when opening PO
- [ ] Can edit vessel fields when PO status is B or E
- [ ] Can edit vendor quantities for items
- [ ] "Save Quantity Changes" button appears in edit mode
- [ ] Document upload dropdown shows all 5 options
- [ ] "Other (specify)" shows text input when selected
- [ ] Document type badge appears on uploaded document
- [ ] Document type displays correctly after refresh

### Buyer Functions
- [ ] Can view vessel fields when opening PO
- [ ] Can edit vessel fields
- [ ] Can see vendor-entered vessel data after vendor update
- [ ] Items show Vendor/Shipped Qty (not Rate)
- [ ] Document type badges visible on all documents
- [ ] Can filter documents by type (if filter added later)

### API Functions
- [ ] `PUT /api/purchase-orders.php` updates vessel fields
- [ ] `POST /api/purchase-orders.php` with `update_vendor_quantities` works
- [ ] `POST /api/upload.php` requires document_type
- [ ] `POST /api/upload.php` rejects invalid file types
- [ ] Database correctly stores all new values

## Database Queries for Reporting

### Find POs by Vessel Name
```sql
SELECT id, tran_id, vessel_name, vessel_identifier FROM purchase_orders 
WHERE vessel_name LIKE '%containership%' ORDER BY created_date DESC;
```

### Count Documents by Type
```sql
SELECT document_type, COUNT(*) as count 
FROM po_documents 
GROUP BY document_type 
ORDER BY count DESC;
```

### Find POs with Vendor Quantity Updates
```sql
SELECT po.id, po.tran_id, COUNT(poi.id) as items_with_vendor_qty
FROM purchase_orders po
JOIN po_items poi ON po.id = poi.po_id
WHERE poi.vendor_quantity IS NOT NULL AND poi.vendor_quantity != poi.quantity
GROUP BY po.id
ORDER BY po.created_date DESC;
```

## Known Limitations & Notes

1. **Rate Column Removed:** Vendors no longer see the Rate column in items. This is by design - pricing remains between buyer and NetSuite.

2. **Vendor Quantity Editing:** Vendors can only edit quantities when PO is in "Pending Received" or "Partially Received" status. This prevents unauthorized changes to completed/billed orders.

3. **Document Type Flexibility:** The "Other" option stores up to 50 characters of combined "Other: [specification]" text. For very long specifications, use the Comment field.

4. **NetSuite Sync:** When vendor quantities change, the PO is marked as `has_vendor_updates = 1` and `is_synced_to_netsuite = 0`. Admins must explicitly approve and sync changes to NetSuite.

## Future Enhancements

Potential future additions to consider:
- Document type filtering in document list
- Vessel tracking integration (AIS/SISA)
- Expected factory date notifications
- Vendor quantity change approval workflow
- Bulk vessel/date updates for multiple POs
- Export PO details with vessel information
- Analytics dashboard for vessel tracking

## Support

For issues or questions about these enhancements:
1. Check the test results above
2. Review database migration logs
3. Check browser console for JavaScript errors
4. Review PHP error logs for API issues
5. Verify document_type field exists in po_documents table

---

**Version:** 1.0
**Last Updated:** 2025
**Modified By:** System Enhancement