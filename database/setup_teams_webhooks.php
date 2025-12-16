<?php
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    
    echo "Setting up Teams webhook configuration...\n\n";
    
    echo "Step 1: Creating teams_webhook_config table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `teams_webhook_config` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `notification_type` VARCHAR(100) NOT NULL UNIQUE,
        `webhook_url` LONGTEXT NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_notification_type` (`notification_type`),
        INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    echo "✓ Table created (or already exists)\n\n";
    
    echo "Step 2: Inserting webhook notification types...\n";
    $types = [
        'po_vendor_update' => 'PO vendor updates',
        'invoice_submitted' => 'Invoice submissions',
        'message_accounting' => 'Messages to accounting team',
        'message_buyer' => 'Messages to buyer team'
    ];
    
    foreach ($types as $type => $desc) {
        try {
            $existing = $db->fetchOne(
                "SELECT id FROM teams_webhook_config WHERE notification_type = ?",
                [$type]
            );
            
            if ($existing) {
                echo "  - Skipped: '$type' (already exists)\n";
            } else {
                $db->insert('teams_webhook_config', [
                    'notification_type' => $type,
                    'webhook_url' => '',
                    'is_active' => 0
                ]);
                echo "  ✓ Added: '$type'\n";
            }
        } catch (\Exception $e) {
            echo "  ✗ Error with '$type': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Teams webhook configuration complete!\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Go to Admin Dashboard > Teams Webhook Configuration\n";
    echo "2. Add your Teams webhook URLs for messaging notifications:\n";
    echo "   - message_accounting: Notifications for accounting team messages\n";
    echo "   - message_buyer: Notifications for buyer team messages\n";
    echo "3. Test each webhook URL to verify it's working\n";
    echo "4. Enable the webhooks\n";
    echo "5. Now when vendors send messages, Teams notifications will be triggered\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
