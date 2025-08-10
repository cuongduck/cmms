<?php
// modules/users/api.php - API quản lý users (chỉ admin)
require_once '../../auth/check_auth.php';
checkApiPermission('admin');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listUsers();
        break;
    case 'create':
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'toggle_status':
        toggleUserStatus();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listUsers() {
    global $db;
    
    $page = $_GET['page'] ?? 1;
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($role)) {
        $where_conditions[] = "role = ?";
        $params[] = $role;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Đếm tổng số records
    $count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Lấy dữ liệu phân trang
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT id, username, full_name, email, phone, role, is_active, last_login, created_at 
            FROM users $where_clause 
            ORDER BY created_at DESC 
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $users,
        'pagination' => $pagination
    ]);
}

function createUser() {
    global $db;
    
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];
    $full_name = cleanInput($_POST['full_name']);
    $email = cleanInput($_POST['email']);
    $phone = cleanInput($_POST['phone']);
    $role = $_POST['role'];
    
    // Validate
    if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
    }
    
    // Kiểm tra username đã tồn tại
    $check_stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->execute([$username]);
    if ($check_stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại'], 400);
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role]);
        
        logActivity($_SESSION['user_id'], 'create_user', "Tạo user: $username");
        
        jsonResponse(['success' => true, 'message' => 'Tạo người dùng thành công']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi tạo người dùng: ' . $e->getMessage()], 500);
    }
}

function updateUser() {
    global $db;
    
    $id = $_POST['id'];
    $username = cleanInput($_POST['username']);
    $full_name = cleanInput($_POST['full_name']);
    $email = cleanInput($_POST['email']);
    $phone = cleanInput($_POST['phone']);
    $role = $_POST['role'];
    $password = $_POST['password'] ?? '';
    
    if (empty($id) || empty($username) || empty($full_name) || empty($role)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
    }
    
    try {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $full_name, $email, $phone, $role, $id]);
        }
        
        logActivity($_SESSION['user_id'], 'update_user', "Cập nhật user ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật người dùng thành công']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật người dùng: ' . $e->getMessage()], 500);
    }
}

function deleteUser() {
    global $db;
    
    $id = $_POST['id'];
    
    if ($id == $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Không thể xóa tài khoản đang đăng nhập'], 400);
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_user', "Xóa user ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Xóa người dùng thành công']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa người dùng: ' . $e->getMessage()], 500);
    }
}

function toggleUserStatus() {
    global $db;
    
    $id = $_POST['id'];
    
    if ($id == $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Không thể thay đổi trạng thái tài khoản đang đăng nhập'], 400);
    }
    
    try {
        $stmt = $db->prepare("UPDATE users SET is_active = !is_active WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'toggle_user_status', "Thay đổi trạng thái user ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Thay đổi trạng thái thành công']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi thay đổi trạng thái: ' . $e->getMessage()], 500);
    }
}
?>