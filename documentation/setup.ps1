# Laguna Partners Portal - Windows Setup Script
# This script sets up the application on Windows with XAMPP

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Laguna Partners Portal - Setup Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running in the correct directory
if (-not (Test-Path "composer.json")) {
    Write-Host "Error: Please run this script from the project root directory" -ForegroundColor Red
    exit 1
}

# Check PHP version
Write-Host "Checking PHP version..." -ForegroundColor Yellow
try {
    $phpVersion = php -r "echo PHP_VERSION;"
    Write-Host "PHP version: $phpVersion" -ForegroundColor Green
    
    $versionParts = $phpVersion.Split('.')
    $majorVersion = [int]$versionParts[0]
    
    if ($majorVersion -lt 8) {
        Write-Host "Error: PHP 8.0 or higher is required. Current version: $phpVersion" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "Error: PHP is not installed or not in PATH" -ForegroundColor Red
    exit 1
}

# Check Composer
Write-Host "Checking Composer..." -ForegroundColor Yellow
try {
    $composerVersion = composer --version
    Write-Host "Composer is installed" -ForegroundColor Green
} catch {
    Write-Host "Error: Composer is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install Composer from https://getcomposer.org/" -ForegroundColor Yellow
    exit 1
}

# Install dependencies
Write-Host ""
Write-Host "Installing PHP dependencies..." -ForegroundColor Yellow
composer install --no-dev

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to install dependencies" -ForegroundColor Red
    exit 1
}

# Create .env file if it doesn't exist
if (-not (Test-Path ".env")) {
    Write-Host ""
    Write-Host "Creating .env file from .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
    Write-Host ".env file created successfully" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host ".env file already exists" -ForegroundColor Green
}

# Create required directories
Write-Host ""
Write-Host "Creating required directories..." -ForegroundColor Yellow

$directories = @("uploads", "logs", "cache")
foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created directory: $dir" -ForegroundColor Green
    } else {
        Write-Host "Directory already exists: $dir" -ForegroundColor Green
    }
}

# Database setup
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Database Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$setupDb = Read-Host "Do you want to set up the database now? (y/n)"

if ($setupDb -eq "y" -or $setupDb -eq "Y") {
    $dbHost = Read-Host "Enter database host (default: localhost)"
    if ([string]::IsNullOrWhiteSpace($dbHost)) { $dbHost = "localhost" }
    
    $dbPort = Read-Host "Enter database port (default: 3306)"
    if ([string]::IsNullOrWhiteSpace($dbPort)) { $dbPort = "3306" }
    
    $dbName = Read-Host "Enter database name (default: laguna_partner)"
    if ([string]::IsNullOrWhiteSpace($dbName)) { $dbName = "laguna_partner" }
    
    $dbUser = Read-Host "Enter database username (default: root)"
    if ([string]::IsNullOrWhiteSpace($dbUser)) { $dbUser = "root" }
    
    $dbPass = Read-Host "Enter database password (leave empty for no password)" -AsSecureString
    $dbPassPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($dbPass))
    
    Write-Host ""
    Write-Host "Creating database..." -ForegroundColor Yellow
    
    # Create database
    $createDbQuery = "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    if ([string]::IsNullOrWhiteSpace($dbPassPlain)) {
        mysql -h $dbHost -P $dbPort -u $dbUser -e $createDbQuery
    } else {
        mysql -h $dbHost -P $dbPort -u $dbUser -p$dbPassPlain -e $createDbQuery
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Database created successfully" -ForegroundColor Green
        
        # Import schema
        Write-Host "Importing database schema..." -ForegroundColor Yellow
        
        if ([string]::IsNullOrWhiteSpace($dbPassPlain)) {
            Get-Content "database\schema.sql" | mysql -h $dbHost -P $dbPort -u $dbUser $dbName
        } else {
            Get-Content "database\schema.sql" | mysql -h $dbHost -P $dbPort -u $dbUser -p$dbPassPlain $dbName
        }
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Database schema imported successfully" -ForegroundColor Green
            
            # Update .env file
            Write-Host "Updating .env file with database credentials..." -ForegroundColor Yellow
            
            $envContent = Get-Content ".env" -Raw
            $envContent = $envContent -replace "DB_HOST=.*", "DB_HOST=$dbHost"
            $envContent = $envContent -replace "DB_PORT=.*", "DB_PORT=$dbPort"
            $envContent = $envContent -replace "DB_NAME=.*", "DB_NAME=$dbName"
            $envContent = $envContent -replace "DB_USER=.*", "DB_USER=$dbUser"
            $envContent = $envContent -replace "DB_PASS=.*", "DB_PASS=$dbPassPlain"
            
            Set-Content ".env" $envContent
            Write-Host ".env file updated successfully" -ForegroundColor Green
        } else {
            Write-Host "Error: Failed to import database schema" -ForegroundColor Red
        }
    } else {
        Write-Host "Error: Failed to create database" -ForegroundColor Red
    }
}

# Final instructions
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Update .env file with your NetSuite API credentials" -ForegroundColor White
Write-Host "2. Update .env file with your email provider credentials (Brevo or AWS SES)" -ForegroundColor White
Write-Host "3. Configure your web server to point to the 'public' directory" -ForegroundColor White
Write-Host "4. Access the application at http://localhost/laguna_partner/public" -ForegroundColor White
Write-Host "5. Default admin login: admin@lagunatools.com" -ForegroundColor White
Write-Host ""
Write-Host "For more information, see README.md and DEPLOYMENT.md" -ForegroundColor Cyan
Write-Host ""