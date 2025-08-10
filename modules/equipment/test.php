<?php
// test.php - đặt trong thư mục modules/equipment/
require_once '../../config/database.php';
require_once '../../config/functions.php';

echo "Testing database connection...\n";

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM xuong");
    $result = $stmt->fetch();
    echo "Xuong count: " . $result['count'] . "\n";
    
    $stmt = $db->query("SELECT id, ten_xuong FROM xuong LIMIT 5");
    $xuong = $stmt->fetchAll();
    echo "Xuong data: " . json_encode($xuong) . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>