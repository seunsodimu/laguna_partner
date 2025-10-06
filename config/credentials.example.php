<?php
/**
 * API Credentials Configuration
 * 
 * Copy this file to credentials.php and fill in your actual API credentials.
 * Never commit credentials.php to version control!
 */

return [
    // NetSuite API Credentials
    // Switch between 'production' and 'sandbox' using NETSUITE_ENVIRONMENT in .env
    'netsuite' => [
        'production' => [
            'account_id' => $_ENV['NETSUITE_PROD_ACCOUNT_ID'] ?? 'your-production-account-id',
            'consumer_key' => $_ENV['NETSUITE_PROD_CONSUMER_KEY'] ?? 'your-production-consumer-key',
            'consumer_secret' => $_ENV['NETSUITE_PROD_CONSUMER_SECRET'] ?? 'your-production-consumer-secret',
            'token_id' => $_ENV['NETSUITE_PROD_TOKEN_ID'] ?? 'your-production-token-id',
            'token_secret' => $_ENV['NETSUITE_PROD_TOKEN_SECRET'] ?? 'your-production-token-secret',
            'rest_url' => $_ENV['NETSUITE_PROD_BASE_URL'] ?? 'https://your-account-id.suitetalk.api.netsuite.com',
            'rest_api_version' => 'v1',
            'signature_method' => 'HMAC-SHA256',
            'environment' => 'production'
        ],
        'sandbox' => [
            'account_id' => $_ENV['NETSUITE_SANDBOX_ACCOUNT_ID'] ?? 'your-sandbox-account-id',
            'consumer_key' => $_ENV['NETSUITE_SANDBOX_CONSUMER_KEY'] ?? 'your-sandbox-consumer-key',
            'consumer_secret' => $_ENV['NETSUITE_SANDBOX_CONSUMER_SECRET'] ?? 'your-sandbox-consumer-secret',
            'token_id' => $_ENV['NETSUITE_SANDBOX_TOKEN_ID'] ?? 'your-sandbox-token-id',
            'token_secret' => $_ENV['NETSUITE_SANDBOX_TOKEN_SECRET'] ?? 'your-sandbox-token-secret',
            'rest_url' => $_ENV['NETSUITE_SANDBOX_BASE_URL'] ?? 'https://your-sandbox-account-id.suitetalk.api.netsuite.com',
            'rest_api_version' => 'v1',
            'signature_method' => 'HMAC-SHA256',
            'environment' => 'sandbox'
        ]
    ],

    // Email Service Configuration
    'email' => [
        // Choose your email provider: 'brevo' or 'ses'
        'provider' => $_ENV['EMAIL_PROVIDER'] ?? 'brevo', // Options: 'brevo', 'ses'
        
        // Brevo (formerly SendinBlue) API Credentials
        'brevo' => [
            'api_key' => $_ENV['BREVO_API_KEY'] ?? 'your-brevo-api-key',
            'from_email' => $_ENV['BREVO_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
            'from_name' => $_ENV['BREVO_FROM_NAME'] ?? 'Laguna Partners Portal',
        ],
        
        // Amazon SES API Credentials
        'ses' => [
            'region' => $_ENV['AWS_SES_REGION'] ?? 'us-east-1',
            'access_key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? 'your-aws-access-key',
            'secret_key' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? 'your-aws-secret-key',
            'from_email' => $_ENV['SES_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
            'from_name' => $_ENV['SES_FROM_NAME'] ?? 'Laguna Partners Portal',
        ],
    ],
];