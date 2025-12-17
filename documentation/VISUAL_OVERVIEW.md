# Purchase Order Enhancements - Visual Overview

## 🎯 What Changed - At a Glance

### Before vs After

```
BEFORE                          AFTER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Items Table:
┌─────────────────────────────┐  ┌──────────────────────────┐
│ Item │Orig Qty │Vendor Qty  │  │ Item │Orig Qty │V/Shipped  │
│      │         │(read-only) │  │      │         │ (editable) │
│      │         │ Rate       │  │      │         │            │
│      │         │ Amount     │  │      │         │ Amount     │
└─────────────────────────────┘  └──────────────────────────┘
                                  ✓ Rate removed
                                  ✓ Qty editable (when status B/E)
                                  ✓ Better naming (V/Shipped)

Document Upload:
────────────────────────           ────────────────────────────
[Choose File] [Comment]            [Document Type] ← NEW!
[Upload]                           [Other Specify] ← Shows when needed
                                   [Choose File]
                                   [Comment]
                                   [Upload]
                                   ✓ Validation on type
                                   ✓ Badge display after upload

PO Details:
┌─────────────┐                ┌────────────────────────────┐
│ Port Date   │                │ Vessel Name           ← NEW!
│ Est. Deliv. │                │ Vessel Identifier     ← NEW!
│ Ship Date   │                │ Expected Factory Date ← NEW!
│             │                │ ─────────────────────────
└─────────────┘                │ Port Date
                               │ Est. Deliv.
                               │ Ship Date
                               │
                               └────────────────────────────┘
```

---

## 📊 Database Changes

### New Columns

```sql
purchase_orders TABLE:
┌─────────────────────────────────────────┐
│ Column                    │ Type         │
├─────────────────────────────────────────┤
│ vessel_name               │ VARCHAR(255) │ ← NEW
│ vessel_identifier         │ VARCHAR(100) │ ← NEW
│ expected_factory_date     │ DATE         │ ← NEW
│ (existing columns...)     │              │
└─────────────────────────────────────────┘

po_documents TABLE:
┌──────────────────────────────────────┐
│ Column              │ Type            │
├──────────────────────────────────────┤
│ document_type       │ VARCHAR(50)     │ ← NEW
│ (existing columns...)
└──────────────────────────────────────┘
```

---

## 🎨 UI/UX Changes

### Vendor Dashboard - PO Details Modal

```
╔════════════════════════════════════════════════════════════╗
║                  Purchase Order Details                    ║
╠════════════════════════════════════════════════════════════╣
║ PO# POD-001234                        Total: $50,000.00   ║
║ Vendor: Acme Corp Ltd.                                    ║
║ Status: Pending Received                                  ║
║ Created: 01/10/2025                                       ║
║─────────────────────────────────────────────────────────── ║
║                                                            ║
║ 🚢 VESSEL INFORMATION (NEW!)                              ║
║ ┌─────────────┬──────────────┬────────────────────────┐  ║
║ │ Vessel Name │ Identifier   │ Expected Factory Date  │  ║
║ │ [_________] │ [__________] │ [______________]       │  ║
║ └─────────────┴──────────────┴────────────────────────┘  ║
║                                                            ║
║ 📅 DELIVERY DATES                                          ║
║ ┌─────────────┬──────────────┬────────────────────────┐  ║
║ │ Port Date   │ Est. Delivery│ Ship Date              │  ║
║ │ [_________] │ [__________] │ [______________]       │  ║
║ └─────────────┴──────────────┴────────────────────────┘  ║
║                                                            ║
║            [💾 Save Changes]                               ║
║─────────────────────────────────────────────────────────── ║
║                                                            ║
║ 📋 TABS: [Items] [Comments] [Documents]                   ║
║                                                            ║
║ ╔═ Items (3) ═════════════════════════════════════════╗  ║
║ ║ Item │ Orig │ Vendor/Shipped │ Amount              ║  ║
║ ║      │ Qty  │ Qty (EDITABLE) │                     ║  ║
║ ║─────────────────────────────────────────────────    ║  ║
║ ║ SKU1 │ 500  │ [____] (change) │ $10,000           ║  ║
║ ║ SKU2 │ 250  │ [____] (change) │ $15,000           ║  ║
║ ║ SKU3 │ 100  │ [____] (change) │ $25,000           ║  ║
║ ║      │      │                 │                     ║  ║
║ ║      [💾 Save Quantity Changes]                     ║  ║
║ ╚═════════════════════════════════════════════════════╝  ║
║                                                            ║
║ ╔═ Documents (2) ═════════════════════════════════════╗  ║
║ ║ 📤 UPLOAD NEW DOCUMENT (NEW!)                      ║  ║
║ ║ Document Type * [▼ Select Type ─────────┐          ║  ║
║ ║                  │ BOL              │ ✓  ║  ← NEW!   ║
║ ║                  │ Invoice          │    ║           ║
║ ║                  │ Receipt          │    ║           ║
║ ║                  │ Bills            │    ║           ║
║ ║                  │ Other (specify)  │    ║           ║
║ ║                  └──────────────────┘    ║           ║
║ ║ [if Other]                               ║           ║
║ ║ Other Specify: [___________________]     ║           ║
║ ║ File: [Choose File]                      ║           ║
║ ║ Comment: [________________________]       ║           ║
║ ║ [📤 Upload Document]                     ║           ║
║ ║                                           ║           ║
║ ║ ┌─────────────────────────────────────┐  ║           ║
║ ║ │ 📄 bill_of_lading.pdf               │  ║           ║
║ ║ │ 🔖 BOL (245 KB) - 01/10 14:30       │  ║ ← NEW!   ║
║ ║ │ └──────────────────────────────────┘  ║           ║
║ ║ │ 📄 invoice_123456.pdf                │  ║           ║
║ ║ │ 🔖 Invoice (156 KB) - 01/10 14:25   │  ║ ← NEW!   ║
║ ║ │ └──────────────────────────────────┘  ║           ║
║ ╚═════════════════════════════════════════════════════╝  ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
```

---

## 🔄 Data Flow

### Vendor Quantity Update Flow

```
Vendor UI                   Backend API                Database
═════════                   ════════════               ════════

1. Edit Qty [500]
   Edit Qty [250]
              │
              │ POST /api/purchase-orders.php
              │ action: update_vendor_quantities
              ├──────────────────────────────→ Update po_items
              │  {po_id: 123,                  vendor_quantity
              │   items: [...]}                 SET has_vendor_updates = 1
              │                                 SET is_synced_to_netsuite = 0
              │
              ← "Updated 2 item(s)"
              │
2. Show toast
3. Refresh modal
              │
              │ GET /api/purchase-orders.php
              │ id=123
              │                               → Fetch fresh PO data
              ├──────────────────────────────→ with new quantities
              │
              ← Return PO with updates
              │
4. Display updated
   quantities
```

### Document Upload with Type Flow

```
Vendor UI                   Backend API                Database
═════════                   ════════════               ════════

1. Select Type [BOL]
2. Select File
3. Enter Comment
              │
              │ POST /api/upload.php
              │ (multipart/form-data)
              │ po_id: 123
              │ document_type: "BOL"
              ├──────────────────────────────→ Validate type
              │ file: [binary]                 Check access
              │ comment: "..."                 Save file
              │                                 INSERT po_documents
              │                                 {document_type: "BOL"}
              │
              ← {success: true, data: {...}}
              │
4. Show badge
   "BOL"
5. Close modal
```

### Vessel Information Save Flow

```
Vendor or Buyer UI         Backend API                Database
════════════════           ════════════               ════════

1. Enter:
   - Vessel Name
   - Vessel ID
   - Factory Date
   - Port Date
   - etc.
              │
              │ PUT /api/purchase-orders.php
              │ {id: 123,
              ├─────────────────────────────→ Check permissions
              │  vessel_name: "...",           Validate changes
              │  vessel_identifier: "...",     Track changes
              │  expected_factory_date: "...",UPDATE purchase_orders
              │  port_date: "..."             Log activity
              │ }                              Mark dirty if vendor
              │
              ← {success: true,
              │  changes: {...}}
              │
2. Show toast
   "Saved!"
3. Refresh modal
              │
              │ GET /api/purchase-orders.php
              │ id=123
              │                               → Fetch updated PO
              ├──────────────────────────────→ with all new fields
              │
              ← Return full PO data
              │
4. Display all
   fields with
   new values
```

---

## 📝 Form Fields Visual

### Document Type Dropdown Behavior

```
Click Dropdown:
┌─────────────────────────────┐
│ Document Type *             │  ← REQUIRED
├─────────────────────────────┤
│ -- Select Document Type --  │  ← Default
│ BOL (Bill of Lading)        │
│ Invoice                     │
│ Receipt                     │
│ Bills                       │
│ Other (specify)             │  ← Special option
└─────────────────────────────┘

If "Other (specify)" selected:
┌─────────────────────────────┐
│ Document Type * [Other ▼]   │
├─────────────────────────────┤
│ ✓ Other Specify *           │  ← NOW VISIBLE (required)
│ [Customs Manifest        ]  │    (max 50 chars)
│                             │
│ File * [Choose File]        │
│ Comment [________________]   │
│ [📤 Upload Document]        │
└─────────────────────────────┘

If "BOL" selected:
┌─────────────────────────────┐
│ Document Type * [BOL ▼]     │
├─────────────────────────────┤
│ ✗ Other Specify hidden      │
│                             │
│ File * [Choose File]        │
│ Comment [________________]   │
│ [📤 Upload Document]        │
└─────────────────────────────┘
```

---

## 🔐 Permission Matrix

```
┌───────────────────────────────────────────────────────────┐
│ FEATURE              │ VENDOR │ BUYER │ ADMIN │ DEALER   │
├───────────────────────────────────────────────────────────┤
│ View PO Details      │ Own    │ All   │ All   │ No       │
├───────────────────────────────────────────────────────────┤
│ Edit Vessel Fields   │ B/E*   │ Any   │ Any   │ No       │
│ (Name, ID, Date)     │        │       │       │          │
├───────────────────────────────────────────────────────────┤
│ Edit Vessel Fields   │ Own B/E│ Yes   │ Yes   │ No       │
│ Restricted Status    │        │       │       │          │
├───────────────────────────────────────────────────────────┤
│ Edit Dates           │ Own B/E│ Any   │ Any   │ No       │
│ (Port, Deliv, Ship)  │        │       │       │          │
├───────────────────────────────────────────────────────────┤
│ Edit Item Quantities │ Own B/E│ No    │ No    │ No       │
│ (Vendor/Shipped Qty) │        │       │       │          │
├───────────────────────────────────────────────────────────┤
│ Upload Documents     │ Own    │ Admin │ Admin │ No       │
├───────────────────────────────────────────────────────────┤
│ Select Doc Type      │ Yes    │ Admin │ Admin │ No       │
│ (Required)           │        │ only  │ only  │          │
├───────────────────────────────────────────────────────────┤
│ View Doc Type Badge  │ Yes    │ Yes   │ Yes   │ No       │
├───────────────────────────────────────────────────────────┤
│ Approve Qty Changes  │ No     │ Yes   │ Yes   │ No       │
│ (Sync to NetSuite)   │        │       │       │          │
└───────────────────────────────────────────────────────────┘

* B = Pending Received, E = Partially Received
  F = Pending Billing, H = Fully Billed
```

---

## 📊 Document Type Usage Example

```
PO #12345 - ACME Corp - $150,000

Documents Tab:
──────────────

📄 shipping_manifest.pdf
   🔖 BOL (345 KB)
   Uploaded by: John Vendor - 01/15/2025 10:30 AM

📄 commercial_invoice.pdf
   🔖 Invoice (234 KB)
   "Updated with corrected amounts"
   Uploaded by: John Vendor - 01/15/2025 10:45 AM

📄 delivery_proof.pdf
   🔖 Receipt (456 KB)
   Uploaded by: Mary Shipping - 01/18/2025 2:15 PM

📄 customs_entry_form.pdf
   🔖 Other: Customs Entry (567 KB)
   "Cleared by Customs Authority"
   Uploaded by: Admin User - 01/18/2025 3:30 PM

📄 inspection_report.docx
   🔖 Other: Quality Inspection (234 KB)
   Uploaded by: John Vendor - 01/19/2025 9:00 AM
```

---

## 🎯 Use Case Scenarios

### Scenario 1: Partial Shipment

```
Timeline:
─────────

Week 1: Vendor receives PO
┌─────────────────────────────┐
│ PO Status: B (Pending Recv) │
│ Item 1: 500 units ordered   │
│ Item 2: 250 units ordered   │
└─────────────────────────────┘

Week 2: Partial shipment ready
┌──────────────────────────────┐
│ Vendor Updates in Portal:    │
│ ✏️ Vessel Name: MV Harmony   │
│ ✏️ Vessel ID: IMO-1234567    │
│ ✏️ Expected Factory: 1/20    │
│ ✏️ Item 1 Qty: 500 → [300]   │
│ ✏️ Item 2 Qty: 250 → [0]     │
│ 💾 [Save Qty Changes]        │
│                              │
│ 📤 Upload Documents:         │
│ Type: [BOL ▼]                │
│ File: [bol.pdf]              │
│ 📤 Upload                    │
└──────────────────────────────┘

Buyer Reviews:
┌──────────────────────────────┐
│ ⚠️ Vendor has updates        │
│ Vessel: MV Harmony           │
│ Expected Factory: 1/20       │
│ Item 1: 300/500 units        │
│ Item 2: 0/250 units          │
│                              │
│ 📄 Documents:                │
│ • bol.pdf 🔖 BOL            │
│                              │
│ [✅ Approve & Sync]          │
└──────────────────────────────┘

Week 4: Second shipment
┌──────────────────────────────┐
│ Vendor Updates:              │
│ Item 1: 300 → [500] ✓ FULL  │
│ Item 2: 0 → [250] ✓ FULL    │
│ 📤 New Documents:            │
│ Type: [Invoice ▼]            │
│ File: [invoice.pdf]          │
│ 📤 Upload                    │
└──────────────────────────────┘
```

### Scenario 2: Factory Coordination

```
PO Status: B (Pending Received)

Buyer Sets:
┌─────────────────────────┐
│ Expected Factory Date:  │
│ [2025-02-15]            │
│ Vessel Name: [TBD]      │
└─────────────────────────┘

Vendor Updates When Ready:
┌──────────────────────────────┐
│ ✓ Vessel Name: MSC Marco     │
│ ✓ Vessel ID: IMO-9876543     │
│ ✓ Port Date: 2025-02-18      │
│ ✓ Est. Delivery: 2025-03-15  │
│ ✓ Ship Date: 2025-02-20      │
│                              │
│ Documents:                   │
│ 🔖 BOL ✓                     │
│ 🔖 Invoice ✓                 │
│ 🔖 Other: Factory Cert ✓     │
│                              │
│ [✓ All Ready]                │
└──────────────────────────────┘

Buyer Can Now:
• Track shipment details
• Monitor factory dates
• Organize documents by type
• Download specific doc types for audit
```

---

## 🔍 Key Visual Elements

### Status Badges

```
Document Type Badge:
┌─────────────────────────────────┐
│ bill_of_lading.pdf              │
│ 🔖 BOL  (245 KB)                │
│    ↑   badge with bg-info       │
│                                 │
│ 🔖 Invoice, 🔖 Receipt,         │
│ 🔖 Bills, 🔖 Other: [custom]    │
└─────────────────────────────────┘

PO Status Badge (existing):
┌──────────────────────────────┐
│ Status: ⚠️ PENDING RECEIVED  │
│ (editable mode active)       │
└──────────────────────────────┘

Vendor Update Flag (existing):
┌──────────────────────────────┐
│ ⚠️ Vendor has made updates   │
│ (review and approve)         │
└──────────────────────────────┘
```

### Input Field States

```
EDITABLE (Status B or E):
┌──────────────────────┐
│ Vessel Name          │
│ [▢ Edit me]          │ ← white bg, cursor
│                      │
│ [💾 Save Changes]    │ ← active button
└──────────────────────┘

READ-ONLY (Status F or H):
┌──────────────────────┐
│ Vessel Name          │
│ MV Harmony           │ ← gray bg, no cursor
│ (disabled)           │
│                      │
│ [💾 Save Changes]    │ ← grayed out button
└──────────────────────┘
```

---

## 📈 Implementation Statistics

```
Files Changed:        4
├─ purchase-orders.php (API)
├─ upload.php (API)
├─ vendor/dashboard.php (UI)
└─ buyer/dashboard.php (UI)

New Files:            4
├─ migration_add_po_fields.sql
├─ CHANGELOG_PO_ENHANCEMENTS.md
├─ PO_ENHANCEMENTS_QUICK_START.md
└─ IMPLEMENTATION_SUMMARY.md

Database Changes:     3 new columns (purchase_orders)
                      1 new column (po_documents)

New API Actions:      1 (update_vendor_quantities)
New API Parameters:   1 (document_type)

UI Components:        3 new form fields (vessels)
                      1 new dropdown (doc type)
                      1 new text input (other specify)
                      1 new editable column (qty)
                      Multiple badges (doc types)

JavaScript Functions: 2 new
                      (saveVendorQtyChanges, upload enhance)

Event Handlers:       1 new (document type dropdown)

Lines of Code Added:  ~450 lines total
                      ~200 lines backend
                      ~250 lines frontend
```

---

## ✅ Verification Checklist

```
Database Layer:
✅ New columns created
✅ Indexes added
✅ Data types correct
✅ Nullable fields configured
✅ Migration script ready

API Layer:
✅ Vessel fields in SELECT queries
✅ Vessel fields in editable list
✅ Document type validation
✅ Vendor quantity endpoint
✅ Error handling
✅ Permission checks
✅ Activity logging

Frontend Layer:
✅ Vessel fields displayed
✅ Vessel fields editable (conditional)
✅ Document type dropdown
✅ Other specification input (conditional)
✅ Document type badge
✅ Quantity editing form
✅ Save buttons and handlers
✅ Event listeners
✅ Toast notifications
✅ Modal refresh logic

Testing:
✅ Vendor edits quantities
✅ Buyer approves changes
✅ Documents upload with type
✅ Vessel info persists
✅ Edits blocked by status
✅ Permissions enforced
✅ Error messages clear
```

---

**Visual Overview Complete** ✅

All features implemented and ready for use.