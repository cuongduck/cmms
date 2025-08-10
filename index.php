<?php
// index.php - Dashboard Homepage
require_once 'config/database.php';
require_once 'config/functions.php';

$page_title = 'Dashboard';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Lấy dữ liệu thống kê
try {
    // Tổng số thiết bị
    $stmt = $db->query("SELECT COUNT(*) as total FROM thiet_bi");
    $total_equipment = $stmt->fetch()['total'];
    
    // Thiết bị đang hoạt động
    $stmt = $db->query("SELECT COUNT(*) as total FROM thiet_bi WHERE tinh_trang = 'hoat_dong'");
    $active_equipment = $stmt->fetch()['total'];
    
    // Thiết bị cần bảo trì
    $stmt = $db->query("SELECT COUNT(*) as total FROM thiet_bi WHERE tinh_trang = 'bao_tri'");
    $maintenance_equipment = $stmt->fetch()['total'];
    
    // Thiết bị hỏng
    $stmt = $db->query("SELECT COUNT(*) as total FROM thiet_bi WHERE tinh_trang = 'hong'");
    $broken_equipment = $stmt->fetch()['total'];
    
    // Kế hoạch bảo trì sắp đến hạn (7 ngày tới)
    $stmt = $db->query("SELECT COUNT(*) as total FROM ke_hoach_bao_tri WHERE ngay_bao_tri_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND trang_thai = 'chua_thuc_hien'");
    $upcoming_maintenance = $stmt->fetch()['total'];
    
    // Kế hoạch bảo trì quá hạn
    $stmt = $db->query("SELECT COUNT(*) as total FROM ke_hoach_bao_tri WHERE ngay_bao_tri_tiep_theo < CURDATE() AND trang_thai = 'chua_thuc_hien'");
    $overdue_maintenance = $stmt->fetch()['total'];
    
    // Công việc đang thực hiện
    $stmt = $db->query("SELECT COUNT(*) as total FROM ke_hoach_cong_viec WHERE trang_thai IN ('da_giao', 'dang_thuc_hien')");
    $ongoing_tasks = $stmt->fetch()['total'];
    
    // Vật tư tồn kho thấp
    $stmt = $db->query("SELECT COUNT(*) as total FROM ton_kho tk JOIN vat_tu vt ON tk.id_vat_tu = vt.id WHERE tk.so_luong_ton <= tk.so_luong_toi_thieu");
    $low_stock = $stmt->fetch()['total'];
    
    // Kế hoạch hiệu chuẩn sắp đến hạn
    $stmt = $db->query("SELECT COUNT(*) as total FROM ke_hoach_hieu_chuan WHERE ngay_hieu_chuan_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND trang_thai = 'chua_thuc_hien'");
    $upcoming_calibration = $stmt->fetch()['total'];
    
    // Lấy danh sách thiết bị cần bảo trì gấp
    $urgent_maintenance_sql = "SELECT tb.id_thiet_bi, tb.ten_thiet_bi, x.ten_xuong, pl.ten_line, 
                               kh.ten_ke_hoach, kh.ngay_bao_tri_tiep_theo,
                               DATEDIFF(kh.ngay_bao_tri_tiep_theo, CURDATE()) as days_remaining
                               FROM ke_hoach_bao_tri kh
                               JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
                               JOIN xuong x ON tb.id_xuong = x.id
                               JOIN production_line pl ON tb.id_line = pl.id
                               WHERE kh.trang_thai = 'chua_thuc_hien' 
                               AND kh.ngay_bao_tri_tiep_theo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                               ORDER BY kh.ngay_bao_tri_tiep_theo ASC
                               LIMIT 10";
    $stmt = $db->query($urgent_maintenance_sql);
    $urgent_maintenance_list = $stmt->fetchAll();
    
    // Lấy lịch sử bảo trì gần đây
    $recent_maintenance_sql = "SELECT lsbt.*, tb.id_thiet_bi, tb.ten_thiet_bi, u.full_name
                               FROM lich_su_bao_tri lsbt
                               JOIN thiet_bi tb ON lsbt.id_thiet_bi = tb.id
                               JOIN users u ON lsbt.nguoi_thuc_hien = u.id
                               ORDER BY lsbt.ngay_thuc_hien DESC
                               LIMIT 5";
    $stmt = $db->query($recent_maintenance_sql);
    $recent_maintenance = $stmt->fetchAll();
    
    // Lấy vật tư tồn kho thấp
    $low_stock_sql = "SELECT vt.ten_vat_tu, vt.dvt, tk.so_luong_ton, tk.so_luong_toi_thieu
                      FROM ton_kho tk
                      JOIN vat_tu vt ON tk.id_vat_tu = vt.id
                      WHERE tk.so_luong_ton <= tk.so_luong_toi_thieu
                      ORDER BY (tk.so_luong_ton / tk.so_luong_toi_thieu) ASC
                      LIMIT 5";
    $stmt = $db->query($low_stock_sql);
    $low_stock_items = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total_equipment = $active_equipment = $maintenance_equipment = $broken_equipment = 0;
    $upcoming_maintenance = $overdue_maintenance = $ongoing_tasks = $low_stock = $upcoming_calibration = 0;
    $urgent_maintenance_list = $recent_maintenance = $low_stock_items = [];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted">Tổng quan hệ thống CMMS</p>
        </div>
        <div class="text-end">
            <small class="text-muted">Cập nhật lần cuối: <?= date('d/m/Y H:i') ?></small><br>
            <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Làm mới
            </button>
        </div>
    </div>

    <!-- Statistics Cards Row 1 -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($total_equipment) ?></h4>
                            <p class="mb-0">Tổng thiết bị</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cogs fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-primary bg-opacity-75">
                    <a href="/modules/equipment/" class="text-white text-decoration-none">
                        <small>Xem chi tiết <i class="fas fa-arrow-right ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($active_equipment) ?></h4>
                            <p class="mb-0">Đang hoạt động</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-success bg-opacity-75">
                    <small>
                        <?= $total_equipment > 0 ? round(($active_equipment / $total_equipment) * 100, 1) : 0 ?>% 
                        tổng thiết bị
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($maintenance_equipment) ?></h4>
                            <p class="mb-0">Đang bảo trì</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-wrench fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-warning bg-opacity-75">
                    <a href="/modules/maintenance/" class="text-white text-decoration-none">
                        <small>Xem kế hoạch <i class="fas fa-arrow-right ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($broken_equipment) ?></h4>
                            <p class="mb-0">Hỏng hóc</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-danger bg-opacity-75">
                    <small>Cần sửa chữa gấp</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards Row 2 -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-info"><?= number_format($upcoming_maintenance) ?></h5>
                            <small class="text-muted">Bảo trì sắp đến hạn</small>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x text-info opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-danger"><?= number_format($overdue_maintenance) ?></h5>
                            <small class="text-muted">Bảo trì quá hạn</small>
                        </div>
                        <i class="fas fa-clock fa-2x text-danger opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-primary"><?= number_format($ongoing_tasks) ?></h5>
                            <small class="text-muted">Công việc đang thực hiện</small>
                        </div>
                        <i class="fas fa-tasks fa-2x text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-warning"><?= number_format($low_stock) ?></h5>
                            <small class="text-muted">Vật tư tồn kho thấp</small>
                        </div>
                        <i class="fas fa-boxes fa-2x text-warning opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Urgent Maintenance -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                        Bảo trì cần ưu tiên
                    </h5>
                    <a href="/modules/maintenance/" class="btn btn-outline-primary btn-sm">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($urgent_maintenance_list)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">Không có bảo trì khẩn cấp</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Thiết bị</th>
                                        <th>Vị trí</th>
                                        <th>Ngày hết hạn</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($urgent_maintenance_list as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($item['ten_thiet_bi']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($item['id_thiet_bi']) ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars($item['ten_xuong']) ?><br>
                                                    <?= htmlspecialchars($item['ten_line']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?= formatDate($item['ngay_bao_tri_tiep_theo']) ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($item['days_remaining']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Maintenance History -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-success me-2"></i>
                        Lịch sử bảo trì gần đây
                    </h5>
                    <a href="/modules/maintenance/history.php" class="btn btn-outline-primary btn-sm">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_maintenance)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">Chưa có lịch sử bảo trì</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recent_maintenance as $history): ?>
                                <div class="timeline-item mb-3">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= htmlspecialchars($history['ten_cong_viec']) ?></h6>
                                        <p class="mb-1 text-muted">
                                            <strong><?= htmlspecialchars($history['ten_thiet_bi']) ?></strong>
                                            (<?= htmlspecialchars($history['id_thiet_bi']) ?>)
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($history['full_name']) ?>
                                            <i class="fas fa-calendar ms-2 me-1"></i><?= formatDate($history['ngay_thuc_hien']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Items -->
    <?php if (!empty($low_stock_items)): ?>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Vật tư tồn kho thấp
                    </h5>
                    <a href="/modules/inventory/" class="btn btn-outline-primary btn-sm">
                        Quản lý kho
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tên vật tư</th>
                                    <th>ĐVT</th>
                                    <th>Tồn hiện tại</th>
                                    <th>Tồn tối thiểu</th>
                                    <th>Tỷ lệ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                    <?php 
                                        $ratio = $item['so_luong_toi_thieu'] > 0 ? 
                                                ($item['so_luong_ton'] / $item['so_luong_toi_thieu']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['ten_vat_tu']) ?></td>
                                        <td><?= htmlspecialchars($item['dvt']) ?></td>
                                        <td class="text-danger fw-bold"><?= number_format($item['so_luong_ton'], 2) ?></td>
                                        <td><?= number_format($item['so_luong_toi_thieu'], 2) ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-danger" style="width: <?= min($ratio, 100) ?>%">
                                                    <?= round($ratio, 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-primary me-2"></i>
                        Thao tác nhanh
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (hasPermission(['admin', 'to_truong'])): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="/modules/equipment/add.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                <span>Thêm thiết bị mới</span>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="/modules/maintenance/add.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                <span>Lập kế hoạch bảo trì</span>
                            </a>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="/modules/qr-scanner/" class="btn btn-outline-info w-100 h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-qrcode fa-2x mb-2"></i>
                                <span>Quét mã QR</span>
                            </a>
                        </div>
                        
                        <?php if (hasPermission(['admin', 'truong_ca'])): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="/modules/tasks/requests.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center">
                                <i class="fas fa-paper-plane fa-2x mb-2"></i>
                                <span>Gửi yêu cầu công việc</span>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 8px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #28a745;
}

/* Card hover effects */
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

/* Progress animation */
.progress-bar {
    transition: width 1s ease-in-out;
}

/* Quick action buttons */
.btn-outline-primary:hover,
.btn-outline-success:hover,
.btn-outline-info:hover,
.btn-outline-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
</style>

<script>
$(document).ready(function() {
    // Animate progress bars
    setTimeout(function() {
        $('.progress-bar').each(function() {
            const width = $(this).css('width');
            $(this).css('width', '0').animate({ width: width }, 1000);
        });
    }, 500);
    
    // Auto refresh dashboard every 5 minutes
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            location.reload();
        }
    }, 300000);
});
</script>

<?php require_once 'includes/footer.php'; ?>