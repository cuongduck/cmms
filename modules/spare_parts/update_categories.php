<?php
/**
 * Update Categories Script
 * Tự động phân loại lại tất cả spare parts hiện có
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once 'config.php';

// Check admin permission
if (!isLoggedIn() || getCurrentUser()['role'] !== 'Admin') {
    die('Chỉ Admin mới có thể chạy script này');
}

echo "<h2>Tự động phân loại lại Spare Parts</h2>";

try {
    $db->beginTransaction();
    
    // Lấy tất cả spare parts chưa có category hoặc category = 'Vật tư khác'
    $spareParts = $db->fetchAll("
        SELECT id, item_code, item_name, category 
        FROM spare_parts 
        WHERE is_active = 1 
        AND (category IS NULL OR category = '' OR category = 'Vật tư khác')
        ORDER BY item_code
    ");
    
    echo "<p>Tìm thấy " . count($spareParts) . " spare parts cần phân loại lại</p>";
    
    $updated = 0;
    $unchanged = 0;
    
    foreach ($spareParts as $part) {
        $detectedCategory = autoDetectCategory($part['item_name']);
        
        if ($detectedCategory && $detectedCategory !== 'Vật tư khác') {
            $db->execute("
                UPDATE spare_parts 
                SET category = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$detectedCategory, $part['id']]);
            
            echo "<p style='color: green;'>Cập nhật: {$part['item_code']} - {$part['item_name']} → {$detectedCategory}</p>";
            $updated++;
        } else {
            echo "<p style='color: orange;'>Giữ nguyên: {$part['item_code']} - {$part['item_name']} → Vật tư khác</p>";
            $unchanged++;
        }
    }
    
    $db->commit();
    
    echo "<h3 style='color: green;'>Hoàn thành!</h3>";
    echo "<p>Đã cập nhật: {$updated} spare parts</p>";
    echo "<p>Giữ nguyên: {$unchanged} spare parts</p>";
    
} catch (Exception $e) {
    $db->rollback();
    echo "<p style='color: red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>

<br>
<a href="index.php" class="btn btn-primary">Quay lại danh sách</a>
<a href="category_keywords.php" class="btn btn-info">Quản lý từ khóa</a>