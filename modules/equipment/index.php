<?php
// modules/equipment/index.php - Danh sách thiết bị - Updated for new DB structure
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$page_title = 'Quản lý thiết bị';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Lấy thống kê nhanh
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_equipment,
            SUM(CASE WHEN tinh_trang = 'hoat_dong' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN tinh_trang = 'bao_tri' THEN 1 ELSE 0 END) as maintenance_count,
            SUM(CASE WHEN tinh_trang = 'hong' THEN 1 ELSE 0 END) as broken_count,
            SUM(CASE WHEN tinh_trang = 'ngung_hoat_dong' THEN 1 ELSE 0 END) as inactive_count
        FROM thiet_bi
    ";
    $stats = $db->query($stats_sql)->fetch();
} catch (Exception $e) {
    $stats = ['total_equipment' => 0, 'active_count' => 0, 'maintenance_count' => 0, 'broken_count' => 0, 'inactive_count' => 0];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý thiết bị</h1>
            <p class="text-muted">Danh sách và thông tin thiết bị trong hệ thống</p>
        </div>
        <div>
            <?php if (hasPermission(['admin', 'to_truong'])): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Thêm thiết bị
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['total_equipment']) ?></h4>
                            <small>Tổng thiết bị</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cogs fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-primary bg-opacity-75">
                    <small>Tất cả thiết bị trong hệ thống</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['active_count']) ?></h4>
                            <small>Đang hoạt động</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-success bg-opacity-75">
                    <small>
                        <?= $stats['total_equipment'] > 0 ? round(($stats['active_count'] / $stats['total_equipment']) * 100, 1) : 0 ?>% 
                        tổng thiết bị
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['maintenance_count']) ?></h4>
                            <small>Đang bảo trì</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-wrench fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-warning bg-opacity-75">
                    <small>Cần theo dõi tiến độ</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($stats['broken_count']) ?></h4>
                            <small>Hỏng hóc</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-danger bg-opacity-75">
                    <small>Cần sửa chữa khẩn cấp</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Filter Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-filter me-2"></i>Lọc nhanh theo trạng thái
                    </h6>
                    <div class="btn-group flex-wrap" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="EquipmentModule.resetFilter()">
                            <i class="fas fa-list me-2"></i>Tất cả
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="EquipmentModule.quickFilter('active')">
                            <i class="fas fa-check-circle me-2"></i>Hoạt động
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="EquipmentModule.quickFilter('maintenance')">
                            <i class="fas fa-wrench me-2"></i>Bảo trì
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="EquipmentModule.quickFilter('broken')">
                            <i class="fas fa-exclamation-triangle me-2"></i>Hỏng
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="EquipmentModule.quickFilter('inactive')">
                            <i class="fas fa-pause-circle me-2"></i>Ngừng hoạt động
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filter Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-search me-2"></i>Bộ lọc nâng cao
            </h6>
        </div>
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Xưởng</label>
                    <select class="form-select select2" id="filter_xuong" name="xuong">
                        <option value="">Tất cả xưởng</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Khu vực</label>
                    <select class="form-select select2" id="filter_khu_vuc" name="khu_vuc" disabled>
                        <option value="">Tất cả khu vực</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Line sản xuất</label>
                    <select class="form-select select2" id="filter_line" name="line" disabled>
                        <option value="">Tất cả line</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tình trạng</label>
                    <select class="form-select" id="filter_tinh_trang" name="tinh_trang">
                        <option value="">Tất cả</option>
                        <option value="hoat_dong">Hoạt động</option>
                        <option value="bao_tri">Bảo trì</option>
                        <option value="hong">Hỏng</option>
                        <option value="ngung_hoat_dong">Ngừng hoạt động</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="filter_search" name="search" 
                           placeholder="ID, tên thiết bị, ngành...">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="EquipmentModule.resetFilter()">
                        <i class="fas fa-times me-2"></i>Xóa bộ lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Equipment List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Danh sách thiết bị
            </h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="EquipmentModule.exportData()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="EquipmentModule.showQRBatch()">
                    <i class="fas fa-qrcode me-2"></i>In QR hàng loạt
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="equipmentTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="120">ID thiết bị</th>
                            <th>Tên thiết bị</th>
                            <th>Vị trí & Cấu trúc</th>
                            <th width="100">Tình trạng</th>
                            <th>Người chủ quản</th>
                            <th width="80">Năm SX</th>
                            <th width="160">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="equipmentTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Đang tải dữ liệu thiết bị...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav id="pagination" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- Equipment Detail Modal -->
<div class="modal fade" id="equipmentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Chi tiết thiết bị
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipmentDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-qrcode me-2"></i>Mã QR thiết bị
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContent"></div>
            </div>
            <div class="modal-footer justify-content-center">
                <button class="btn btn-primary" onclick="EquipmentModule.printQR()">
                    <i class="fas fa-print me-2"></i>In QR
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch QR Modal -->
<div class="modal fade" id="batchQRModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-qrcode me-2"></i>In QR Code hàng loạt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Tính năng này sẽ được phát triển trong phiên bản tiếp theo
                </div>
                <p>Các tính năng sẽ có:</p>
                <ul>
                    <li>Chọn nhiều thiết bị để in QR cùng lúc</li>
                    <li>Tùy chọn kích thước và layout QR code</li>
                    <li>Xuất file PDF để in trực tiếp</li>
                    <li>Bao gồm thông tin thiết bị bên cạnh QR</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.table th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
    font-size: 0.875rem;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.card-body .spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.stats-card {
    transition: transform 0.2s ease-in-out;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

.equipment-id {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #495057;
}

.location-info {
    line-height: 1.3;
}

.location-info .text-info {
    font-weight: 500;
}

.action-buttons .btn {
    margin: 0 1px;
}

.quick-filter-btn {
    transition: all 0.2s ease;
}

.quick-filter-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .btn-group.flex-wrap .btn {
        margin-bottom: 0.5rem;
    }
    
    .card-body .btn-group-sm {
        flex-direction: column;
    }
    
    .card-body .btn-group-sm .btn {
        margin-bottom: 0.25rem;
    }
}

/* Loading animation */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.loading-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Status badge colors */
.badge.bg-success { background-color: #198754 !important; }
.badge.bg-warning { background-color: #ffc107 !important; color: #000 !important; }
.badge.bg-danger { background-color: #dc3545 !important; }
.badge.bg-secondary { background-color: #6c757d !important; }

/* Equipment table specific styles */
.equipment-table {
    font-size: 0.875rem;
}

.equipment-table .equipment-id {
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
}

.equipment-table .location-hierarchy {
    font-size: 0.8rem;
    color: #6c757d;
}

.equipment-table .location-hierarchy .separator {
    margin: 0 0.3rem;
    color: #adb5bd;
}

.equipment-table .equipment-name {
    font-weight: 500;
    color: #212529;
}

.equipment-table .equipment-industry {
    font-size: 0.75rem;
    color: #6c757d;
    font-style: italic;
}

/* Modal improvements */
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

.modal-body {
    padding: 1.5rem;
}

/* QR Code modal specific */
#qrCodeContent h6 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1rem;
}

#qrCodeContent img {
    border: 2px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 0.5rem;
    background: white;
}
</style>

<!-- Load Equipment Module -->
<script>
// Debug: log page load
console.log('Equipment index page loaded');

// Auto-refresh function (optional)
function autoRefreshData() {
    if (typeof EquipmentModule !== 'undefined' && EquipmentModule.loadData) {
        // Only refresh if user is active and no modals are open
        if (document.visibilityState === 'visible' && !document.querySelector('.modal.show')) {
            console.log('Auto-refreshing equipment data...');
            EquipmentModule.loadData().catch(error => {
                console.log('Auto-refresh failed:', error);
            });
        }
    }
}

// Set up auto-refresh every 5 minutes (optional)
// setInterval(autoRefreshData, 300000);

// Handle browser back/forward
window.addEventListener('popstate', function(event) {
    if (typeof EquipmentModule !== 'undefined' && EquipmentModule.loadData) {
        EquipmentModule.loadData();
    }
});

// Page visibility change handler
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Refresh data when user comes back to tab
        setTimeout(autoRefreshData, 1000);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>