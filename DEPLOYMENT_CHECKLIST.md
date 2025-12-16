# 🚀 Deployment Checklist - Purchase Order Enhancements

## Pre-Deployment

- [ ] **Database Backup**
  ```bash
  mysqldump -u root -p laguna_partner > backup_$(date +%Y%m%d_%H%M%S).sql
  ```
  Backup saved to: `________________`

- [ ] **Review Changes**
  - [ ] Read IMPLEMENTATION_SUMMARY.md
  - [ ] Review CHANGELOG_PO_ENHANCEMENTS.md
  - [ ] Check VISUAL_OVERVIEW.md for UI changes

- [ ] **Code Review**
  - [ ] Reviewed public/api/purchase-orders.php changes
  - [ ] Reviewed public/api/upload.php changes
  - [ ] Reviewed public/vendor/dashboard.php changes
  - [ ] Reviewed public/buyer/dashboard.php changes

- [ ] **Test in Staging (if available)**
  - [ ] Run migration script on test database
  - [ ] Test vendor login and PO access
  - [ ] Test buyer login and PO access
  - [ ] Upload test documents with types

---

## Database Migration

### Option 1: Fresh Installation
- [ ] Deploying fresh installation (no migration needed)
- [ ] database/schema.sql includes all new fields

### Option 2: Existing Installation

**Step 1: Execute Migration**
```bash
mysql -u root -p laguna_partner < database/migration_add_po_fields.sql
```

- [ ] Migration completed successfully
- [ ] No SQL errors reported

**Step 2: Verify Migration**
```sql
-- Verify purchase_orders table has new columns
DESCRIBE purchase_orders;
-- Look for: vessel_name, vessel_identifier, expected_factory_date

-- Verify po_documents table has new column
DESCRIBE po_documents;
-- Look for: document_type
```

- [ ] vessel_name column exists (VARCHAR 255)
- [ ] vessel_identifier column exists (VARCHAR 100)
- [ ] expected_factory_date column exists (DATE)
- [ ] document_type column exists (VARCHAR 50)
- [ ] All indexes created successfully

**Step 3: Data Integrity Check**
```sql
-- Count existing purchase orders
SELECT COUNT(*) FROM purchase_orders;
-- Record: ____________

-- Count existing documents
SELECT COUNT(*) FROM po_documents;
-- Record: ____________

-- Verify no data loss
SELECT COUNT(*) FROM po_items;
-- Record: ____________
```

- [ ] Record counts match expectations
- [ ] No data loss detected

---

## File Deployment

### Backend Files
- [ ] `public/api/purchase-orders.php` deployed
  - Includes: New fields in editable array
  - Includes: updateVendorQuantities() function
  
- [ ] `public/api/upload.php` deployed
  - Includes: document_type validation
  - Includes: document_type storage

### Frontend Files
- [ ] `public/vendor/dashboard.php` deployed
  - Includes: Vessel fields form
  - Includes: Editable quantities
  - Includes: Document type dropdown
  - Includes: JavaScript functions
  - Includes: Event handlers

- [ ] `public/buyer/dashboard.php` deployed
  - Includes: Vessel fields form
  - Includes: Updated items table
  - Includes: Document type badges

### Database Files
- [ ] `database/schema.sql` updated (for future deployments)
- [ ] `database/migration_add_po_fields.sql` saved (for reference)

---

## Vendor Testing

**Login as Vendor User**

- [ ] Can access vendor dashboard
- [ ] Can view purchase orders
- [ ] Can open PO details modal
- [ ] **Vessel Fields Display**
  - [ ] Vessel Name field visible
  - [ ] Vessel Identifier field visible
  - [ ] Expected Factory Date field visible
  
- [ ] **Edit Vessel Fields (only when PO status is B or E)**
  - [ ] Can enter Vessel Name
  - [ ] Can enter Vessel Identifier
  - [ ] Can select Expected Factory Date
  - [ ] [Save Changes] button visible and clickable
  - [ ] Changes persist after save
  - [ ] Modal refreshes with new values
  
- [ ] **Edit Item Quantities (only when PO status is B or E)**
  - [ ] Items tab shows: Item, Original Qty, Vendor/Shipped Qty, Amount
  - [ ] Rate column NOT visible ✓
  - [ ] Vendor/Shipped Qty fields are editable input boxes
  - [ ] Can enter decimal values
  - [ ] [Save Quantity Changes] button appears
  - [ ] Quantities save correctly
  - [ ] PO shows "Vendor has updates" for buyer
  
- [ ] **Document Upload**
  - [ ] Goes to Documents tab
  - [ ] Document Type dropdown shows all 5 options:
    - [ ] BOL (Bill of Lading)
    - [ ] Invoice
    - [ ] Receipt
    - [ ] Bills
    - [ ] Other (specify)
  - [ ] If "BOL" selected: Other Specify field hidden
  - [ ] If "Other" selected: Other Specify text input appears
  - [ ] Can upload file with BOL type
  - [ ] Can upload file with "Other: Custom Type"
  - [ ] Document appears with correct type badge
  - [ ] Badge shows "🔖 BOL", "🔖 Invoice", etc.
  
- [ ] **Status Restrictions**
  - [ ] When PO status is F or H: All fields read-only
  - [ ] Quantity input fields disabled
  - [ ] Save buttons grayed out/disabled

---

## Buyer Testing

**Login as Buyer User**

- [ ] Can access buyer dashboard
- [ ] Can view all purchase orders
- [ ] Can open PO details modal

- [ ] **View Vessel Fields**
  - [ ] Can see Vessel Name field
  - [ ] Can see Vessel Identifier field
  - [ ] Can see Expected Factory Date field
  
- [ ] **Edit Vessel Fields (always editable for buyers)**
  - [ ] Can edit Vessel Name
  - [ ] Can edit Vessel Identifier
  - [ ] Can edit Expected Factory Date
  - [ ] Changes save correctly
  - [ ] Can update anytime (no status restriction)
  
- [ ] **View Items**
  - [ ] Items tab shows: Item, Original Qty, Vendor/Shipped Qty, Amount
  - [ ] Rate column NOT visible ✓
  - [ ] Vendor/Shipped Qty displays correctly
  - [ ] Shows vendor-updated quantities when vendor made changes
  
- [ ] **View Documents**
  - [ ] Documents show with type badges
  - [ ] Can see: 🔖 BOL, 🔖 Invoice, 🔖 Receipt, etc.
  - [ ] Document type badges appear in correct position
  
- [ ] **Approve Vendor Changes**
  - [ ] When vendor made updates: "Vendor has made updates" flag visible
  - [ ] [Approve & Sync to NetSuite] button appears
  - [ ] Can click to approve and sync changes

---

## API Testing

### Test Vessel Field Update
```bash
curl -X PUT http://localhost/public/api/purchase-orders.php \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123,
    "vessel_name": "MSC Marco",
    "vessel_identifier": "IMO-1234567",
    "expected_factory_date": "2025-02-15"
  }'
```

- [ ] Request succeeds (200 OK)
- [ ] Response shows: `"success": true`
- [ ] Changes reflected in database

### Test Vendor Quantity Update
```bash
curl -X POST http://localhost/public/api/purchase-orders.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_vendor_quantities",
    "po_id": 123,
    "items": [
      {"item_id": 456, "vendor_quantity": "500.50"}
    ]
  }'
```

- [ ] Request succeeds
- [ ] Response shows: `"success": true`
- [ ] Quantity updated in database

### Test Document Upload
```bash
curl -X POST http://localhost/public/api/upload.php \
  -F "po_id=123" \
  -F "document_type=BOL" \
  -F "file=@test.pdf" \
  -F "comment=Test document"
```

- [ ] Request succeeds
- [ ] Response includes document_type
- [ ] document_type saved in database

### Test Missing Document Type (should fail)
```bash
curl -X POST http://localhost/public/api/upload.php \
  -F "po_id=123" \
  -F "file=@test.pdf"
```

- [ ] Request fails with 400 error
- [ ] Error message: "Document type required"

---

## Browser Cache Clearing

- [ ] **Chrome Users**
  - [ ] Ctrl + Shift + Delete to open Cache clearing dialog
  - [ ] OR: Hard refresh Ctrl + Shift + R
  
- [ ] **Firefox Users**
  - [ ] Ctrl + Shift + Delete to open Cache clearing dialog
  - [ ] OR: Hard refresh Ctrl + F5
  
- [ ] **Safari Users**
  - [ ] Cmd + Option + E to clear cache
  - [ ] OR: Hard refresh Cmd + Shift + R
  
- [ ] **Edge Users**
  - [ ] Ctrl + Shift + Delete to open Cache clearing dialog
  - [ ] OR: Hard refresh Ctrl + Shift + R

---

## Security Verification

- [ ] **Input Validation**
  - [ ] Cannot upload document without type selection
  - [ ] Cannot upload invalid file types
  - [ ] Large files (>10MB) rejected
  
- [ ] **Permission Checks**
  - [ ] Vendors can only edit own POs
  - [ ] Buyers cannot edit vendor quantities
  - [ ] Admins have full access
  - [ ] Dealers have no access
  
- [ ] **XSS Protection**
  - [ ] Special characters in vessel name handled safely
  - [ ] Special characters in document comments handled safely
  
- [ ] **SQL Injection Prevention**
  - [ ] All queries use parameterized statements
  - [ ] No direct string concatenation in queries

---

## Performance Verification

- [ ] **Page Load Time**
  - [ ] Vendor dashboard loads in <2 seconds
  - [ ] Buyer dashboard loads in <2 seconds
  - [ ] PO modal opens in <1 second
  
- [ ] **Database Queries**
  - [ ] Indexes are used (EXPLAIN PLAN shows indexes)
  - [ ] No N+1 query problems
  - [ ] Document type index speeds up queries
  
- [ ] **File Upload Performance**
  - [ ] Small files (<1MB) upload in <5 seconds
  - [ ] Large files (<10MB) upload without timeout

---

## Error Handling

- [ ] **Database Errors**
  - [ ] Migration errors caught and reported
  - [ ] Column existence checked before use
  
- [ ] **API Errors**
  - [ ] Missing required field returns 400 with message
  - [ ] Permission denied returns 403 with message
  - [ ] Not found returns 404 with message
  
- [ ] **Frontend Errors**
  - [ ] No JavaScript console errors
  - [ ] Error messages display to user
  - [ ] Toast notifications show correctly
  - [ ] Modal still functional after errors

---

## Documentation

- [ ] **Files Created**
  - [ ] ✅ CHANGELOG_PO_ENHANCEMENTS.md exists
  - [ ] ✅ PO_ENHANCEMENTS_QUICK_START.md exists
  - [ ] ✅ IMPLEMENTATION_SUMMARY.md exists
  - [ ] ✅ VISUAL_OVERVIEW.md exists
  - [ ] ✅ WORK_COMPLETED.md exists
  - [ ] ✅ DEPLOYMENT_CHECKLIST.md exists
  
- [ ] **Files Updated**
  - [ ] ✅ database/schema.sql updated
  - [ ] ✅ public/api/purchase-orders.php updated
  - [ ] ✅ public/api/upload.php updated
  - [ ] ✅ public/vendor/dashboard.php updated
  - [ ] ✅ public/buyer/dashboard.php updated
  
- [ ] **README Updated**
  - [ ] Consider updating main README.md if applicable
  - [ ] Document new features for users

---

## Training & Communication

- [ ] **User Notification**
  - [ ] Email sent to vendors about new features
  - [ ] Email sent to buyers about UI changes
  - [ ] Training session scheduled (if applicable)
  
- [ ] **Documentation Shared**
  - [ ] PO_ENHANCEMENTS_QUICK_START.md sent to users
  - [ ] Screenshots/visuals provided
  - [ ] Support contact information provided

- [ ] **Vendor Training Topics**
  - [ ] How to edit vessel information
  - [ ] How to edit quantities (when allowed)
  - [ ] How to select document type
  - [ ] How to handle "Other" specification
  
- [ ] **Buyer Training Topics**
  - [ ] New vessel fields in POs
  - [ ] Document type organization
  - [ ] How to approve vendor updates
  - [ ] UI changes to items table

---

## Monitoring & Support

**Post-Deployment (24-48 hours)**

- [ ] **Monitor Error Logs**
  - [ ] Check PHP error logs: `tail -f logs/php_error.log`
  - [ ] Check Apache logs: `tail -f /var/log/apache2/error.log`
  - [ ] No new errors reported
  
- [ ] **Monitor Activity**
  - [ ] Users accessing new features
  - [ ] Document uploads with types working
  - [ ] No duplicate uploads
  - [ ] Quantity updates processing
  
- [ ] **Collect Feedback**
  - [ ] Any UI confusion reported
  - [ ] Any missing field issues
  - [ ] Any performance complaints
  - [ ] Any document type issues

- [ ] **Provide Support**
  - [ ] Support team trained on changes
  - [ ] FAQ prepared for common questions
  - [ ] Troubleshooting guide available
  - [ ] Rollback plan ready (if needed)

---

## Rollback Plan

**If Issues Occur:**

1. **Quick Rollback (if within 1 hour)**
   ```bash
   # Restore from backup
   mysql -u root -p laguna_partner < backup_YYYYMMDD_HHMMSS.sql
   
   # Revert file changes (from git)
   git checkout -- public/api/*.php
   git checkout -- public/*/dashboard.php
   ```

2. **Clear Browser Cache**
   - Users: Ctrl+Shift+Delete and clear cache
   - CDN: Clear if applicable

3. **Test Restoration**
   - Verify old PO details display correctly
   - Verify document upload still works
   - Verify no data loss

---

## Final Sign-Off

**Deployment Manager:** _________________________ Date: _______

**QA Lead:** _________________________________ Date: _______

**Database Admin:** ______________________________ Date: _______

**Deployment Approved:** ⬜ YES ⬜ NO ⬜ CONDITIONAL

**Conditional Approval Notes:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

## Deployment Execution

**Deployment Date & Time:** _____________________________

**Deployed By:** ______________________________ Time: __________

**Deployment Status:**
- [ ] Database migration: ✅ COMPLETE / ⚠️ ISSUES
- [ ] File deployment: ✅ COMPLETE / ⚠️ ISSUES
- [ ] Vendor testing: ✅ COMPLETE / ⚠️ ISSUES
- [ ] Buyer testing: ✅ COMPLETE / ⚠️ ISSUES
- [ ] API testing: ✅ COMPLETE / ⚠️ ISSUES

**Issues Encountered:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

**Resolution:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

**Deployment Completed:** ⬜ YES ⬜ PARTIAL ⬜ ROLLBACK

**Time to Complete:** ____________ minutes

**Post-Deployment Verification Complete:** ⬜ YES ⬜ NO

---

## Post-Deployment Notes

```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

**Checklist Version:** 1.0
**Last Updated:** 2025
**Status:** READY FOR DEPLOYMENT ✅

---

**❗ DO NOT PROCEED WITH DEPLOYMENT UNTIL ALL ITEMS ARE CHECKED ✅**

For questions or issues, refer to:
- IMPLEMENTATION_SUMMARY.md
- CHANGELOG_PO_ENHANCEMENTS.md
- PO_ENHANCEMENTS_QUICK_START.md