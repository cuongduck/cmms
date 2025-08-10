<?php
// config/functions.php - Thêm vào đầu file

// Cấu hình session an toàn
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Đổi thành 1 nếu dùng HTTPS

// Khởi tạo session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hàm kiểm tra đăng nhập
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Hàm kiểm tra quyền
function hasPermission($required_roles) {
    if (!isLoggedIn()) return false;
    
    $user_role = $_SESSION['user_role'];
    
    // Admin có full quyền
    if ($user_role === 'admin') return true;
    
    // Kiểm tra role cụ thể
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles);
    }
    
    return $user_role === $required_roles;
}

// Hàm redirect nếu không có quyền
function requirePermission($required_roles) {
    if (!hasPermission($required_roles)) {
        header('Location: /auth/login.php');
        exit();
    }
}

// Hàm sanitize input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Hàm format ngày tháng
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

// Hàm tính số ngày còn lại hoặc quá hạn
function getDaysRemaining($target_date) {
    if (!$target_date) return null;
    
    $today = new DateTime();
    $target = new DateTime($target_date);
    $diff = $today->diff($target);
    
    if ($target < $today) {
        return -$diff->days; // Số âm = quá hạn
    } else {
        return $diff->days; // Số dương = còn lại
    }
}

// Hàm hiển thị status badge
function getStatusBadge($days_remaining) {
    if ($days_remaining === null) return '';
    
    if ($days_remaining < 0) {
        return '<span class="badge bg-danger">Quá hạn ' . abs($days_remaining) . ' ngày</span>';
    } elseif ($days_remaining <= 7) {
        return '<span class="badge bg-warning">Còn ' . $days_remaining . ' ngày</span>';
    } elseif ($days_remaining <= 30) {
        return '<span class="badge bg-info">Còn ' . $days_remaining . ' ngày</span>';
    } else {
        return '<span class="badge bg-success">Còn ' . $days_remaining . ' ngày</span>';
    }
}

// Hàm upload file
function uploadFile($file, $upload_dir = 'uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Không có file được upload'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Định dạng file không được phép'];
    }

    // Tạo tên file duy nhất
    $file_name = uniqid() . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $file_name;

    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'file_path' => $upload_path];
    } else {
        return ['success' => false, 'message' => 'Lỗi upload file'];
    }
}

// Hàm tạo QR code
function generateQRCode($data, $size = 200) {
    // Sử dụng Google Chart API để tạo QR
    $base_url = "https://chart.googleapis.com/chart";
    $params = [
        'chs' => $size . 'x' . $size,
        'cht' => 'qr',
        'chl' => urlencode($data)
    ];
    
    return $base_url . '?' . http_build_query($params);
}

// Hàm gửi JSON response
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Hàm log hoạt động
function logActivity($user_id, $action, $details = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

// Hàm phân trang
function getPagination($current_page, $total_records, $records_per_page = 10) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_records' => $total_records,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

// Hàm hiển thị thông báo flash
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Constants
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('ITEMS_PER_PAGE', 10);
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
?>