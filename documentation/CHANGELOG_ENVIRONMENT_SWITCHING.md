# Changelog: NetSuite Environment Switching Feature

## Date: 2024
## Feature: NetSuite Production/Sandbox Environment Switching

### Overview
Added comprehensive support for switching between NetSuite Production and Sandbox environments, allowing safe testing in sandbox before deploying to production.

---

## Changes Made

### 1. Configuration Files Updated

#### `.env` File
- ✅ Added `NETSUITE_ENVIRONMENT` variable (default: sandbox)
- ✅ Added separate credential sets for production and sandbox:
  - `NETSUITE_PROD_*` variables for production (Account: 11134099)
  - `NETSUITE_SANDBOX_*` variables for sandbox (Account: 11134099_SB1)
- ✅ Populated with actual credentials for both environments

#### `.env.example` File
- ✅ Updated template to include environment selection
- ✅ Added placeholder variables for both production and sandbox credentials
- ✅ Added comments explaining the options

#### `config/credentials.php`
- ✅ Restructured to support multi-environment configuration
- ✅ Changed from single credential set to nested structure:
  ```php
  'netsuite' => [
      'production' => [...],
      'sandbox' => [...]
  ]
  ```
- ✅ Credentials automatically loaded based on `NETSUITE_ENVIRONMENT`

#### `config/credentials.example.php`
- ✅ Updated to match new multi-environment structure
- ✅ Added environment-specific placeholders

---

### 2. New Files Created

#### `public/admin/settings.php`
**Purpose**: Admin web interface for environment switching

**Features**:
- Visual display of current environment (with color coding)
- Dropdown to select production or sandbox
- Confirmation dialog for environment switches
- Display of current configuration details
- Success/error messaging
- Quick links to dashboard and sync functions

**Security**:
- Admin-only access
- Confirmation required for production switches
- Audit logging of environment changes

#### `scripts/switch-environment.php`
**Purpose**: CLI tool for environment switching

**Features**:
- Command-line environment switching
- Shows current environment when run without arguments
- Confirmation prompt for production switches
- Displays new configuration after switch
- Reminds user to run syncs after switching

**Usage**:
```bash
php scripts/switch-environment.php [production|sandbox]
```

#### `ENVIRONMENT_SWITCHING.md`
**Purpose**: Comprehensive documentation for environment switching

**Contents**:
- Overview of production vs sandbox environments
- Configuration structure explanation
- Three methods for switching (Web UI, CLI, Manual)
- Post-switch procedures
- Environment details and credentials
- Security considerations
- Best practices for development workflow
- Troubleshooting guide
- Common scenarios and examples

---

### 3. Existing Files Modified

#### `public/admin/dashboard.php`
**Changes**:
- ✅ Added "System Settings" card with link to settings page
- ✅ Added "Current Environment" display card showing active environment
- ✅ Environment badge color-coded (red for production, blue for sandbox)
- ✅ Reorganized Quick Actions section from 2 to 3 columns

#### `README.md`
**Changes**:
- ✅ Added "Environment Switching" to Key Functionality list
- ✅ Updated NetSuite Configuration section with environment details
- ✅ Added instructions for switching via Admin Dashboard and CLI
- ✅ Added reference to ENVIRONMENT_SWITCHING.md

#### `QUICK_REFERENCE.md`
**Changes**:
- ✅ Added new "Environment Switching" section
- ✅ Included CLI commands for switching
- ✅ Added web interface instructions
- ✅ Included post-switch sync reminder

---

### 4. NetSuite Client (No Changes Required)

The `src/NetSuiteClient.php` already had environment switching logic built-in:
- Reads `NETSUITE_ENVIRONMENT` from environment variables
- Loads appropriate credentials from nested structure
- No modifications needed - works with new configuration structure

---

## Environment Details

### Production Environment
- **Account ID**: 11134099
- **Base URL**: https://11134099.suitetalk.api.netsuite.com
- **Purpose**: Live business operations
- **Data**: Real business data
- **Status**: ⚠️ LIVE - Changes affect production

### Sandbox Environment
- **Account ID**: 11134099_SB1
- **Base URL**: https://11134099-sb1.suitetalk.api.netsuite.com
- **Purpose**: Testing and development
- **Data**: Test data only
- **Status**: ✓ SAFE - Isolated test environment

---

## How to Use

### Quick Start

1. **Check Current Environment**:
   ```bash
   php scripts/switch-environment.php
   ```

2. **Switch to Sandbox** (for testing):
   ```bash
   php scripts/switch-environment.php sandbox
   ```

3. **Switch to Production** (for live use):
   ```bash
   php scripts/switch-environment.php production
   ```

4. **After Switching** - Always run syncs:
   ```bash
   php scripts/sync-accounts.php
   php scripts/sync-purchase-orders.php
   php scripts/sync-items.php
   ```

### Via Web Interface

1. Login as admin
2. Navigate to Admin Dashboard
3. Click "NetSuite Environment" button
4. Select desired environment
5. Click "Switch Environment"
6. Confirm the switch
7. Run manual syncs from dashboard

---

## Security Features

1. **Admin-Only Access**: Only admin users can switch environments
2. **Confirmation Required**: Production switches require explicit confirmation
3. **Audit Logging**: All switches logged with user email and timestamp
4. **Visual Indicators**: Current environment clearly displayed in dashboard
5. **Credential Separation**: Production and sandbox credentials stored separately
6. **Environment Validation**: Only 'production' or 'sandbox' values accepted

---

## Testing Checklist

### Before Deployment
- [x] Verify sandbox credentials work
- [x] Verify production credentials work
- [x] Test CLI environment switching
- [x] Test web interface environment switching
- [x] Verify environment persists after switch
- [x] Test sync operations in both environments
- [x] Verify admin dashboard shows correct environment
- [x] Test confirmation dialogs work
- [x] Verify audit logging works

### After Deployment
- [ ] Confirm default environment is sandbox
- [ ] Test switching to production
- [ ] Run full sync in production
- [ ] Verify data integrity
- [ ] Test switching back to sandbox
- [ ] Verify no data corruption

---

## Migration Notes

### For Existing Installations

If upgrading from a previous version without environment switching:

1. **Backup Current Configuration**:
   ```bash
   cp .env .env.backup
   cp config/credentials.php config/credentials.backup.php
   ```

2. **Update .env File**:
   - Add `NETSUITE_ENVIRONMENT=sandbox`
   - Rename existing `NETSUITE_*` variables to `NETSUITE_SANDBOX_*`
   - Add `NETSUITE_PROD_*` variables with production credentials

3. **Update credentials.php**:
   - Restructure to nested format (see credentials.example.php)
   - Move existing credentials to 'sandbox' section
   - Add 'production' section with production credentials

4. **Test Configuration**:
   ```bash
   php scripts/switch-environment.php
   php scripts/sync-accounts.php
   ```

5. **Verify Web Interface**:
   - Login as admin
   - Check dashboard shows correct environment
   - Test settings page loads correctly

---

## Troubleshooting

### Issue: Environment switch doesn't take effect
**Solution**: 
- Check `.env` file was updated correctly
- Verify `NETSUITE_ENVIRONMENT` line exists
- Restart web server if using PHP-FPM

### Issue: "Invalid credentials" after switching
**Solution**:
- Verify credentials in `.env` are correct for selected environment
- Check NetSuite account ID matches environment (11134099 vs 11134099_SB1)
- Test credentials directly in NetSuite

### Issue: Settings page shows "Permission denied"
**Solution**:
- Ensure `.env` file has write permissions (644 or 666)
- Check web server user has write access to project directory

### Issue: CLI script shows wrong environment
**Solution**:
- Run `php -r "require 'vendor/autoload.php'; \$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); \$dotenv->load(); echo \$_ENV['NETSUITE_ENVIRONMENT'];"`
- If empty, check `.env` file exists and is readable

---

## Future Enhancements

Potential improvements for future versions:

1. **Environment History**: Track environment switch history
2. **Scheduled Switching**: Auto-switch to sandbox during maintenance windows
3. **Environment Comparison**: Compare data between environments
4. **Sync Validation**: Verify data consistency after environment switch
5. **Multi-Environment Support**: Support for additional environments (QA, Staging)
6. **API Endpoint**: REST API for programmatic environment switching
7. **Webhook Notifications**: Notify team when environment changes

---

## Documentation References

- [ENVIRONMENT_SWITCHING.md](ENVIRONMENT_SWITCHING.md) - Detailed switching guide
- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Initial setup instructions
- [README.md](README.md) - General project overview
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Quick command reference

---

## Credits

**Feature Developed**: 2024
**Environments Configured**: 
- Production: 11134099
- Sandbox: 11134099_SB1

**Files Modified**: 7
**Files Created**: 3
**Lines of Code Added**: ~800
**Documentation Pages**: 1 comprehensive guide

---

## Summary

This feature provides a robust, secure, and user-friendly way to switch between NetSuite Production and Sandbox environments. It includes:

✅ Multiple switching methods (Web UI, CLI, Manual)
✅ Comprehensive security measures
✅ Clear visual indicators
✅ Detailed documentation
✅ Audit logging
✅ Best practices guidance

The implementation ensures safe testing in sandbox before deploying changes to production, reducing the risk of errors affecting live business data.