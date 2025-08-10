<?php
// auth/logout.php
require_once '../config/functions.php';

if (isLoggedIn()) {
    // Log hoạt động logout
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'Đăng xuất');
    }
    
    // Xóa session
    session_unset();
    session_destroy();
}

header('Location: login.php');
exit();
?>
