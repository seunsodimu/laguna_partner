# User Roles Refactoring - Implementation Guide

## Overview

This refactoring introduces a major update to the authentication and user management system:

1. **Terminology Change**: "Buyer" terminology replaced with "User"
2. **Role-Based System**: Internal users now have roles (Buyer, Accounting, Admin) instead of fixed user types
3. **Unified Logins**: Single login pages for each user type category:
   - `user-login.php` - For internal users (replaces admin-buyer-login.php)
   - `vendor-login.php` - For vendors (separate from dealers)
   - `dealer-login.php` - For dealers (separate from vendors)

## Database Changes

### What Changed

**Before:**
- `user_type` ENUM: 'admin', 'buyer', 'vendor', 'dealer'
- No role system

**After:**
- `user_type` ENUM: 'user', 'vendor', 'dealer'
- New `role` column: Stores 'admin', 'accounting', 'buyer' for user_type='user'
- New index on `role` column for performance

### Migration Steps

**Step 1: Apply Database Migration**

Run this SQL migration to update existing systems:

```bash
mysql -u root -p laguna_partner < database/migrate_user_roles.sql
```

Or execute via MySQL/PHPMyAdmin:

```sql
-- Add role column to users table
ALTER TABLE `users` ADD COLUMN `role` VARCHAR(50) DEFAULT NULL AFTER `user_type`;

-- Add index on role for faster queries
ALTER TABLE `users` ADD INDEX `idx_role` (`role`);

-- Update existing 'admin' user_type to have 'admin' role
UPDATE `users` SET `role` = 'admin' WHERE `user_type` = 'admin';

-- Update existing 'buyer' user_type to have 'buyer' role
UPDATE `users` SET `role` = 'buyer' WHERE `user_type` = 'buyer' AND `user_type` != 'admin';

-- Update user_type from 'buyer' to 'user' for internal staff
UPDATE `users` SET `user_type` = 'user' WHERE `user_type` = 'buyer' AND `role` IN ('buyer', 'accounting');
```

**Step 2: Verify Migration**

```sql
-- Check users table structure
DESC users;

-- Verify data migration
SELECT id, email, user_type, role FROM users LIMIT 10;
```

## File Changes

### New Files Created

| File | Purpose |
|------|---------|
| `public/user-login.php` | Unified login for internal users (Admin, Buyer, Accounting roles) |
| `public/vendor-login.php` | Separate vendor login page |
| `public/dealer-login.php` | Separate dealer login page |
| `database/migrate_user_roles.sql` | Database migration script |

### Files Modified

| File | Changes |
|------|---------|
| `src/Auth.php` | Updated to handle new user_type values and role system |
| `public/index.php` | Updated routing and UI for new login pages |
| `database/schema.sql` | Updated schema for new installations |
| `database/schema.sql` | OTP table updated with new user_type values |

### Files Deprecated (but still functional)

| File | Replacement |
|------|-------------|
| `public/admin-buyer-login.php` | `public/user-login.php` |
| `public/vendor-dealer-login.php` | `public/vendor-login.php` and `public/dealer-login.php` |

## How It Works

### Authentication Flow

```
User visits index.php
    ↓
Checks Auth::check()
    ↓
If logged in → Route based on user_type and role
    ├── user_type='user' → Check role
    │   ├── role='admin' → /admin/dashboard.php
    │   ├── role='accounting' → /buyer/dashboard.php
    │   └── role='buyer' → /buyer/dashboard.php
    │
    ├── user_type='vendor' → /vendor/dashboard.php
    │
    └── user_type='dealer' → /dealer/dashboard.php
    ↓
If not logged in → Show login portal (/index.php)
    ├── Internal User Login → /user-login.php
    ├── Vendor Login → /vendor-login.php
    └── Dealer Login → /dealer-login.php
```

### User Login Flow (user-login.php)

```
1. User enters email
2. System checks if email exists with user_type='user'
3. OTP generated and sent
4. User enters OTP
5. User retrieved from database with role
6. Route determined by role:
   - role='admin' → /admin/dashboard.php
   - role='buyer' → /buyer/dashboard.php
   - role='accounting' → /buyer/dashboard.php
```

### Session Data

**Session variables now include:**

```php
$_SESSION['user_id']       // User ID
$_SESSION['user_email']    // Email
$_SESSION['user_type']     // 'user', 'vendor', or 'dealer'
$_SESSION['role']          // 'admin', 'buyer', 'accounting' (for user_type='user')
$_SESSION['user_name']     // Full name
$_SESSION['logged_in']     // Boolean
$_SESSION['login_time']    // Timestamp
```

## User Type Reference

### user_type='user' (Internal Staff)

Roles (stored in `role` column):
- **admin**: Administrative access, system management
- **buyer**: Buyer/AP access, PO management
- **accounting**: Accounting access, invoice management

*Note: Roles currently have no privilege enforcement. Implement role-based access control as needed.*

### user_type='vendor'

- Vendors with single or multiple accounts
- Access to vendor dashboard and PO management
- No roles (future-proofing: could add sub-roles if needed)

### user_type='dealer'

- Dealers viewing items and inventory
- Access to dealer dashboard
- No roles (future-proofing: could add sub-roles if needed)

## Migration Checklist

### Pre-Migration

- [ ] Backup database
- [ ] Review current users and their types
- [ ] Plan role assignments for existing buyers
- [ ] Notify users about UI changes (optional)
- [ ] Test in development environment first

### Migration

- [ ] Stop application traffic (optional)
- [ ] Run SQL migration
- [ ] Verify data migration
- [ ] Deploy new files:
  - [ ] `public/user-login.php`
  - [ ] `public/vendor-login.php`
  - [ ] `public/dealer-login.php`
- [ ] Update `public/index.php`
- [ ] Update `src/Auth.php`
- [ ] Update `database/schema.sql`
- [ ] Clear browser cache/sessions if needed

### Post-Migration

- [ ] Test user login (user-login.php)
- [ ] Test vendor login (vendor-login.php)
- [ ] Test dealer login (dealer-login.php)
- [ ] Verify routing for each role
- [ ] Check admin dashboard loads correctly
- [ ] Check buyer dashboard loads correctly
- [ ] Monitor logs for errors

### Rollback Plan (if needed)

If you need to revert:

```sql
-- Revert user_type changes
UPDATE `users` SET `user_type` = 'buyer' WHERE `user_type` = 'user' AND `role` IN ('buyer', 'accounting');

-- Drop role column
ALTER TABLE `users` DROP COLUMN `role`;
ALTER TABLE `users` DROP INDEX `idx_role`;

-- Restore original user_type enum values if needed
-- ALTER TABLE `users` MODIFY `user_type` ENUM('admin', 'buyer', 'vendor', 'dealer');
```

## Code Examples

### Checking User Type and Role

```php
session_start();

// Current user
$user = Auth::user();

// Check if internal user
if ($user['type'] === 'user') {
    // Check specific role
    if ($user['role'] === 'admin') {
        // Admin actions
    } else if ($user['role'] === 'buyer') {
        // Buyer actions
    } else if ($user['role'] === 'accounting') {
        // Accounting actions
    }
}

// Check if vendor/dealer
if ($user['type'] === 'vendor') {
    // Vendor-specific actions
}
```

### Authentication Gates

For admin-only pages:

```php
if (!Auth::check()) {
    header('Location: /index.php');
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    http_response_code(403);
    echo "Access Denied";
    exit;
}
```

For internal users (any role):

```php
if (!Auth::check()) {
    header('Location: /index.php');
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user') {
    http_response_code(403);
    echo "Access Denied";
    exit;
}
```

## Backward Compatibility

### Session Access

Old code using `$_SESSION['user_type']` will still work:
- Returns 'user', 'vendor', or 'dealer'
- Admin users will have `$_SESSION['user_type'] = 'user'` (changed from 'admin')
- Buyer users will have `$_SESSION['user_type'] = 'user'` (changed from 'buyer')

### Auth::user() Method

```php
$user = Auth::user();
// $user['type'] - Returns 'user', 'vendor', 'dealer'
// $user['role'] - Returns role for user_type='user', null for others
```

## Future Enhancements

### Role-Based Access Control (RBAC)

Once roles are stable, implement privilege checks:

```php
// Define permissions per role
$rolePermissions = [
    'admin' => ['manage_users', 'manage_settings', 'view_reports', 'manage_pos', 'manage_invoices'],
    'buyer' => ['manage_pos', 'approve_invoices', 'view_reports'],
    'accounting' => ['manage_invoices', 'view_reports']
];

// Helper function
function userCan($permission) {
    $user = Auth::user();
    if ($user['type'] !== 'user') return false;
    
    global $rolePermissions;
    $permissions = $rolePermissions[$user['role']] ?? [];
    return in_array($permission, $permissions);
}

// Usage
if (!userCan('manage_pos')) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}
```

### Additional Roles

Easy to add new roles:

```sql
-- Add new roles for user_type='user'
-- Examples: 'finance', 'director', 'analyst', 'auditor'

-- Add new user with specific role
INSERT INTO users (email, user_type, role, first_name, last_name, status)
VALUES ('finance@company.com', 'user', 'finance', 'Jane', 'Doe', 'active');
```

## Troubleshooting

### "User not found or does not have access"

**Cause**: Email exists but user_type is not 'user'

**Solution**: Check database:
```sql
SELECT email, user_type, role FROM users WHERE email = 'user@example.com';
```

Ensure user_type='user' for internal staff.

### Redirect loop on login

**Cause**: User role or type not set correctly

**Solution**: 
1. Check Auth::user() returns correct type and role
2. Verify session data is being set
3. Check dashboard.php routing logic

### Old admin-buyer-login.php still used

**Solution**: Update links in navigation/UI to point to new user-login.php

## Terminology Reference

### Before → After

| Before | After |
|--------|-------|
| "Admin & Buyer Login" | "Internal User Login" |
| User Type: "Admin" | User Type: "User", Role: "Admin" |
| User Type: "Buyer" | User Type: "User", Role: "Buyer" |
| (N/A) | Role: "Accounting" |
| User Type: "Vendor" | User Type: "Vendor" (unchanged) |
| User Type: "Dealer" | User Type: "Dealer" (unchanged) |

## Files and Paths

### Login Pages
- `/user-login.php` - Internal users
- `/vendor-login.php` - Vendors
- `/dealer-login.php` - Dealers
- `/index.php` - Portal home/routing

### Backend
- `src/Auth.php` - Authentication class
- `database/schema.sql` - Database schema
- `database/migrate_user_roles.sql` - Migration script

### Dashboards (unchanged)
- `/admin/dashboard.php` - Admin dashboard
- `/buyer/dashboard.php` - Buyer/Accounting dashboard
- `/vendor/dashboard.php` - Vendor dashboard
- `/dealer/dashboard.php` - Dealer dashboard

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review database migration results
3. Check application logs
4. Verify session data with `Auth::user()`

---

**Last Updated**: January 2025  
**Version**: 1.0.0
