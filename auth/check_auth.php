<?php
// auth/check_auth.php - Fixed paths version
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Sử dụng absolute path từ document root
$root_path = dirname(dirname(__FILE__)); // /var/www/html

require_once $root_path . '/config/database.php';
require_once $root_path . '/config/functions.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Request analysis
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$is_api_call = strpos($_SERVER['REQUEST_URI'], '/api.php') !== false;

// Minimal AJAX check - chỉ block những action thực sự nguy hiểm
if ($is_api_call) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Chỉ block delete action không có AJAX header
    if ($action === 'delete' && $method === 'POST' && !$is_ajax) {
        error_log("BLOCKED: Delete action without AJAX header");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // Log API calls
    error_log("API Call: method=$method, action=$action, ajax=" . ($is_ajax ? 'yes' : 'no'));
}

// Login check
if (!isLoggedIn()) {
    error_log("User not logged in");
    
    if ($is_ajax || $is_api_call) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Phiên đăng nhập đã hết hạn',
            'redirect' => '/auth/login.php'
        ]);
        exit();
    } else {
        // Tính toán đường dẫn login đúng
        $login_path = '/auth/login.php';
        header('Location: ' . $login_path);
        exit();
    }
}

// Permission check function
function checkApiPermission($required_roles) {
    if (!hasPermission($required_roles)) {
        $user_role = $_SESSION['user_role'] ?? 'unknown';
        $required_str = is_array($required_roles) ? implode(',', $required_roles) : $required_roles;
        
        error_log("Permission denied - user_role: $user_role, required: $required_str");
        
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Bạn không có quyền thực hiện hành động này'
        ]);
        exit();
    }
}

// Set JSON header for API calls
if ($is_api_call) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}
?>