<?php
session_start();

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/auth.php';

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
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin đăng nhập';
    } else {
        $result = $auth->login($username, $password);
        
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
            max-width: 400px;
            position: relative;
            z-index: 1;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.3);
        }
        
        .form-floating .form-control {
            background-color: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 0.75rem;
            backdrop-filter: blur(10px);
        }
        
        .form-floating .form-control:focus {
            background-color: rgba(255, 255, 255, 0.9);
            border-color: #1e3a8a;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
        }
        
        .form-floating > label {
            color: #64748b;
            font-weight: 500;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
        }
        
        .company-info {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(226, 232, 240, 0.5);
        }
        
        .company-info h5 {
            color: #1e3a8a;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .company-info p {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0;
        }
        
        .login-footer {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            font-size: 0.875rem;
        }
        
        .alert {
            border-radius: 0.75rem;
            border: none;
            backdrop-filter: blur(10px);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5 col-xl-4">
                    <div class="login-card">
                        <div class="loading-overlay" id="loadingOverlay">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Đang đăng nhập...</span>
                            </div>
                        </div>
                        
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
                                       name="remember">
                                <label class="form-check-label text-muted" for="remember">
                                    Ghi nhớ đăng nhập
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
            <p>&copy; <?php echo date('Y'); ?> IT Department. All rights reserved.</p>
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
        console.log('Demo accounts:');
        console.log('Admin: admin / password');
        console.log('Supervisor: supervisor1 / password');
        console.log('Manager: manager1 / password');
        console.log('User: user1 / password');
        
        // Add demo account buttons (remove in production)
        if (window.location.hostname === 'localhost') {
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
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Password visibility toggle
        const togglePassword = document.createElement('button');
        togglePassword.type = 'button';
        togglePassword.className = 'btn btn-outline-secondary position-absolute top-50 end-0 translate-middle-y me-2';
        togglePassword.style.border = 'none';
        togglePassword.style.background = 'transparent';
        togglePassword.style.zIndex = '10';
        togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
        
        const passwordContainer = document.querySelector('.form-floating:has(#password)');
        passwordContainer.style.position = 'relative';
        passwordContainer.appendChild(togglePassword);
        
        togglePassword.addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    </script>
</body>
</html>