# Laguna Partners Portal

A comprehensive vendor and dealer portal that integrates with NetSuite to manage purchase orders, inventory, and user access.

## Overview

The Laguna Partners Portal allows vendors and dealers to access product inventory and purchase orders from Laguna's NetSuite instance. The portal features OTP-based authentication and provides different access levels based on user type.

## Features

### User Types

1. **Vendor Access**
   - View open purchase orders (status: B, E, F, H)
   - Update PO dates (Port Date, Estimated Delivery Date, Ship Date)
   - Upload documents to purchase orders
   - Add comments and track conversations
   - View payment information and history

2. **Dealer Access**
   - View available items and inventory
   - Search items by name or SKU
   - Set up notifications for item availability
   - Manage notification preferences

3. **Buyer Access**
   - View all purchase orders regardless of status
   - Filter by status, vendor, buyer, dates
   - Approve vendor-requested changes
   - Receive notifications for vendor updates
   - Make changes to all PO fields

4. **Admin Access**
   - Manage all user types
   - Manual sync for accounts, users, and purchase orders
   - Edit/update purchase orders
   - View user activity logs
   - Update email templates

### Key Functionality

- **OTP Authentication**: Secure one-time password login via email
- **Multi-Account Support**: Users can be associated with multiple accounts
- **NetSuite Integration**: Bi-directional sync with NetSuite
- **Environment Switching**: Easy switching between NetSuite Production and Sandbox
- **Email Notifications**: Automated notifications via Brevo or Amazon SES
- **Document Management**: Upload and manage PO documents
- **Responsive Design**: Mobile-ready interface

## Technology Stack

- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Frontend**: Bootstrap 5, JavaScript
- **Email**: Brevo (Sendinblue) / Amazon SES
- **Integration**: NetSuite REST API (OAuth 1.0)
- **Deployment**: Docker, EC2

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer
- NetSuite account with REST API access
- Brevo or Amazon SES account for email

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd laguna_partner
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   
   Edit `.env` and configure:
   - Database credentials
   - NetSuite API credentials
   - Email provider settings (Brevo or SES)
   - Application URL

4. **Configure NetSuite credentials**
   ```bash
   cp config/credentials.example.php config/credentials.php
   ```
   
   Edit `config/credentials.php` with your NetSuite OAuth credentials

5. **Create database**
   ```bash
   mysql -u root -p
   CREATE DATABASE integration_db;
   ```

6. **Import database schema**
   ```bash
   mysql -u root -p integration_db < database/schema.sql
   ```

7. **Set up web server**
   
   Point your web server document root to the `public` directory.
   
   **Apache (.htaccess)**:
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```

8. **Set permissions**
   ```bash
   chmod -R 755 public
   chmod -R 777 logs
   chmod -R 777 uploads
   ```

## Configuration

### Database Configuration

Edit `.env`:
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=integration_db
DB_USER=your_user
DB_PASS=your_password
```

### NetSuite Configuration

The application supports both Production and Sandbox environments. Configure both in `.env`:

```env
# Select environment: production or sandbox
NETSUITE_ENVIRONMENT=sandbox

# Production credentials
NETSUITE_PROD_ACCOUNT_ID=11134099
NETSUITE_PROD_CONSUMER_KEY=your_production_key
# ... other production credentials

# Sandbox credentials
NETSUITE_SANDBOX_ACCOUNT_ID=11134099_SB1
NETSUITE_SANDBOX_CONSUMER_KEY=your_sandbox_key
# ... other sandbox credentials
```

**Switch environments**:
- Via Admin Dashboard: Navigate to Admin > Settings > NetSuite Environment
- Via CLI: `php scripts/switch-environment.php [production|sandbox]`

See [ENVIRONMENT_SWITCHING.md](ENVIRONMENT_SWITCHING.md) for detailed instructions.

### Email Configuration

**For Brevo (Sendinblue)**:
```env
EMAIL_PROVIDER=brevo
BREVO_API_KEY=your_api_key_here
```

**For Amazon SES**:
```env
EMAIL_PROVIDER=ses
AWS_SES_KEY=your_key
AWS_SES_SECRET=your_secret
AWS_SES_REGION=us-east-1
```

## Usage

### Initial Setup

1. **Access the portal**: Navigate to your configured URL
2. **Default admin login**: Use `admin@lagunatools.com` (configure in database)
3. **Run initial sync**: Go to Admin > Sync and run:
   - Account/User Sync
   - Purchase Order Sync
   - Item Sync

### User Login

1. Select user type (Vendor, Dealer, Buyer, or Admin)
2. Enter email address
3. Receive OTP code via email
4. Enter OTP to login

### Vendor Workflow

1. Login with vendor credentials
2. View open purchase orders
3. Click on PO to view details
4. Update dates if status allows (Pending Received/Partially Received)
5. Upload documents with comments
6. Add comments for communication
7. Changes trigger email notification to assigned buyer

### Dealer Workflow

1. Login with dealer credentials
2. Browse available items
3. Search by item name or SKU
4. Set up notifications:
   - "Notify when in stock" (for out-of-stock items)
   - "Notify when out of stock or below 10" (for in-stock items)
5. Manage notification preferences

### Buyer Workflow

1. Login with buyer credentials
2. View all purchase orders
3. Filter by status, vendor, buyer, dates
4. Review vendor updates (highlighted)
5. Approve or reject changes
6. Approved changes sync back to NetSuite

### Admin Workflow

1. Login with admin credentials
2. **Sync Management**:
   - Manual sync for accounts, users, POs, items
   - View sync logs and history
3. **User Management**:
   - Create/edit admin and buyer users
   - View user activity logs
4. **PO Management**:
   - Edit any purchase order
   - View all PO details
5. **Email Templates**:
   - Customize email templates
   - Manage template variables

## API Endpoints

### Authentication
- `POST /api/auth/send-otp.php` - Send OTP code
- `POST /api/auth/verify-otp.php` - Verify OTP and login

### Purchase Orders
- `GET /api/purchase-orders.php` - List purchase orders
- `GET /api/purchase-order.php?id={id}` - Get PO details
- `POST /api/purchase-order/update.php` - Update PO
- `POST /api/purchase-order/upload.php` - Upload document
- `POST /api/purchase-order/comment.php` - Add comment

### Items
- `GET /api/items.php` - List items
- `GET /api/item.php?id={id}` - Get item details
- `POST /api/item/notification.php` - Create notification
- `DELETE /api/item/notification.php?id={id}` - Delete notification

### Sync
- `POST /api/sync/accounts.php` - Sync accounts and users
- `POST /api/sync/purchase-orders.php` - Sync purchase orders
- `POST /api/sync/items.php` - Sync items

## Database Schema

### Main Tables

- **accounts**: Vendor and dealer accounts from NetSuite
- **users**: Portal users (admin, buyer, vendor, dealer)
- **user_accounts**: Many-to-many relationship between users and accounts
- **purchase_orders**: Purchase orders from NetSuite
- **po_items**: Line items for purchase orders
- **po_comments**: Comments on purchase orders
- **po_documents**: Uploaded documents for purchase orders
- **items**: Items for dealer portal
- **item_notifications**: Dealer notification preferences
- **otp_codes**: One-time password codes for authentication
- **email_templates**: Customizable email templates
- **sync_logs**: Sync operation logs
- **user_logs**: User activity logs

## Scheduled Tasks

Set up cron jobs for automated syncing:

```bash
# Sync accounts and users daily at 2 AM
0 2 * * * php /path/to/laguna_partner/scripts/sync-accounts.php

# Sync purchase orders every 4 hours
0 */4 * * * php /path/to/laguna_partner/scripts/sync-purchase-orders.php

# Sync items every 6 hours
0 */6 * * * php /path/to/laguna_partner/scripts/sync-items.php

# Clean up expired OTP codes daily
0 3 * * * php /path/to/laguna_partner/scripts/cleanup-otp.php
```

## Security

- OTP-based authentication (15-minute expiration)
- Session management with secure cookies
- SQL injection prevention via prepared statements
- XSS protection via output escaping
- CSRF protection (implement tokens)
- Role-based access control
- Activity logging for audit trail

## Troubleshooting

### Common Issues

**Cannot connect to database**
- Check database credentials in `.env`
- Ensure MySQL service is running
- Verify database exists

**NetSuite API errors**
- Verify OAuth credentials in `config/credentials.php`
- Check NetSuite account permissions
- Review API rate limits

**Email not sending**
- Verify email provider credentials
- Check email provider API status
- Review email logs in `logs/` directory

**OTP not received**
- Check spam/junk folder
- Verify email provider configuration
- Check application logs

### Logs

Application logs are stored in:
- `logs/app-YYYY-MM-DD.log` - Application logs
- Database `sync_logs` table - Sync operation logs
- Database `user_logs` table - User activity logs

## Development

### Directory Structure

```
laguna_partner/
├── config/              # Configuration files
├── database/            # Database schema and migrations
├── logs/                # Application logs
├── public/              # Web root
│   ├── admin/          # Admin portal pages
│   ├── buyer/          # Buyer portal pages
│   ├── dealer/         # Dealer portal pages
│   ├── vendor/         # Vendor portal pages
│   ├── api/            # API endpoints
│   ├── assets/         # CSS, JS, images
│   └── includes/       # Shared includes
├── sample_responses/    # NetSuite API sample responses
├── scripts/            # CLI scripts for cron jobs
├── src/                # PHP classes
│   ├── Auth.php
│   ├── Database.php
│   ├── EmailService.php
│   ├── NetSuiteClient.php
│   └── SyncService.php
├── uploads/            # Uploaded files
└── vendor/             # Composer dependencies
```

### Adding New Features

1. Create new PHP class in `src/`
2. Add database tables in `database/schema.sql`
3. Create API endpoints in `public/api/`
4. Add frontend pages in appropriate portal directory
5. Update navigation in `public/includes/header.php`

## Support

For issues or questions:
- Email: support@lagunatools.com
- Documentation: [Internal Wiki]

## License

Proprietary - Laguna Tools, Inc.

## Version History

### v1.0.0 (2025-01-XX)
- Initial release
- OTP authentication
- Vendor portal with PO management
- Dealer portal with item notifications
- Buyer portal with approval workflow
- Admin portal with sync management
- NetSuite integration
- Email notifications via Brevo/SES