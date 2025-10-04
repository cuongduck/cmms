<?php
/**
 * Authentication System
 * CMMS User Authentication and Authorization
 */

require_once 'database.php';
require_once 'config.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Đăng nhập user
     */
    public function login($username, $password, $remember = false) {
        try {
            $sql = "SELECT id, username, password, full_name, email, role, status 
                   FROM users WHERE username = ? AND status = 'active'";
            $user = $this->db->fetch($sql, [$username]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Tên đăng nhập không tồn tại'];
            }
            
            if (!password_verify($password, $user['password'])) {
                $this->logFailedLogin($username);
                return ['success' => false, 'message' => 'Mật khẩu không chính xác'];
            }
            
            // Khởi tạo session
            $this->startSession($user);
            
            // XỬ LÝ GHI NHỚ MẬT KHẨU
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $hashedToken = hash('sha256', $token);
                $expiry = time() + (30 * 24 * 60 * 60); // 30 ngày
                
                // Lưu token vào DB
                $sqlToken = "INSERT INTO remember_tokens (user_id, token, expires_at) 
                            VALUES (?, ?, FROM_UNIXTIME(?))
                            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)";
                $this->db->execute($sqlToken, [$user['id'], $hashedToken, $expiry]);
                
                // Set cookie
                setcookie('remember_token', $token, [
                    'expires' => $expiry,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            // Cập nhật thời gian đăng nhập cuối
            $this->updateLastLogin($user['id']);
            
            return ['success' => true, 'message' => 'Đăng nhập thành công', 'user' => $user];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
        }
    }
    
    /**
     * Kiểm tra và tự động đăng nhập từ cookie
     */
    public function checkRememberToken() {
        // Nếu đã login rồi thì không cần check
        if ($this->isLoggedIn()) {
            return true;
        }
        
        // Kiểm tra cookie remember_token
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        try {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            // Tìm token trong DB
            $sql = "SELECT rt.*, u.id, u.username, u.full_name, u.email, u.role, u.status
                   FROM remember_tokens rt
                   JOIN users u ON rt.user_id = u.id
                   WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active'";
            
            $result = $this->db->fetch($sql, [$hashedToken]);
            
            if (!$result) {
                // Token không hợp lệ, xóa cookie
                $this->clearRememberCookie();
                return false;
            }
            
            // Token hợp lệ, tạo session
            $user = [
                'id' => $result['id'],
                'username' => $result['username'],
                'full_name' => $result['full_name'],
                'email' => $result['email'],
                'role' => $result['role']
            ];
            
            $this->startSession($user);
            $this->updateLastLogin($user['id']);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Remember token error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Xóa remember cookie
     */
    private function clearRememberCookie() {
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE['remember_token']);
        }
    }
    
    /**
     * Đăng xuất
     */
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Xóa remember token trong DB nếu có
        if (isset($_SESSION['user_id'])) {
            try {
                $sql = "DELETE FROM remember_tokens WHERE user_id = ?";
                $this->db->execute($sql, [$_SESSION['user_id']]);
            } catch (Exception $e) {
                error_log("Error deleting remember token: " . $e->getMessage());
            }
        }
        
        // Xóa cookie
        $this->clearRememberCookie();
        
        // Xóa session
        $_SESSION = [];
        session_destroy();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 36000, '/');
        }
    }
    
    /**
     * Kiểm tra đăng nhập
     */
    public function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
    
    /**
     * Lấy thông tin user hiện tại
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['user_role']
        ];
    }
    
    /**
     * Kiểm tra quyền truy cập module
     */
    public function hasPermission($module, $action = 'view') {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        
        // Admin có full quyền
        if ($role === 'Admin') {
            return true;
        }
        
        $sql = "SELECT can_view, can_create, can_edit, can_delete, can_export 
               FROM module_permissions 
               WHERE role = ? AND module_name = ?";
        
        $permission = $this->db->fetch($sql, [$role, $module]);
        
        if (!$permission) {
            return false;
        }
        
        switch ($action) {
            case 'view':
                return $permission['can_view'];
            case 'create':
                return $permission['can_create'];
            case 'edit':
                return $permission['can_edit'];
            case 'delete':
                return $permission['can_delete'];
            case 'export':
                return $permission['can_export'];
            default:
                return false;
        }
    }
    
    /**
     * Require đăng nhập
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                jsonResponse(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn'], 401);
            } else {
                redirect('/login.php');
            }
        }
    }
    
    /**
     * Require quyền truy cập
     */
    public function requirePermission($module, $action = 'view') {
        $this->requireLogin();
        
        if (!$this->hasPermission($module, $action)) {
            if ($this->isAjaxRequest()) {
                jsonResponse(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này'], 403);
            } else {
                http_response_code(403);
                include '../errors/403.php';
                exit;
            }
        }
    }
    
    /**
     * Tạo mật khẩu hash
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Tạo user mới
     */
    public function createUser($data) {
        try {
            // Validate dữ liệu
            if (empty($data['username']) || empty($data['password']) || empty($data['full_name'])) {
                return ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
            }
            
            // Kiểm tra username đã tồn tại
            $sql = "SELECT id FROM users WHERE username = ?";
            if ($this->db->fetch($sql, [$data['username']])) {
                return ['success' => false, 'message' => 'Tên đăng nhập đã tồn tại'];
            }
            
            // Kiểm tra email đã tồn tại
            if (!empty($data['email'])) {
                $sql = "SELECT id FROM users WHERE email = ?";
                if ($this->db->fetch($sql, [$data['email']])) {
                    return ['success' => false, 'message' => 'Email đã tồn tại'];
                }
            }
            
            // Tạo user
            $sql = "INSERT INTO users (username, password, full_name, email, phone, role, created_by) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['username'],
                $this->hashPassword($data['password']),
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['role'] ?? 'User',
                $_SESSION['user_id'] ?? null
            ];
            
            $this->db->execute($sql, $params);
            
            return ['success' => true, 'message' => 'Tạo user thành công'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cập nhật thông tin user
     */
    public function updateUser($id, $data) {
        try {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, updated_at = NOW() 
                   WHERE id = ?";
            
            $params = [
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['role'],
                $id
            ];
            
            $this->db->execute($sql, $params);
            
            return ['success' => true, 'message' => 'Cập nhật thành công'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Đổi mật khẩu
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Kiểm tra mật khẩu cũ
            $sql = "SELECT password FROM users WHERE id = ?";
            $user = $this->db->fetch($sql, [$userId]);
            
            if (!$user || !password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Mật khẩu cũ không chính xác'];
            }
            
            // Cập nhật mật khẩu mới
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$this->hashPassword($newPassword), $userId]);
            
            return ['success' => true, 'message' => 'Đổi mật khẩu thành công'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Khởi tạo session
     */
    private function startSession($user) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Cập nhật thời gian đăng nhập cuối
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET updated_at = NOW() WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Ghi log đăng nhập thất bại
     */
    private function logFailedLogin($username) {
        error_log("Failed login attempt for username: $username from IP: " . $_SERVER['REMOTE_ADDR']);
    }
    
    /**
     * Kiểm tra request Ajax
     */
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Lấy danh sách roles
     */
    public function getRoles() {
        return ['Admin', 'Supervisor', 'Production Manager', 'User'];
    }
    
    /**
     * Lấy danh sách users
     */
    public function getUsers($filters = []) {
        $sql = "SELECT id, username, full_name, email, phone, role, status, created_at 
               FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
}

// Khởi tạo auth global
$auth = new Auth();

// Helper functions
function requireLogin() {
    global $auth;
    $auth->requireLogin();
}

function requirePermission($module, $action = 'view') {
    global $auth;
    $auth->requirePermission($module, $action);
}

function hasPermission($module, $action = 'view') {
    global $auth;
    return $auth->hasPermission($module, $action);
}

function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function isLoggedIn() {
    global $auth;
    return $auth->isLoggedIn();
}
?>