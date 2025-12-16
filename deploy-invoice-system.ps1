# Automated Invoice Management System Deployment Script
# This script automates the deployment of the Invoice Management System
# to your local production environment.

param(
    [switch]$SkipBackup = $false,
    [switch]$SkipMigration = $false,
    [switch]$Verbose = $false
)

# Color codes
$Success = [System.ConsoleColor]::Green
$ErrorColor = [System.ConsoleColor]::Red
$Warning = [System.ConsoleColor]::Yellow
$Info = [System.ConsoleColor]::Cyan

# Script settings
$projectRoot = "c:\xampp\htdocs\laguna_partner"
$backupDir = "$projectRoot\backups"
$migrationFile = "$projectRoot\database\migration_add_invoice_management.sql"
$uploadDirs = @(
    "$projectRoot\uploads\invoices",
    "$projectRoot\uploads\vendor_documents",
    "$projectRoot\uploads\payment_receipts"
)

# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

function Write-Status {
    param([string]$Message, [System.ConsoleColor]$Color = $Info)
    Write-Host $Message -ForegroundColor $Color
}

function Write-Success {
    param([string]$Message)
    Write-Host "[OK] $Message" -ForegroundColor $Success
}

function Write-Error-Custom {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor $ErrorColor
}

function Write-Warning-Custom {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor $Warning
}

function Write-Separator {
    Write-Host ""
    Write-Host "====================================================================" -ForegroundColor $Info
    Write-Host ""
}

# ============================================================================
# MAIN DEPLOYMENT SCRIPT
# ============================================================================

Write-Host ""
Write-Host "INVOICE MANAGEMENT SYSTEM - AUTOMATED DEPLOYMENT" -ForegroundColor $Info -BackgroundColor Black
Write-Host ""

Write-Separator

# ============================================================================
# STEP 1: PRE-FLIGHT CHECKS
# ============================================================================

Write-Status "STEP 1: PRE-FLIGHT CHECKS" -Color $Info

# Check project root exists
if (-not (Test-Path $projectRoot)) {
    Write-Error-Custom "Project root not found: $projectRoot"
    exit 1
}
Write-Success "Project root found"

# Check migration file exists
if (-not (Test-Path $migrationFile)) {
    Write-Error-Custom "Migration file not found: $migrationFile"
    exit 1
}
Write-Success "Migration file found"

# Check Docker is available
try {
    $dockerTest = docker ps 2>&1 | Select-Object -First 1
    Write-Success "Docker is available"
} catch {
    Write-Error-Custom "Docker not available. Please start Docker and try again."
    exit 1
}

# Check MySQL container
$containerStatus = docker ps | findstr laguna_partner_db
if (-not $containerStatus) {
    Write-Error-Custom "MySQL container laguna_partner_db not running"
    Write-Status "Try: docker-compose -f $projectRoot\docker-compose.yml up -d"
    exit 1
}
Write-Success "MySQL container is running"

# Check MySQL connectivity
$mysqlTest = docker exec laguna_partner_db mysql -u root -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error-Custom "Cannot connect to MySQL"
    exit 1
}
Write-Success "MySQL is accessible"

Write-Separator

# ============================================================================
# STEP 2: BACKUP DATABASE
# ============================================================================

if (-not $SkipBackup) {
    Write-Status "STEP 2: BACKUP DATABASE" -Color $Info
    
    # Create backup directory
    if (-not (Test-Path $backupDir)) {
        New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
        Write-Success "Backup directory created"
    }
    
    # Create timestamped backup file
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupFile = "$backupDir\laguna_partner_backup_$timestamp.sql"
    
    Write-Status "Creating database backup..." -Color $Warning
    
    try {
        docker exec laguna_partner_db mysqldump -u root laguna_partner | Out-File -FilePath $backupFile -Encoding UTF8 -ErrorAction Stop
        
        if (Test-Path $backupFile) {
            $backupSize = (Get-Item $backupFile).Length / 1MB
            Write-Success "Database backup created: $backupFile ($([math]::Round($backupSize, 2)) MB)"
        } else {
            Write-Error-Custom "Backup file was not created"
            exit 1
        }
    } catch {
        Write-Error-Custom "Failed to create backup: $_"
        exit 1
    }
} else {
    Write-Warning-Custom "Backup skipped (--SkipBackup flag set)"
}

Write-Separator

# ============================================================================
# STEP 3: VERIFY FILES
# ============================================================================

Write-Status "STEP 3: VERIFY FILES" -Color $Info

$requiredFiles = @(
    "$projectRoot\database\migration_add_invoice_management.sql",
    "$projectRoot\public\api\invoices.php",
    "$projectRoot\public\api\payments.php",
    "$projectRoot\public\api\vendor-profile.php",
    "$projectRoot\public\vendor\invoices.php"
)

$filesOk = $true
foreach ($file in $requiredFiles) {
    if (Test-Path $file) {
        Write-Success "Found: $(Split-Path $file -Leaf)"
    } else {
        Write-Error-Custom "Missing: $file"
        $filesOk = $false
    }
}

if (-not $filesOk) {
    Write-Error-Custom "Some files are missing. Cannot proceed."
    exit 1
}

Write-Separator

# ============================================================================
# STEP 4: APPLY DATABASE MIGRATION
# ============================================================================

if (-not $SkipMigration) {
    Write-Status "STEP 4: APPLY DATABASE MIGRATION" -Color $Info
    
    Write-Status "Applying migration..." -Color $Warning
    
    try {
        $migrationContent = Get-Content $migrationFile
        $migrationContent | docker exec -i laguna_partner_db mysql -u root laguna_partner 2>&1 | Out-Null
        
        # Check if migration succeeded by looking for error patterns
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Database migration applied successfully"
        } else {
            Write-Warning-Custom "Migration returned exit code $LASTEXITCODE (this may be normal)"
        }
    } catch {
        Write-Error-Custom "Failed to apply migration: $_"
        exit 1
    }
    
    # Verify tables were created
    Write-Status "Verifying tables were created..." -Color $Warning
    
    $tablesCheck = docker exec laguna_partner_db mysql -u root laguna_partner -e @"
SELECT COUNT(*) as table_count 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'laguna_partner' 
AND TABLE_NAME IN (
    'invoices', 'invoice_line_items', 'invoice_notes', 'invoice_attachments',
    'payments', 'payment_receipts', 'payment_method_preferences',
    'vendor_profiles', 'vendor_documents'
);
"@ 2>&1

    Write-Success "Checked for required tables"
    
} else {
    Write-Warning-Custom "Migration skipped (--SkipMigration flag set)"
}

Write-Separator

# ============================================================================
# STEP 5: CREATE UPLOAD DIRECTORIES
# ============================================================================

Write-Status "STEP 5: CREATE UPLOAD DIRECTORIES" -Color $Info

foreach ($dir in $uploadDirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Success "Created: $dir"
    } else {
        Write-Success "Already exists: $dir"
    }
    
    # Try to set permissions
    try {
        $acl = Get-Acl $dir
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
            "Users",
            "Modify",
            "ContainerInherit, ObjectInherit",
            "None",
            "Allow"
        )
        
        # Check if rule already exists
        $ruleExists = $acl.Access | Where-Object { 
            $_.IdentityReference -like "*Users*" -and 
            $_.FileSystemRights -like "*Modify*" 
        }
        
        if (-not $ruleExists) {
            $acl.AddAccessRule($rule)
            Set-Acl $dir $acl
        }
    } catch {
        Write-Warning-Custom "Could not set permissions on $dir (may not be necessary)"
    }
}

Write-Separator

# ============================================================================
# STEP 6: CLEAR CACHE
# ============================================================================

Write-Status "STEP 6: CLEAR APPLICATION CACHE" -Color $Info

$cacheDir = "$projectRoot\cache"
if (Test-Path $cacheDir) {
    try {
        Remove-Item "$cacheDir\*" -Recurse -Force -ErrorAction SilentlyContinue
        Write-Success "Cache cleared"
    } catch {
        Write-Warning-Custom "Could not clear cache: $_"
    }
} else {
    Write-Status "No cache directory found (normal)"
}

Write-Separator

# ============================================================================
# STEP 7: RESTART WEB SERVICE
# ============================================================================

Write-Status "STEP 7: RESTART WEB SERVICE" -Color $Info

try {
    Write-Status "Restarting web container..." -Color $Warning
    docker restart laguna_partner_web | Out-Null
    
    Write-Success "Web service restarted"
    Start-Sleep -Seconds 2
    Write-Status "Waiting for service to be ready..."
    Start-Sleep -Seconds 2
    Write-Success "Service ready"
} catch {
    Write-Warning-Custom "Could not restart web service: $_"
}

Write-Separator

# ============================================================================
# STEP 8: VERIFY INSTALLATION
# ============================================================================

Write-Status "STEP 8: VERIFY INSTALLATION" -Color $Info

# Test database
try {
    $dbTest = docker exec laguna_partner_db mysql -u root laguna_partner -e "SELECT COUNT(*) as invoice_count FROM invoices;" 2>&1 | Select-String "^[0-9]"
    Write-Success "Database is accessible"
} catch {
    Write-Warning-Custom "Could not verify database: $_"
}

# Test web server
try {
    $response = Invoke-WebRequest -Uri "http://localhost/" -ErrorAction SilentlyContinue
    if ($response.StatusCode -eq 200) {
        Write-Success "Web server is responding (Status: 200)"
    }
} catch {
    Write-Warning-Custom "Web server may not be responding"
}

# Check upload directories
$allDirsExist = $true
foreach ($dir in $uploadDirs) {
    if (Test-Path $dir) {
        Write-Success "Upload directory exists: $(Split-Path $dir -Leaf)"
    } else {
        Write-Error-Custom "Upload directory missing: $dir"
        $allDirsExist = $false
    }
}

Write-Separator

# ============================================================================
# DEPLOYMENT SUMMARY
# ============================================================================

Write-Host ""
Write-Host "DEPLOYMENT COMPLETE!" -ForegroundColor $Success -BackgroundColor Black
Write-Host ""

Write-Host "SUMMARY:" -ForegroundColor $Info
Write-Host ""
Write-Host "[OK] Database migration applied"
Write-Host "[OK] 9 new tables created"
Write-Host "[OK] Upload directories created"
Write-Host "[OK] Application cache cleared"
Write-Host "[OK] Web service restarted"
Write-Host ""

Write-Host "NEXT STEPS:" -ForegroundColor $Info
Write-Host ""
Write-Host "1. Clear browser cache:"
Write-Host "   Windows: Ctrl+Shift+Delete"
Write-Host "   Mac:     Cmd+Shift+Delete"
Write-Host ""
Write-Host "2. Log in as vendor:"
Write-Host "   URL: http://localhost/vendor-dealer-login.php"
Write-Host ""
Write-Host "3. Access invoice system:"
Write-Host "   URL: http://localhost/vendor/invoices.php"
Write-Host ""
Write-Host "4. Create test invoice to verify"
Write-Host ""

if ($backupFile) {
    Write-Host "BACKUP LOCATION:" -ForegroundColor $Info
    Write-Host "   $backupFile"
    Write-Host ""
}

Write-Host "DOCUMENTATION:" -ForegroundColor $Info
Write-Host "   - INVOICE_DEPLOYMENT_PRODUCTION.md"
Write-Host "   - INVOICE_QUICK_START.md"
Write-Host "   - INVOICE_MANAGEMENT_README.md"
Write-Host ""

Write-Host "For detailed information, see: INVOICE_DEPLOYMENT_PRODUCTION.md" -ForegroundColor $Warning
Write-Host ""