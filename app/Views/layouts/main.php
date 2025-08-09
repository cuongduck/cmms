<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'CMMS System' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 0.375rem;
            margin: 0.125rem 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0 0.125rem;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                z-index: 1000;
                transition: left 0.3s;
                width: 250px;
            }
            .sidebar.show {
                left: 0;
            }
        }
        .loading {
            display: none;
        }
        .loading.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed top-0 start-0 d-flex flex-column p-3" style="width: 250px;">
        <div class="navbar-brand text-white mb-4">
            <i class="fas fa-tools"></i>
            <span class="ms-2">CMMS System</span>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="<?= base_url('dashboard') ?>" class="nav-link <?= (current_url(true)->getSegment(1) == 'dashboard') ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#equipmentMenu">
                    <span><i class="fas fa-cogs me-2"></i>Quản lý thiết bị</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="equipmentMenu">
                    <ul class="nav nav-pills flex-column ms-3">
                        <li class="nav-item">
                            <a href="<?= base_url('thiet-bi') ?>" class="nav-link">
                                <i class="fas fa-list me-2"></i>Danh sách thiết bị
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('thiet-bi/create') ?>" class="nav-link">
                                <i class="fas fa-plus me-2"></i>Thêm thiết bị
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <?php if (session('role') == 'admin'): ?>
            <li class="nav-item">
                <a href="<?= base_url('users') ?>" class="nav-link <?= (current_url(true)->getSegment(1) == 'users') ? 'active' : '' ?>">
                    <i class="fas fa-users me-2"></i>
                    Quản lý người dùng
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= base_url('assets/images/avatar-default.png') ?>" alt="" width="32" height="32" class="rounded-circle me-2">
                <strong><?= session('full_name') ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Hồ sơ</a></li>
                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Cài đặt</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= base_url('auth/logout') ?>"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main content -->
    <main class="main-content">
        <!-- Top navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3">
            <button class="btn btn-outline-secondary d-md-none" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Thông báo</h6></li>
                        <li><a class="dropdown-item" href="#">Thiết bị XYZ cần bảo trì</a></li>
                        <li><a class="dropdown-item" href="#">Vật tư ABC sắp hết</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">Xem tất cả</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page content -->
        <div class="container-fluid p-4">
            <!-- Hiển thị thông báo -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Loading overlay -->
            <div class="loading position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.8); z-index: 9999;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Đang tải...</span>
                </div>
            </div>

            <!-- Main content goes here -->
            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Global JavaScript functions
        $(document).ready(function() {
            // Sidebar toggle for mobile
            $('#sidebarToggle').click(function() {
                $('.sidebar').toggleClass('show');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
                    $('.sidebar').removeClass('show');
                }
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Auto hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        });

        // Show/Hide loading
        function showLoading() {
            $('.loading').addClass('show');
        }

        function hideLoading() {
            $('.loading').removeClass('show');
        }

        // AJAX setup with loading
        $.ajaxSetup({
            beforeSend: function() {
                showLoading();
            },
            complete: function() {
                hideLoading();
            }
        });

        // Global confirm delete function
        function confirmDelete(url, message = 'Bạn có chắc chắn muốn xóa?') {
            Swal.fire({
                title: 'Xác nhận xóa',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'DELETE',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Đã xóa!', response.message, 'success');
                                // Reload table or page
                                if (typeof table !== 'undefined') {
                                    table.ajax.reload();
                                } else {
                                    location.reload();
                                }
                            } else {
                                Swal.fire('Lỗi!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Lỗi!', 'Có lỗi xảy ra khi xóa', 'error');
                        }
                    });
                }
            });
        }
    </script>

    <?= $this->renderSection('scripts') ?>
</body>
</html>