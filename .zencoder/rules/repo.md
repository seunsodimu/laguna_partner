---
description: Repository Information Overview
alwaysApply: true
---

# Laguna Partners Portal Information

## Summary
A comprehensive vendor and dealer portal that integrates with NetSuite to manage purchase orders, inventory, and user access. The portal features OTP-based authentication and provides different access levels for vendors, dealers, buyers, and administrators.

## Structure
- **config/**: Configuration files including credentials and app settings
- **database/**: Database schema and SQL files
- **docker/**: Docker configuration files including crontab
- **logs/**: Application logs directory
- **public/**: Web root with frontend files and API endpoints
- **sample_responses/**: Sample NetSuite API responses
- **scripts/**: CLI scripts for cron jobs and sync operations
- **src/**: PHP classes for core functionality
- **uploads/**: Directory for uploaded files

## Language & Runtime
**Language**: PHP
**Version**: 8.1 (based on Dockerfile)
**Database**: MySQL 8.0
**Frontend**: Bootstrap 5, JavaScript
**Build System**: Composer
**Package Manager**: Composer

## Dependencies
**Main Dependencies**:
- vlucas/phpdotenv (^5.5): Environment variable management
- ext-pdo: PHP PDO extension for database access
- ext-json: PHP JSON extension
- ext-curl: PHP cURL extension for API requests

**Development Dependencies**:
- phpunit/phpunit (^10.0): Testing framework

## Build & Installation
```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
cp config/credentials.example.php config/credentials.php

# Create database
mysql -u root -p -e "CREATE DATABASE laguna_partner;"
mysql -u root -p laguna_partner < database/schema.sql
```

## Docker
**Dockerfile**: Dockerfile
**Image**: PHP 8.1 Apache
**Configuration**: 
- Includes cron for scheduled tasks
- Exposes port 80
- Mounts volumes for logs and uploads
- Includes MySQL 8.0 and phpMyAdmin

**Docker Compose**:
```bash
# Start containers
docker-compose up -d
```

## API Integration
**NetSuite REST API**: OAuth 1.0 authentication
**Email Services**: Brevo (Sendinblue) or Amazon SES
**Environment Switching**: Supports switching between production and sandbox

## Main Files
**Entry Point**: public/index.php
**Core Classes**:
- src/Auth.php: Authentication management
- src/Database.php: Database connection handling
- src/EmailService.php: Email notification service
- src/NetSuiteClient.php: NetSuite API client
- src/SyncService.php: Data synchronization service

## Scheduled Tasks
**Cron Jobs**:
- Daily account sync (2 AM)
- Purchase order sync every 4 hours
- Item sync every 6 hours
- OTP cleanup daily (3 AM)

## User Types
- **Vendor**: View and update purchase orders
- **Dealer**: View available items and inventory
- **Buyer**: View and manage purchase orders
- **Admin**: Manage users, sync data, and system settings