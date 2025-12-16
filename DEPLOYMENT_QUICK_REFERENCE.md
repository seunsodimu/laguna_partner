# 📋 INVOICE SYSTEM DEPLOYMENT - QUICK REFERENCE

**⏱️ Total Time: 15-20 minutes**

---

## 🚀 OPTION A: AUTOMATED DEPLOYMENT (RECOMMENDED)

### Run this single command:
```powershell
powershell -ExecutionPolicy Bypass -File "c:\xampp\htdocs\laguna_partner\deploy-invoice-system.ps1"
```

**What it does:**
- ✅ Backs up your database
- ✅ Verifies all files
- ✅ Applies database migration
- ✅ Creates upload directories
- ✅ Clears cache
- ✅ Restarts web service
- ✅ Verifies installation

**Total Time:** 5-7 minutes (mostly waiting)

---

## 🔧 OPTION B: MANUAL DEPLOYMENT

### Step 1: Backup Database (2 min)
```powershell
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
docker exec laguna_partner_db mysqldump -u root laguna_partner | Out-File -FilePath "c:\xampp\htdocs\laguna_partner\backups\backup_$timestamp.sql" -Encoding UTF8
```

### Step 2: Apply Migration (1 min)
```powershell
Get-Content "c:\xampp\htdocs\laguna_partner\database\migration_add_invoice_management.sql" | `
    docker exec -i laguna_partner_db mysql -u root laguna_partner
```

### Step 3: Verify Migration (1 min)
```powershell
docker exec laguna_partner_db mysql -u root laguna_partner -e "SHOW TABLES LIKE 'invoice%';"
```

### Step 4: Create Directories (1 min)
```powershell
New-Item -ItemType Directory -Path "c:\xampp\htdocs\laguna_partner\uploads\invoices" -Force
New-Item -ItemType Directory -Path "c:\xampp\htdocs\laguna_partner\uploads\vendor_documents" -Force
New-Item -ItemType Directory -Path "c:\xampp\htdocs\laguna_partner\uploads\payment_receipts" -Force
```

### Step 5: Restart Web Service (1 min)
```powershell
docker restart laguna_partner_web
Start-Sleep -Seconds 3
```

### Step 6: Clear Browser Cache (1 min)
```
Ctrl+Shift+Delete
```

---

## ✅ VERIFICATION CHECKLIST

After deployment, verify these items:

### Database
- [ ] 9 tables created (invoices, invoice_line_items, etc.)
```powershell
docker exec laguna_partner_db mysql -u root laguna_partner -e "SHOW TABLES LIKE 'invoice%'; SHOW TABLES LIKE 'payment%'; SHOW TABLES LIKE 'vendor%';"
```

### Directories
- [ ] `/uploads/invoices` exists
- [ ] `/uploads/vendor_documents` exists
- [ ] `/uploads/payment_receipts` exists

### Web Access
- [ ] Can access: `http://localhost/vendor/invoices.php`
- [ ] Can log in as vendor
- [ ] Can create test invoice
- [ ] Can submit invoice
- [ ] Can edit profile

### No Errors
- [ ] Browser console has no errors (F12)
- [ ] Docker logs show no errors
```powershell
docker logs laguna_partner_web | Select-Object -Last 20
```

---

## 🧪 QUICK TEST

### Test Invoice Creation (2 min)
1. Log in: `http://localhost/vendor-dealer-login.php`
2. Navigate to: `http://localhost/vendor/invoices.php`
3. Click "Create Invoice"
4. Fill in:
   - Invoice Number: `TEST-001`
   - Invoice Date: Today
   - Due Date: 30 days from now
   - Amount: `1000.00`
5. Click "Add Line Item"
6. Fill in:
   - Description: `Test Item`
   - Qty: `1`
   - Price: `1000.00`
7. Click "Save Line Item"
8. Click "Save as Draft"
9. **Expected:** Invoice appears with status "DRAFT"

### Test Invoice Submission (1 min)
1. Click "View" on your test invoice
2. Click "Submit"
3. **Expected:** Status changes to "SUBMITTED", buttons disabled

### Test Profile (1 min)
1. Click "My Profile" tab
2. Change company name
3. Click "Save Changes"
4. **Expected:** Success message, change persists

---

## 🐛 QUICK TROUBLESHOOTING

### Database migration fails
```
❌ Problem: Error: Table already exists
✅ Solution: This is normal - tables already exist from previous migration. Verify all 9 tables exist instead.
```

### Cannot access invoices.php
```
❌ Problem: Page not found or 401 Unauthorized
✅ Solution: 
   1. Make sure you're logged in
   2. Clear cookies: Ctrl+Shift+Delete
   3. Restart browser
   4. Log in again
```

### Upload directories don't exist
```
❌ Problem: Permission denied when uploading
✅ Solution: Re-run Step 4 of manual deployment
```

### Web server not responding
```
❌ Problem: Cannot access http://localhost
✅ Solution:
   docker ps
   docker restart laguna_partner_web
   docker restart laguna_partner_db
```

### Docker container not running
```
❌ Problem: Cannot find laguna_partner_db
✅ Solution:
   docker-compose -f c:\xampp\htdocs\laguna_partner\docker-compose.yml up -d
```

---

## 📊 WHAT YOU'LL GET

After successful deployment:

| Component | Status |
|-----------|--------|
| **9 Database Tables** | ✅ Ready |
| **3 API Endpoints** | ✅ Ready |
| **Vendor Dashboard** | ✅ Ready |
| **Upload System** | ✅ Ready |
| **Permission System** | ✅ Ready |
| **Invoice Status Tracking** | ✅ Ready |

### Available URLs
```
Vendor Dashboard:  http://localhost/vendor/invoices.php
API - Invoices:    http://localhost/public/api/invoices.php
API - Payments:    http://localhost/public/api/payments.php
API - Profile:     http://localhost/public/api/vendor-profile.php
```

---

## 📚 DOCUMENTATION

| Document | Purpose |
|----------|---------|
| **INVOICE_DEPLOYMENT_PRODUCTION.md** | Full detailed deployment guide |
| **INVOICE_QUICK_START.md** | 5-minute quick start |
| **INVOICE_MANAGEMENT_README.md** | Complete API documentation |
| **INVOICE_SYSTEM_IMPLEMENTATION_SUMMARY.md** | Implementation overview |
| **INVOICE_FEATURES_CHECKLIST.md** | 100+ feature verification items |

---

## ⏱️ TIME BREAKDOWN

| Task | Time | Command |
|------|------|---------|
| Backup DB | 2 min | See Option B Step 1 |
| Migration | 1 min | See Option B Step 2 |
| Verify DB | 1 min | See Option B Step 3 |
| Create Dirs | 1 min | See Option B Step 4 |
| Restart | 1 min | See Option B Step 5 |
| Browser | 1 min | See Option B Step 6 |
| Testing | 3-5 min | See Quick Test section |
| **TOTAL** | **15-20 min** | **Start with Option A** |

---

## 🎯 SUCCESS CRITERIA

✅ You're successful when:
1. All 9 database tables exist
2. You can access `/vendor/invoices.php`
3. You can create an invoice
4. Invoice can be submitted
5. Profile can be edited
6. No browser or server errors

---

## 📞 GETTING HELP

### Check These Files First:
1. **INVOICE_DEPLOYMENT_PRODUCTION.md** - Most detailed
2. **INVOICE_QUICK_START.md** - Quick troubleshooting
3. **INVOICE_MANAGEMENT_README.md** - API examples

### Useful Commands:

```powershell
# View recent errors
docker logs laguna_partner_web

# Check database tables
docker exec laguna_partner_db mysql -u root laguna_partner -e "SHOW TABLES;"

# Count invoices
docker exec laguna_partner_db mysql -u root laguna_partner -e "SELECT COUNT(*) FROM invoices;"

# Check container status
docker ps | findstr laguna_partner

# View upload directories
Get-ChildItem "c:\xampp\htdocs\laguna_partner\uploads" -Recurse
```

---

## 🚀 READY TO DEPLOY?

### Choose your path:

**Option A (Recommended):** Run automated script (5 min + 5 min testing)
```powershell
powershell -ExecutionPolicy Bypass -File "c:\xampp\htdocs\laguna_partner\deploy-invoice-system.ps1"
```

**Option B:** Follow manual steps above (15-20 min total)

**Both options result in identical deployment!**

---

## 💾 BACKUP LOCATION

Your database backup will be saved to:
```
c:\xampp\htdocs\laguna_partner\backups\backup_YYYYMMDD_HHMMSS.sql
```

**Keep this backup safe!** You can restore from it if needed:
```powershell
Get-Content "c:\xampp\htdocs\laguna_partner\backups\backup_20250101_120000.sql" | `
    docker exec -i laguna_partner_db mysql -u root laguna_partner
```

---

## ✨ FINAL CHECKLIST

Before starting deployment:
- [ ] Docker is running
- [ ] MySQL container is up
- [ ] Web server is running
- [ ] You have admin access to your computer
- [ ] You've read this document
- [ ] You have 20 minutes available

**Ready? Start with Option A above!** ✅

---

**Questions?** See INVOICE_DEPLOYMENT_PRODUCTION.md for detailed information.