# Purchase Order Enhancements - Quick Start Guide

## What's New

### 🚢 Vessel Information
Purchase orders now include three new fields for tracking shipment details:
- **Vessel Name** - Name of the carrying vessel/company
- **Vessel Identifier** - Identification code (IMO, Call sign, etc.)
- **Expected Factory Date** - When the order should be manufactured

### 📄 Document Classification
When uploading documents, you must now select a document type:
- **BOL** - Bill of Lading
- **Invoice** - Invoice document
- **Receipt** - Receipt/Proof of delivery
- **Bills** - Billing documents
- **Other** - Custom type (must specify)

### ✏️ Vendor Quantity Editing
Vendors can now edit "Vendor/Shipped Qty" directly in the items list when the PO is in:
- Pending Received (status B)
- Partially Received (status E)

Buyers can see the editable quantities but cannot change them.

---

## For Vendors

### How to Edit Vessel Information
1. Open a Purchase Order (status B or E)
2. Enter or update:
   - Vessel Name
   - Vessel Identifier
   - Expected Factory Date
3. Click **"Save Changes"** button
4. Changes are marked for buyer review

### How to Edit Item Quantities
1. Open a Purchase Order (status B or E)
2. Go to **Items** tab
3. Edit the **Vendor/Shipped Qty** field for each item
4. Click **"Save Quantity Changes"** button
5. Buyer will see the updates pending approval

### How to Upload Documents
1. Open a Purchase Order
2. Go to **Documents** tab
3. Select **Document Type** from dropdown (required!)
4. If selecting "Other", specify the document type
5. Choose the file to upload
6. Add optional comment
7. Click **"Upload Document"** button

**Note:** The document type helps buyers organize and find documents quickly.

---

## For Buyers

### What Changed
- **New Fields:** View vessel information for shipments
- **Editable:** You can also update vessel and date fields
- **Items List:** No longer shows "Rate" column (internal pricing)
- **Document Types:** Documents show their type as a colored badge

### How to Review Vendor Updates
1. Look for PO(s) with "Has Updates" badge
2. Open the PO to review changes:
   - New vessel information
   - Updated item quantities
   - New documents uploaded
3. Review the Documents tab to see document types
4. Click **"Approve & Sync to NetSuite"** when ready

### How to Update PO Details
1. Edit vessel information, dates, or memo
2. Click **"Save Changes"** button
3. Changes sync to NetSuite immediately

---

## Database Migration

If upgrading from a previous version:

### Option 1: Run Migration Script
```bash
mysql -u root -p laguna_partner < database/migration_add_po_fields.sql
```

### Option 2: Manual SQL
```sql
ALTER TABLE `purchase_orders` 
ADD COLUMN `vessel_name` VARCHAR(255) AFTER `location`,
ADD COLUMN `vessel_identifier` VARCHAR(100) AFTER `vessel_name`,
ADD COLUMN `expected_factory_date` DATE AFTER `vessel_identifier`;

ALTER TABLE `po_documents` 
ADD COLUMN `document_type` VARCHAR(50) AFTER `file_type`,
ADD INDEX `idx_document_type` (`document_type`);
```

---

## Troubleshooting

### "Document type required" error
- Make sure you selected a document type from the dropdown
- If selecting "Other", fill in the specification field

### Quantity edit fields not appearing
- PO must be in status "Pending Received" (B) or "Partially Received" (E)
- Check the status badge - if showing "Pending Bill" or "Fully Billed", editing is locked

### Vessel fields not editable
- Same restriction as quantities - status must be B or E
- For vendors: only vessels in pending/partially received orders can be edited

### Changes not saving
- Check browser console for errors (F12 → Console tab)
- Verify you clicked the correct "Save" button
- Check that all required fields are filled

### Document type not showing after upload
- Refresh the modal by clicking the PO again
- Check that upload was successful (check for success message)
- Verify the `po_documents` table has the `document_type` column

---

## API Reference for Developers

### Update Vendor Quantities
```
POST /api/purchase-orders.php
Content-Type: application/json

{
    "action": "update_vendor_quantities",
    "po_id": 123,
    "items": [
        {"item_id": 1, "vendor_quantity": "100.50"},
        {"item_id": 2, "vendor_quantity": "200.00"}
    ]
}
```

**Response:** `{ "success": true, "message": "Updated 2 item(s)" }`

### Upload Document with Type
```
POST /api/upload.php
Content-Type: multipart/form-data

po_id: 123
document_type: BOL (or Invoice, Receipt, Bills, or "Other: Custom Type")
file: [file contents]
comment: Optional comment
```

### Get PO with New Fields
```
GET /api/purchase-orders.php?id=123
```

**Response includes:** `vessel_name`, `vessel_identifier`, `expected_factory_date` in PO object

---

## Important Notes

⚠️ **Vendor Quantity Changes:**
- When vendors update quantities, PO shows "Vendor has made updates"
- Buyers must review and approve changes before they sync to NetSuite
- Changes are NOT automatic

⚠️ **Document Types:**
- Types are stored for record-keeping and organization
- Cannot be changed after upload (delete and re-upload if needed)
- "Other" types can be up to 50 characters total

⚠️ **Vessel Information:**
- Required field in some organizations, optional in others
- Maps to NetSuite custom fields (custbody35, custbody36, custbody37)
- Used for shipping/logistics coordination

---

## Common Workflows

### Workflow 1: Vendor Confirms Shipment Details
1. Vendor opens PO
2. Enters Vessel Name and Identifier
3. Sets Expected Factory Date
4. Updates quantity if shipment is partial
5. Clicks "Save Changes"
6. Buyer receives notification and reviews
7. Buyer approves and syncs to NetSuite

### Workflow 2: Document Upload and Organization
1. Vendor receives shipper documents
2. Categorizes each document (BOL, Invoice, etc.)
3. Uploads to portal with type selected
4. Buyer can quickly find documents by type
5. Documents are organized for accounting/audit

### Workflow 3: Partial Shipment Processing
1. PO arrives with partial quantity
2. Vendor updates quantities in Items tab
3. Uploads BOL and receipt documents
4. Marks as partially shipped
5. Buyer reviews and approves quantity changes
6. Remaining items stay on open PO for next shipment

---

## Field Mapping to NetSuite

| Portal Field | NetSuite Field | NetSuite ID |
|--------------|----------------|-------------|
| Vessel Name | - | custbody36 |
| Vessel Identifier | - | custbody37 |
| Expected Factory Date | - | custbody35 |
| Document Type | - | Stored in portal only |

---

## Questions or Issues?

Check these resources:
1. **Changelog:** CHANGELOG_PO_ENHANCEMENTS.md - Full technical details
2. **Database Schema:** database/schema.sql - Table structure
3. **Migration Script:** database/migration_add_po_fields.sql - Update instructions
4. **API Code:** public/api/purchase-orders.php, public/api/upload.php

---

**Last Updated:** 2025
**Version:** 1.0