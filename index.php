<?php
$pageTitle = 'Tổng quan hệ thống';
$currentModule = 'dashboard';

require_once 'includes/header.php';

// Get statistics
$stats = [
    'industries' => $db->fetch("SELECT COUNT(*) as count FROM industries WHERE status = 'active'")['count'],
    'workshops' => $db->fetch("SELECT COUNT(*) as count FROM workshops WHERE status = 'active'")['count'],
    'equipment_total' => $db->fetch("SELECT COUNT(*) as count FROM equipment")['count'],
    'equipment_active' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'active'")['count'],
    'equipment_maintenance' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'maintenance'")['count'],
    'equipment_broken' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'broken'")['count']
];

// Get recent activities (mock data for now)
$recentActivities = [
    ['user' => 'Admin', 'action' => 'Tạo thiết bị mới', 'target' => 'Máy trộn ABC-001', 'time' => '5 phút trước'],
    ['user' => 'Supervisor1', 'action' => 'Cập nhật kế hoạch bảo trì', 'target' => 'Line 1 - Đóng gói', 'time' => '15 phút trước'],
    ['user' => 'Manager1', 'action' => 'Thêm ngành mới', 'target' => 'Ngành Nêm rau', 'time' => '30 phút trước'],
    ['user' => 'User1', 'action' => 'Xem báo cáo', 'target' => 'Báo cáo thiết bị tháng 8', 'time' => '1 giờ trước']
];
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-industry"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['industries']); ?></div>
                        <div class="stat-label">Ngành sản xuất</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['workshops']); ?></div>
                        <div class="stat-label">Xưởng sản xuất</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['equipment_total']); ?></div>
                        <div class="stat-label">Tổng thiết bị</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['equipment_maintenance']); ?></div>
                        <div class="stat-label">Đang bảo trì</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Equipment Status Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Trạng thái thiết bị
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="p-3 bg-success bg-opacity-10 rounded">
                            <div class="h3 mb-1 text-success"><?php echo $stats['equipment_active']; ?></div>
                            <div class="text-muted">Hoạt động</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-warning bg-opacity-10 rounded">
                            <div class="h3 mb-1 text-warning"><?php echo $stats['equipment_maintenance']; ?></div>
                            <div class="text-muted">Bảo trì</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-danger bg-opacity-10 rounded">
                            <div class="h3 mb-1 text-danger"><?php echo $stats['equipment_broken']; ?></div>
                            <div class="text-muted">Hỏng</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded">
                            <div class="h3 mb-1 text-secondary">
                                <?php echo $stats['equipment_total'] - $stats['equipment_active'] - $stats['equipment_maintenance'] - $stats['equipment_broken']; ?>
                            </div>
                            <div class="text-muted">Khác</div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="progress" style="height: 20px;">
                        <?php 
                        $total = max($stats['equipment_total'], 1);
                        $activePercent = round(($stats['equipment_active'] / $total) * 100, 1);
                        $maintenancePercent = round(($stats['equipment_maintenance'] / $total) * 100, 1);
                        $brokenPercent = round(($stats['equipment_broken'] / $total) * 100, 1);
                        $otherPercent = 100 - $activePercent - $maintenancePercent - $brokenPercent;
                        ?>
                        <div class="progress-bar bg-success" style="width: <?php echo $activePercent; ?>%" title="Hoạt động: <?php echo $activePercent; ?>%"></div>
                        <div class="progress-bar bg-warning" style="width: <?php echo $maintenancePercent; ?>%" title="Bảo trì: <?php echo $maintenancePercent; ?>%"></div>
                        <div class="progress-bar bg-danger" style="width: <?php echo $brokenPercent; ?>%" title="Hỏng: <?php echo $brokenPercent; ?>%"></div>
                        <div class="progress-bar bg-secondary" style="width: <?php echo $otherPercent; ?>%" title="Khác: <?php echo $otherPercent; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('structure', 'view')): ?>
                    <a href="modules/structure/" class="btn btn-outline-primary">
                        <i class="fas fa-sitemap me-2"></i>
                        Cấu trúc thiết bị
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('equipment', 'view')): ?>
                    <a href="modules/equipment/" class="btn btn-outline-info">
                        <i class="fas fa-cogs me-2"></i>
                        Quản lý thiết bị
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('equipment', 'create')): ?>
                    <a href="modules/equipment/add.php" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>
                        Thêm thiết bị mới
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('maintenance', 'view')): ?>
                    <a href="modules/maintenance/" class="btn btn-outline-warning">
                        <i class="fas fa-wrench me-2"></i>
                        Kế hoạch bảo trì
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activities -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Hoạt động gần đây
                </h5>
                <a href="#" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="avatar me-3">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?php echo htmlspecialchars($activity['user']); ?></div>
                                <div class="text-muted small">
                                    <?php echo htmlspecialchars($activity['action']); ?>: 
                                    <strong><?php echo htmlspecialchars($activity['target']); ?></strong>
                                </div>
                            </div>
                            <div class="text-muted small">
                                <?php echo htmlspecialchars($activity['time']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin hệ thống
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Phiên bản:</strong></td>
                        <td><?php echo APP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Người dùng:</strong></td>
                        <td><?php echo getCurrentUser()['full_name']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Vai trò:</strong></td>
                        <td><span class="badge badge-primary"><?php echo getCurrentUser()['role']; ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Đăng nhập:</strong></td>
                        <td><?php echo date('d/m/Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>IP:</strong></td>
                        <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <h6>Liên kết hữu ích</h6>
                    <div class="d-grid gap-1">
                        <a href="#" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-book me-1"></i> Hướng dẫn sử dụng
                        </a>
                        <a href="#" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-life-ring me-1"></i> Hỗ trợ kỹ thuật
                        </a>
                        <a href="#" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-bug me-1"></i> Báo lỗi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Welcome message for new users -->
<?php if (isset($_SESSION['first_login'])): ?>
<div class="modal fade" id="welcomeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-thumbs-up me-2 text-success"></i>
                    Chào mừng đến với CMMS!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Xin chào <strong><?php echo getCurrentUser()['full_name']; ?></strong>!</p>
                <p>Bạn đã đăng nhập thành công vào hệ thống quản lý thiết bị CMMS.</p>
                <p>Để bắt đầu, bạn có thể:</p>
                <ul>
                    <li>Xem cấu trúc thiết bị hiện tại</li>
                    <li>Thêm thiết bị mới vào hệ thống</li>
                    <li>Tạo kế hoạch bảo trì</li>
                    <li>Xem báo cáo và thống kê</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-rocket me-1"></i> Bắt đầu
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
    welcomeModal.show();
});
</script>
<?php unset($_SESSION['first_login']); ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>