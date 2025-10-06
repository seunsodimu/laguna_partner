# NetSuite Environment Switching Guide

This guide explains how to switch between NetSuite Production and Sandbox environments in the Laguna Partners Portal.

## Overview

The application supports two NetSuite environments:

- **Sandbox (11134099_SB1)**: Safe testing environment for development and testing
- **Production (11134099)**: Live environment with real business data

## Configuration

### Environment Variables

The `.env` file contains credentials for both environments:

```env
# NetSuite Environment Selection
NETSUITE_ENVIRONMENT=sandbox  # Options: production, sandbox

# Production Credentials
NETSUITE_PROD_ACCOUNT_ID=11134099
NETSUITE_PROD_CONSUMER_KEY=c9ef9c30af3b72b09ed512087933de194c35718c47b77af3d275c77b73f5f23b
NETSUITE_PROD_CONSUMER_SECRET=b53325854f963c6b75c1ec99de7ff339fbd2e4693130a61ba87cb35e51f44f3a
NETSUITE_PROD_TOKEN_ID=2ca5364b2c7913fd48c0c6f8f690360c03effb53ac9b4bc3d56f3315825aa3a3
NETSUITE_PROD_TOKEN_SECRET=b9d5afea8792e5f70d9f1850fa6e26f4165f29fd26dfa4ea823b4dfbd92a6531
NETSUITE_PROD_BASE_URL=https://11134099.suitetalk.api.netsuite.com

# Sandbox Credentials
NETSUITE_SANDBOX_ACCOUNT_ID=11134099_SB1
NETSUITE_SANDBOX_CONSUMER_KEY=3f33b52a89cf7f188ea268d3ac45bc9d541cd5bffa978f909b6f41caaf0ae7c8
NETSUITE_SANDBOX_CONSUMER_SECRET=d2b10d6f46e75d10499c1c14b0d279668da635eef0e1f83910119a7db5545d80
NETSUITE_SANDBOX_TOKEN_ID=abdf71349257fb118169bd9a790450e44c9cc36ecd6f33a53cb9602bf3
NETSUITE_SANDBOX_TOKEN_SECRET=186eeb568989094f49d9dbfeee2856347185c92f434807f0594309b1158071cf
NETSUITE_SANDBOX_BASE_URL=https://11134099-sb1.suitetalk.api.netsuite.com
```

### Credentials Structure

The `config/credentials.php` file automatically loads the correct credentials based on the `NETSUITE_ENVIRONMENT` setting:

```php
'netsuite' => [
    'production' => [
        'account_id' => $_ENV['NETSUITE_PROD_ACCOUNT_ID'],
        'consumer_key' => $_ENV['NETSUITE_PROD_CONSUMER_KEY'],
        // ... other production credentials
    ],
    'sandbox' => [
        'account_id' => $_ENV['NETSUITE_SANDBOX_ACCOUNT_ID'],
        'consumer_key' => $_ENV['NETSUITE_SANDBOX_CONSUMER_KEY'],
        // ... other sandbox credentials
    ]
]
```

## Switching Methods

### Method 1: Admin Web Interface (Recommended)

1. Log in as an admin user
2. Navigate to **Admin Dashboard**
3. Click on **NetSuite Environment** in the System Settings card
4. Select the desired environment from the dropdown
5. Click **Switch Environment**
6. Confirm the switch (especially important for production)
7. Run a full sync to ensure data consistency

**URL**: `http://localhost/laguna_partner/public/admin/settings.php`

### Method 2: Command Line Interface

Use the CLI script for quick environment switching:

```bash
# Switch to sandbox
php scripts/switch-environment.php sandbox

# Switch to production (requires confirmation)
php scripts/switch-environment.php production
```

The script will:
- Show current environment
- Confirm the switch (especially for production)
- Update the `.env` file
- Display the new configuration
- Remind you to run syncs

### Method 3: Manual .env Edit

1. Open `.env` file in a text editor
2. Find the line: `NETSUITE_ENVIRONMENT=sandbox`
3. Change to: `NETSUITE_ENVIRONMENT=production` (or vice versa)
4. Save the file
5. Run syncs to update data

## After Switching Environments

**IMPORTANT**: After switching environments, you must run a full sync to ensure data consistency:

```bash
# Sync accounts and users
php scripts/sync-accounts.php

# Sync purchase orders
php scripts/sync-purchase-orders.php

# Sync items
php scripts/sync-items.php
```

Or use the Admin Dashboard to run manual syncs.

## Environment Details

### Sandbox Environment

- **Account ID**: `11134099_SB1`
- **Base URL**: `https://11134099-sb1.suitetalk.api.netsuite.com`
- **Purpose**: Testing and development
- **Data**: Test data only, safe to modify
- **Recommended for**:
  - Development and testing
  - Training new users
  - Testing new features
  - Debugging issues

### Production Environment

- **Account ID**: `11134099`
- **Base URL**: `https://11134099.suitetalk.api.netsuite.com`
- **Purpose**: Live business operations
- **Data**: Real business data
- **⚠️ WARNING**: All changes affect live data
- **Recommended for**:
  - Live business operations
  - Real vendor/dealer interactions
  - Actual purchase order management

## Security Considerations

1. **Access Control**: Only admin users can switch environments
2. **Confirmation Required**: Production switches require explicit confirmation
3. **Audit Logging**: All environment switches are logged with user information
4. **Credential Separation**: Production and sandbox credentials are stored separately
5. **Environment Indicator**: Current environment is clearly displayed in the admin dashboard

## Best Practices

### Development Workflow

1. **Start in Sandbox**: Always develop and test in sandbox first
2. **Test Thoroughly**: Verify all features work correctly in sandbox
3. **Switch to Production**: Only switch to production when ready for live use
4. **Monitor Closely**: Watch for any issues after switching to production
5. **Keep Sandbox Updated**: Regularly refresh sandbox data from production

### Production Deployment

1. **Backup First**: Ensure database backups are current
2. **Off-Peak Hours**: Switch during low-traffic periods
3. **Team Notification**: Inform team members before switching
4. **Full Sync**: Run complete data sync after switching
5. **Verification**: Verify data integrity after sync completes

### Troubleshooting

If you encounter issues after switching:

1. **Verify Credentials**: Check that credentials are correct in `.env`
2. **Check Environment**: Confirm `NETSUITE_ENVIRONMENT` is set correctly
3. **Test Connection**: Run a simple sync to test API connectivity
4. **Review Logs**: Check error logs for API authentication issues
5. **Revert if Needed**: Switch back to previous environment if problems persist

## Common Scenarios

### Scenario 1: Testing New Features

```bash
# Switch to sandbox
php scripts/switch-environment.php sandbox

# Run syncs
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php

# Test your features
# ...

# When ready, switch to production
php scripts/switch-environment.php production
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php
```

### Scenario 2: Emergency Rollback

If you need to quickly switch back to sandbox:

```bash
# Quick switch to sandbox
php scripts/switch-environment.php sandbox

# Or via web interface
# Navigate to: /admin/settings.php
# Select "Sandbox" and click "Switch Environment"
```

### Scenario 3: Scheduled Maintenance

```bash
# Before maintenance: switch to sandbox
php scripts/switch-environment.php sandbox

# Perform maintenance tasks
# ...

# After maintenance: switch back to production
php scripts/switch-environment.php production
php scripts/sync-accounts.php
php scripts/sync-purchase-orders.php
php scripts/sync-items.php
```

## Monitoring

### Check Current Environment

**Via CLI:**
```bash
php scripts/switch-environment.php
# Output: Current environment: sandbox
```

**Via Web:**
- Admin Dashboard shows current environment in a badge
- Settings page displays full configuration details

**Via Code:**
```php
$env = $_ENV['NETSUITE_ENVIRONMENT'] ?? 'sandbox';
echo "Current environment: $env";
```

### Verify Configuration

```bash
# Check .env file
cat .env | grep NETSUITE_ENVIRONMENT

# Check credentials are loaded correctly
php -r "require 'vendor/autoload.php'; \$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); \$dotenv->load(); echo \$_ENV['NETSUITE_ENVIRONMENT'];"
```

## Support

If you encounter issues with environment switching:

1. Check file permissions on `.env` file
2. Verify credentials are correct for both environments
3. Review error logs in `logs/` directory
4. Test API connectivity with NetSuite
5. Contact NetSuite support if API credentials are invalid

## Related Documentation

- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Initial setup instructions
- [DEPLOYMENT.md](DEPLOYMENT.md) - Production deployment guide
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Quick command reference
- [README.md](README.md) - General project overview