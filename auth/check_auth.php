<?php
// auth/check_auth.php - File kiểm tra quyền truy cập cho API
require_once '../config/database.php';
require_once '../config/functions.php';

// Kiểm tra AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Access denied');
}

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn'], 401);
}

// Hàm kiểm tra quyền cho API
function checkApiPermission($required_roles) {
    if (!hasPermission($required_roles)) {
        jsonResponse(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này'], 403);
    }
}
?>
