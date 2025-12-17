#!/bin/bash

# Laguna Partners Portal - Setup Script
# This script automates the initial setup process

set -e

echo "=========================================="
echo "Laguna Partners Portal - Setup Script"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
    echo -e "${RED}Please do not run this script as root${NC}"
    exit 1
fi

# Check PHP version
echo -e "${YELLOW}Checking PHP version...${NC}"
PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "0")
if [ "$PHP_VERSION" == "0" ]; then
    echo -e "${RED}PHP is not installed. Please install PHP 8.0 or higher.${NC}"
    exit 1
fi

PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

if [ "$PHP_MAJOR" -lt 8 ]; then
    echo -e "${RED}PHP 8.0 or higher is required. Current version: $PHP_VERSION${NC}"
    exit 1
fi

echo -e "${GREEN}✓ PHP $PHP_VERSION detected${NC}"

# Check Composer
echo -e "${YELLOW}Checking Composer...${NC}"
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Composer is not installed. Please install Composer first.${NC}"
    echo "Visit: https://getcomposer.org/download/"
    exit 1
fi
echo -e "${GREEN}✓ Composer detected${NC}"

# Check MySQL
echo -e "${YELLOW}Checking MySQL...${NC}"
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}⚠ MySQL client not found. Make sure MySQL server is installed and running.${NC}"
else
    echo -e "${GREEN}✓ MySQL detected${NC}"
fi

# Install dependencies
echo ""
echo -e "${YELLOW}Installing PHP dependencies...${NC}"
composer install

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi

# Setup environment file
echo ""
echo -e "${YELLOW}Setting up environment file...${NC}"
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}✓ .env file created from .env.example${NC}"
        echo -e "${YELLOW}⚠ Please edit .env file with your configuration${NC}"
    else
        echo -e "${RED}✗ .env.example not found${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

# Create directories
echo ""
echo -e "${YELLOW}Creating required directories...${NC}"
mkdir -p uploads/po_documents
mkdir -p logs
mkdir -p cache

echo -e "${GREEN}✓ Directories created${NC}"

# Set permissions
echo ""
echo -e "${YELLOW}Setting permissions...${NC}"
chmod -R 755 .
chmod -R 777 uploads
chmod -R 777 logs
chmod -R 777 cache

echo -e "${GREEN}✓ Permissions set${NC}"

# Database setup
echo ""
echo -e "${YELLOW}Database Setup${NC}"
echo "Do you want to setup the database now? (y/n)"
read -r SETUP_DB

if [ "$SETUP_DB" == "y" ] || [ "$SETUP_DB" == "Y" ]; then
    echo "Enter MySQL root password:"
    read -s MYSQL_ROOT_PASS
    
    echo "Enter database name (default: laguna_partner):"
    read DB_NAME
    DB_NAME=${DB_NAME:-laguna_partner}
    
    echo "Enter database user (default: laguna_user):"
    read DB_USER
    DB_USER=${DB_USER:-laguna_user}
    
    echo "Enter database password:"
    read -s DB_PASS
    
    echo ""
    echo -e "${YELLOW}Creating database...${NC}"
    
    mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database created${NC}"
        
        echo -e "${YELLOW}Importing schema...${NC}"
        mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < database/schema.sql
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Schema imported${NC}"
            
            # Update .env file
            sed -i "s/DB_NAME=.*/DB_NAME=$DB_NAME/" .env
            sed -i "s/DB_USER=.*/DB_USER=$DB_USER/" .env
            sed -i "s/DB_PASS=.*/DB_PASS=$DB_PASS/" .env
            
            echo -e "${GREEN}✓ .env file updated with database credentials${NC}"
        else
            echo -e "${RED}✗ Failed to import schema${NC}"
        fi
    else
        echo -e "${RED}✗ Failed to create database${NC}"
    fi
fi

# Summary
echo ""
echo "=========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Edit .env file with your NetSuite and email credentials"
echo "2. Run initial sync: php scripts/sync-accounts.php"
echo "3. Start development server: cd public && php -S localhost:8000"
echo "4. Access the portal at: http://localhost:8000"
echo ""
echo "For production deployment, see DEPLOYMENT.md"
echo ""
echo "Default admin login:"
echo "  Email: admin@lagunatools.com"
echo "  (Change this in the database after first login)"
echo ""
echo -e "${YELLOW}⚠ Remember to configure NetSuite API credentials in .env${NC}"
echo -e "${YELLOW}⚠ Remember to configure email provider (Brevo or SES) in .env${NC}"
echo ""