<?php
// auth/check_auth.php - File kiểm tra quyền truy cập cho API
require_once '../config/database.php';
require_once '../config/functions.php';

// Debug logging
error_log("Check auth called. Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));

// Kiểm tra AJAX request - RELAXED CHECK
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$is_api_call = strpos($_SERVER['REQUEST_URI'], '/api.php') !== false;

// Chỉ kiểm tra strict cho API calls thực sự quan trọng
if (!$is_ajax && $is_api_call) {
    // Cho phép các GET requests đơn giản
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $allowed_actions = ['get_xuong', 'get_lines', 'get_khu_vuc', 'get_dong_may', 'list_simple', 'search_by_id'];
        $action = $_GET['action'] ?? '';
        
        if (!in_array($action, $allowed_actions)) {
            error_log("Blocked non-AJAX request for action: " . $action);
            http_response_code(403);
            exit('Access denied');
        }
    } else {
        // POST requests phải có AJAX header
        error_log("Blocked non-AJAX POST request");
        http_response_code(403);
        exit('Access denied');
    }
}

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    error_log("User not logged in");
    if ($is_ajax || $is_api_call) {
        jsonResponse(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn'], 401);
    } else {
        header('Location: ../auth/login.php');
        exit();
    }
}

// Log successful auth
error_log("Auth successful for user: " . $_SESSION['user_id']);

// Hàm kiểm tra quyền cho API
function checkApiPermission($required_roles) {
    if (!hasPermission($required_roles)) {
        error_log("Permission denied for user: " . $_SESSION['user_id'] . ", required: " . implode(',', (array)$required_roles));
        jsonResponse(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này'], 403);
    }
}
?>