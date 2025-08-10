<?php
// auth/logout.php
require_once '../config/database.php';
require_once '../config/functions.php';

// Kiểm tra và log hoạt động logout trước khi xóa session
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    try {
        logActivity($_SESSION['user_id'], 'logout', 'Đăng xuất');
    } catch (Exception $e) {
        // Log error nhưng vẫn tiếp tục logout
        error_log("Logout log activity error: " . $e->getMessage());
    }
}

// Xóa session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

// Redirect về trang login
header('Location: login.php');
exit();
?>