<?php
require 'src/Database.php';
use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    $columns = $db->fetchAll("DESCRIBE users");
    echo "Users table columns:\n";
    foreach($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
