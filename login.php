<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';

session_start();

// Kiểm tra remember token trước khi hiển thị form login
$auth->checkRememberToken();

// Nếu đã đăng nhập, chuyển về trang chủ
if (isLoggedIn()) {
    redirect(APP_URL);
}

$error = '';
$success = '';

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']); // Kiểm tra checkbox ghi nhớ
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin đăng nhập';
    } else {
        $result = $auth->login($username, $password, $remember);
        
        if ($result['success']) {
            $success = $result['message'];
            // Chuyển hướng sau 1 giây
            header('Refresh: 1; url=' . APP_URL);
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    
    <style>
        .login-container {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 100px 100px;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-100px); }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
        }
        
        .login-logo i {
            font-size: 2rem;
            color: white;
        }
        
        .form-floating > label {
            color: #64748b;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
        }
        
        .company-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .company-info h5 {
            color: #1e3a8a;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .company-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .login-footer {
            position: fixed;
            bottom: 1rem;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            z-index: 1;
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
        }
        
        .form-check-input:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>
        
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="login-card">
                        <!-- Logo -->
                        <div class="login-logo">
                            <i class="fas fa-industry"></i>
                        </div>
                        
                        <!-- Title -->
                        <div class="text-center mb-4">
                            <h4 class="fw-bold text-dark mb-2">Đăng nhập hệ thống</h4>
                            <p class="text-muted mb-0">Hệ thống quản lý thiết bị CMMS</p>
                        </div>
                        
                        <!-- Alerts -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" id="loginForm" class="needs-validation" novalidate>
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Tên đăng nhập"
                                       value="<?php echo htmlspecialchars($username ?? ''); ?>"
                                       required
                                       autocomplete="username">
                                <label for="username">Tên đăng nhập</label>
                                <div class="invalid-feedback">
                                    Vui lòng nhập tên đăng nhập
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Mật khẩu"
                                       required
                                       autocomplete="current-password">
                                <label for="password">Mật khẩu</label>
                                <div class="invalid-feedback">
                                    Vui lòng nhập mật khẩu
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="remember" 
                                       name="remember"
                                       value="1">
                                <label class="form-check-label text-muted" for="remember">
                                    Ghi nhớ đăng nhập (30 ngày)
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Đăng nhập
                            </button>
                        </form>
                        
                        <!-- Company Info -->
                        <div class="company-info">
                            <h5>BẢO TRÌ CF</h5>
                            <p>Hệ thống quản lý bảo trì thiết bị</p>
                            <small class="text-muted">Version <?php echo APP_VERSION; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Bảo trì CF. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // Form validation
        loginForm.addEventListener('submit', function(event) {
            if (!loginForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Show loading
                loadingOverlay.style.display = 'flex';
            }
            
            loginForm.classList.add('was-validated');
        });
        
        // Auto focus username field
        const usernameField = document.getElementById('username');
        if (usernameField && !usernameField.value) {
            usernameField.focus();
        } else {
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.focus();
            }
        }
        
        // Demo accounts info (remove in production)
        console.log('%cDemo accounts:', 'color: #3b82f6; font-weight: bold;');
        console.log('Admin: admin / password');
        console.log('Supervisor: supervisor1 / password');
        console.log('Manager: manager1 / password');
        console.log('User: user1 / password');
        
        // Add demo account buttons (for localhost only)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            const demoAccounts = [
                { username: 'admin', password: 'password', role: 'Admin' },
                { username: 'supervisor1', password: 'password', role: 'Supervisor' },
                { username: 'manager1', password: 'password', role: 'Manager' },
                { username: 'user1', password: 'password', role: 'User' }
            ];
            
            const demoContainer = document.createElement('div');
            demoContainer.className = 'mt-3 text-center';
            demoContainer.innerHTML = '<small class="text-muted">Demo accounts:</small>';
            
            demoAccounts.forEach(account => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-secondary btn-sm mx-1 mt-1';
                btn.textContent = account.role;
                btn.onclick = function() {
                    document.getElementById('username').value = account.username;
                    document.getElementById('password').value = account.password;
                };
                demoContainer.appendChild(btn);
            });
            
            loginForm.parentNode.insertBefore(demoContainer, loginForm.nextSibling);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl+Enter to submit
            if (event.ctrlKey && event.key === 'Enter') {
                loginForm.submit();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000);
        });
    });
    </script>
</body>
</html>