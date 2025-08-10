<?php
// api/change-password.php - API đổi mật khẩu
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin'], 400);
}

if (strlen($new_password) < 6) {
    jsonResponse(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự'], 400);
}

try {
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Mật khẩu hiện tại không chính xác'], 400);
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'change_password', 'Đổi mật khẩu thành công');
    
    jsonResponse(['success' => true, 'message' => 'Đổi mật khẩu thành công']);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Lỗi hệ thống'], 500);
}
?>