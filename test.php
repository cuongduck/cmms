<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$basePath = '/home/pnlekoychosting/public_html/vendor/phpoffice/PhpSpreadsheet';

echo "Testing PhpSpreadsheet...<br>";
echo "Base path: $basePath<br>";

if (file_exists($basePath . '/IOFactory.php')) {
    echo "IOFactory.php exists<br>";
    require_once $basePath . '/IOFactory.php';
    echo "IOFactory loaded<br>";
} else {
    echo "ERROR: IOFactory.php NOT found<br>";
}

echo "Class exists: " . (class_exists('PhpOffice\PhpSpreadsheet\IOFactory') ? 'YES' : 'NO');
?>