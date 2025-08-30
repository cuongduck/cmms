<?php
// File: /test_debug.php
// File kiểm tra lỗi 500 cho module BOM

// Bật hiển thị lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Hàm ghi log lỗi
function log_error($message) {
    error_log($message, 3, '/var/www/html/baotricf.iot-mmb.site/debug.log');
    echo "<p style='color: red;'>$message</p>";
}

// 1. Kiểm tra các file require
echo "<h3>Kiểm tra file require</h3>";
try {
    // Kiểm tra BASE_PATH
    define('BASE_PATH', dirname(__DIR__));
    echo "<p>BASE_PATH: " . BASE_PATH . "</p>";

    // Kiểm tra các file config
    $files_to_check = [
        BASE_PATH . '/config/config.php',
        BASE_PATH . '/config/database.php',
        BASE_PATH . '/config/auth.php',
        BASE_PATH . '/config/functions.php',
        BASE_PATH . '/modules/bom/config.php',
        BASE_PATH . '/includes/header.php',
        BASE_PATH . '/includes/footer.php'
    ];

    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo "<p>File $file tồn tại.</p>";
            try {
                require_once $file;
                echo "<p>Require $file thành công.</p>";
            } catch (Exception $e) {
                log_error("Lỗi khi require $file: " . $e->getMessage());
            }
        } else {
            log_error("File $file không tồn tại.");
        }
    }
} catch (Exception $e) {
    log_error("Lỗi khi kiểm tra file: " . $e->getMessage());
}

// 2. Kiểm tra kết nối database
echo "<h3>Kiểm tra kết nối database</h3>";
try {
    require_once BASE_PATH . '/config/database.php';
    $db = Database::getInstance();
    echo "<p>Kết nối database thành công!</p>";

    // Kiểm tra các bảng cần thiết
    $tables = [
        'machine_types',
        'machine_bom',
        'parts',
        'part_inventory_mapping',
        'onhand',
        'bom_items',
        'users',
        'module_permissions'
    ];

    foreach ($tables as $table) {
        $sql = "SHOW TABLES LIKE '$table'";
        $result = $db->fetch($sql);
        if ($result) {
            echo "<p>Bảng $table tồn tại.</p>";
        } else {
            log_error("Bảng $table không tồn tại.");
        }
    }
} catch (Exception $e) {
    log_error("Lỗi kết nối database: " . $e->getMessage());
}

// 3. Kiểm tra truy vấn SQL trong index.php
echo "<h3>Kiểm tra truy vấn SQL</h3>";
try {
    // Truy vấn lấy machine_types
    $sql = "SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name";
    $machineTypes = $db->fetchAll($sql);
    echo "<p>Truy vấn machine_types thành công. Số bản ghi: " . count($machineTypes) . "</p>";

    // Truy vấn thống kê
    $stats_queries = [
        'total_boms' => "SELECT COUNT(*) as count FROM machine_bom",
        'total_parts' => "SELECT COUNT(*) as count FROM parts",
        'total_machine_types' => "SELECT COUNT(*) as count FROM machine_types WHERE status = 'active'",
        'low_stock_parts' => "SELECT COUNT(*) as count 
                            FROM parts p 
                            LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
                            LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
                            WHERE COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0"
    ];

    foreach ($stats_queries as $key => $sql) {
        try {
            $result = $db->fetch($sql);
            echo "<p>Truy vấn $key thành công: " . $result['count'] . "</p>";
        } catch (Exception $e) {
            log_error("Lỗi truy vấn $key: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    log_error("Lỗi khi thực hiện truy vấn SQL: " . $e->getMessage());
}

// 4. Kiểm tra hàm hasPermission
echo "<h3>Kiểm tra hàm hasPermission</h3>";
try {
    require_once BASE_PATH . '/config/auth.php';
    session_start();
    $_SESSION['user_role'] = 'Admin'; // Giả lập Admin để test
    if (hasPermission('bom', 'view')) {
        echo "<p>Hàm hasPermission('bom', 'view') hoạt động.</p>";
    } else {
        echo "<p>Hàm hasPermission('bom', 'view') trả về false.</p>";
    }
} catch (Exception $e) {
    log_error("Lỗi khi kiểm tra hasPermission: " . $e->getMessage());
}

echo "<h3>Kiểm tra hoàn tất</h3>";
echo "<p>Xem thêm chi tiết lỗi trong file /var/www/html/baotricf.iot-mmb.site/debug.log</p>";
?>