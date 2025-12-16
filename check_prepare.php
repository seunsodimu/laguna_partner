<?php
$content = file_get_contents('public/api/invoices.php');
$count = substr_count($content, '->prepare(');
echo 'Number of prepare() calls found: ' . $count . PHP_EOL;
if ($count > 0) {
    $lines = explode(PHP_EOL, $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, '->prepare(') !== false) {
            echo 'Line ' . ($i + 1) . ': ' . trim($line) . PHP_EOL;
        }
    }
}
?>