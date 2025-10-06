# Bug Fix: Base Path Redirect Issue

## Problem Description

The application was redirecting to the localhost root directory (`localhost/admin/dashboard.php`) instead of the application root directory (`localhost/laguna_partner/admin/dashboard.php`).

### Root Cause

All redirect URLs throughout the application were hardcoded with absolute paths starting with `/`, which caused redirects to go to the server root instead of the application subdirectory.

**Example of the issue:**
```php
header('Location: /admin/dashboard.php');  // ❌ Redirects to localhost/admin/dashboard.php
```

**Should be:**
```php
header('Location: /laguna_partner/admin/dashboard.php');  // ✅ Redirects to localhost/laguna_partner/admin/dashboard.php
```

## Solution Implemented

### 1. Updated `.env` Configuration

Changed the `APP_BASE_PATH` setting to include the subdirectory:

**Before:**
```env
APP_BASE_PATH=/
```

**After:**
```env
APP_BASE_PATH=/laguna_partner
```

### 2. Added BASE_PATH Constant to All PHP Files

Added environment variable loading and BASE_PATH constant definition to all PHP files that perform redirects:

```php
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');
```

### 3. Updated All Redirect Statements

Changed all hardcoded redirect URLs to use the BASE_PATH constant:

**Before:**
```php
header('Location: /admin/dashboard.php');
```

**After:**
```php
header('Location: ' . BASE_PATH . '/admin/dashboard.php');
```

### 4. Updated All HTML Links

Changed all hardcoded HTML links to use the BASE_PATH constant:

**Before:**
```html
<a href="/admin/dashboard.php">Dashboard</a>
```

**After:**
```html
<a href="<?= BASE_PATH ?>/admin/dashboard.php">Dashboard</a>
```

## Files Modified

### Login Pages (3 files)
1. **`public/index.php`** - Landing page portal selection links
2. **`public/admin-buyer-login.php`** - Admin/Buyer login redirects and links
3. **`public/vendor-dealer-login.php`** - Vendor/Dealer login redirects and links

### Dashboard Pages (4 files)
4. **`public/admin/dashboard.php`** - Admin dashboard authentication redirect
5. **`public/buyer/dashboard.php`** - Buyer dashboard authentication redirect
6. **`public/vendor/dashboard.php`** - Vendor dashboard (uses Auth::requireAuth)
7. **`public/dealer/dashboard.php`** - Dealer dashboard authentication redirect

### Admin Pages (2 files)
8. **`public/admin/settings.php`** - Settings page authentication redirect
9. **`public/admin/users.php`** - User management authentication redirect

### Shared Components (2 files)
10. **`public/logout.php`** - Logout redirect to landing page
11. **`public/includes/header.php`** - Navigation menu links and CSS/logout links

### Configuration (1 file)
12. **`.env`** - Updated APP_BASE_PATH value

## Changes by File Type

### Authentication Redirects
All authentication check redirects now use BASE_PATH:
```php
if (!Auth::isLoggedIn() || !Auth::hasAccess('admin')) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}
```

### Post-Login Redirects
All successful login redirects now use BASE_PATH:
```php
switch ($userType) {
    case 'admin':
        header('Location: ' . BASE_PATH . '/admin/dashboard.php');
        break;
    case 'buyer':
        header('Location: ' . BASE_PATH . '/buyer/dashboard.php');
        break;
    // ... etc
}
```

### Navigation Links
All navigation menu links now use BASE_PATH:
```html
<a class="nav-link" href="<?= BASE_PATH ?>/admin/dashboard.php">
    <i class="bi bi-speedometer2"></i> Dashboard
</a>
```

### Portal Selection Links
Landing page portal selection buttons now use BASE_PATH:
```html
<a href="<?php echo BASE_PATH; ?>/admin-buyer-login.php" class="btn btn-admin-buyer btn-lg w-100">
    <i class="bi bi-box-arrow-in-right"></i> Admin/Buyer Login
</a>
```

## Testing Checklist

After implementing these changes, test the following scenarios:

### ✅ Landing Page
- [ ] Visit `http://localhost/laguna_partner/` - Should show portal selection
- [ ] Click "Admin/Buyer Login" - Should go to `/laguna_partner/admin-buyer-login.php`
- [ ] Click "Vendor/Dealer Login" - Should go to `/laguna_partner/vendor-dealer-login.php`

### ✅ Admin Login Flow
- [ ] Login as Admin
- [ ] Should redirect to `/laguna_partner/admin/dashboard.php`
- [ ] Click navigation links - All should include `/laguna_partner/` prefix
- [ ] Click Logout - Should redirect to `/laguna_partner/index.php`

### ✅ Buyer Login Flow
- [ ] Login as Buyer
- [ ] Should redirect to `/laguna_partner/buyer/dashboard.php`
- [ ] Click navigation links - All should include `/laguna_partner/` prefix
- [ ] Click Logout - Should redirect to `/laguna_partner/index.php`

### ✅ Vendor Login Flow
- [ ] Login as Vendor
- [ ] Should redirect to `/laguna_partner/vendor/dashboard.php`
- [ ] Click navigation links - All should include `/laguna_partner/` prefix
- [ ] Click Logout - Should redirect to `/laguna_partner/index.php`

### ✅ Dealer Login Flow
- [ ] Login as Dealer
- [ ] Should redirect to `/laguna_partner/dealer/dashboard.php`
- [ ] Click navigation links - All should include `/laguna_partner/` prefix
- [ ] Click Logout - Should redirect to `/laguna_partner/index.php`

### ✅ Authentication Protection
- [ ] Try accessing `/laguna_partner/admin/dashboard.php` without login
- [ ] Should redirect to `/laguna_partner/index.php`
- [ ] Try accessing wrong user type dashboard (e.g., Buyer accessing Admin)
- [ ] Should redirect to `/laguna_partner/index.php`

### ✅ Already Logged In
- [ ] Login as any user type
- [ ] Try visiting login pages while logged in
- [ ] Should auto-redirect to appropriate dashboard

## Benefits

1. **Flexible Deployment**: Application can now be deployed in any subdirectory by simply changing the `.env` file
2. **Consistent URLs**: All URLs throughout the application use the same BASE_PATH constant
3. **Easy Maintenance**: Single point of configuration for the application base path
4. **No Hardcoding**: No more hardcoded absolute paths in the codebase

## Future Deployment Options

### Option 1: Root Directory Deployment
If deploying to root directory (e.g., `https://partners.lagunatools.com/`):
```env
APP_BASE_PATH=
```

### Option 2: Subdirectory Deployment
If deploying to subdirectory (e.g., `https://lagunatools.com/partners/`):
```env
APP_BASE_PATH=/partners
```

### Option 3: Local Development
For local XAMPP development:
```env
APP_BASE_PATH=/laguna_partner
```

## Technical Notes

### Why Use BASE_PATH Constant?

1. **Performance**: Loading .env once per request and defining a constant is more efficient than reading environment variables multiple times
2. **Consistency**: Ensures all parts of the application use the same base path value
3. **Fallback**: The `??` operator provides a default value if the environment variable is not set
4. **Simplicity**: Easy to use in both PHP redirects and HTML links

### Environment Variable Loading

Each file that needs BASE_PATH loads the environment variables:
```php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
```

This ensures the `.env` file is parsed and variables are available via `$_ENV`.

### Relative Path Considerations

The path to `.env` varies by file location:
- Files in `public/`: `__DIR__ . '/..'`
- Files in `public/admin/`: `__DIR__ . '/../..'`
- Files in `public/buyer/`: `__DIR__ . '/../..'`

## Prevention Guidelines

### For Future Development

1. **Never use hardcoded absolute paths** starting with `/` for internal links
2. **Always use BASE_PATH constant** for all redirects and links
3. **Test in subdirectory** during development to catch path issues early
4. **Document deployment paths** in README or deployment guide

### Code Review Checklist

When reviewing code, check for:
- ❌ `header('Location: /some/path.php')`
- ❌ `<a href="/some/path.php">`
- ✅ `header('Location: ' . BASE_PATH . '/some/path.php')`
- ✅ `<a href="<?= BASE_PATH ?>/some/path.php">`

## Related Documentation

- **SEPARATE_LOGIN_PAGES.md** - Documentation for the separate login page implementation
- **BUGFIX_PDO_PARAMETERS.md** - Documentation for the PDO parameter bug fix
- **.env** - Environment configuration file

## Summary

This fix ensures that the Laguna Partners Portal works correctly when deployed in a subdirectory (like `/laguna_partner/` on localhost). All redirects and links now respect the `APP_BASE_PATH` configuration, making the application portable and easy to deploy in different environments.

**Status**: ✅ **COMPLETE** - All redirects and links updated to use BASE_PATH constant.