<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require login - with better error handling
try {
    requireLogin();
} catch (Exception $e) {
    // If this is an AJAX request, return JSON error
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Regular request - redirect to login
        header('Location: /login.php');
        exit;
    }
}

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'CMMS';
$currentModule = $currentModule ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Module specific CSS -->
    <?php if (!empty($moduleCSS)): ?>
        <link rel="stylesheet" href="/assets/css/<?php echo $moduleCSS; ?>.css">
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: <?php echo getConfig('app.theme_color'); ?>;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            min-height: calc(100vh - 56px);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.125rem 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .main-content {
            background-color: #f8fafc;
            min-height: calc(100vh - 56px);
        }
        
        .card {
            border: none;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .badge-success { background-color: #10b981; }
        .badge-warning { background-color: #f59e0b; }
        .badge-danger { background-color: #ef4444; }
        .badge-info { background-color: #06b6d4; }
        .badge-secondary { background-color: #6b7280; }
        .badge-dark { background-color: #374151; }
        
        .table th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                left: -100%;
                width: 280px;
                height: calc(100vh - 56px);
                transition: left 0.3s ease;
                z-index: 1040;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 56px;
                left: 0;
                width: 100%;
                height: calc(100vh - 56px);
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1039;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container-fluid">
            <!-- Mobile menu toggle -->
            <button class="navbar-toggler border-0 d-lg-none" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="/index.php">
                <img src="/assets/images/logo.png" alt="Logo" class="me-2">
                <span class="d-none d-md-inline">CMMS</span>
            </a>
            
            <!-- Right side menu -->
            <div class="navbar-nav ms-auto">
                <!-- Notifications -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger badge-notification">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Thông báo</h6></li>
                        <li><a class="dropdown-item" href="#">
                            <small class="text-muted">5 phút trước</small><br>
                            Thiết bị ABC cần bảo trì
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <small class="text-muted">1 giờ trước</small><br>
                            Kế hoạch bảo trì tuần này
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">Xem tất cả</a></li>
                    </ul>
                </div>
                
                <!-- User menu -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="avatar me-2">
                            <i class="fas fa-user-circle fs-4"></i>
                        </div>
                        <div class="d-none d-md-block">
                            <div class="fw-semibold"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">
                            <?php echo htmlspecialchars($currentUser['full_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($currentUser['role']); ?></small>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile.php">
                            <i class="fas fa-user me-2"></i>Thông tin cá nhân
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/change-password.php">
                            <i class="fas fa-key me-2"></i>Đổi mật khẩu
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-lg-3 col-xl-2 sidebar" id="sidebar">
                <?php include 'sidebar.php'; ?>
            </nav>
            
            <!-- Main content -->
            <main class="col-lg-9 col-xl-10 ms-sm-auto px-4 main-content">
                <div class="pt-3 pb-2 mb-3">
                    <!-- Breadcrumb -->
                    <?php if (!empty($breadcrumb)): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Trang chủ</a></li>
                            <?php foreach ($breadcrumb as $item): ?>
                                <?php if (!empty($item['url'])): ?>
                                    <li class="breadcrumb-item"><a href="<?php echo $item['url']; ?>"><?php echo $item['title']; ?></a></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active"><?php echo $item['title']; ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <?php endif; ?>
                    
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
                        <h1 class="h2 mb-0"><?php echo $pageTitle; ?></h1>
                        <?php if (!empty($pageActions)): ?>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <?php echo $pageActions; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>