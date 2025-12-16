<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

$constraints = $db->fetchAll(
    "SELECT CONSTRAINT_NAME, COLUMN_NAME 
     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_NAME = 'invoices' 
     AND CONSTRAINT_SCHEMA = DATABASE()
     ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION"
);

echo "Unique constraints on invoices table:\n";
echo "=====================================\n\n";

$currentConstraint = '';
foreach ($constraints as $row) {
    if ($row['CONSTRAINT_NAME'] !== $currentConstraint) {
        if ($currentConstraint !== '') {
            echo "\n";
        }
        $currentConstraint = $row['CONSTRAINT_NAME'];
        echo "Constraint: " . $row['CONSTRAINT_NAME'] . "\n";
        echo "  Columns: ";
    } else {
        echo ", ";
    }
    echo $row['COLUMN_NAME'];
}
echo "\n\n✓ Verification complete!\n";
?>
