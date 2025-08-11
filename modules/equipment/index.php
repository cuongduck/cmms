<?php
// modules/equipment/index.php - Danh sách thiết bị
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$page_title = 'Quản lý thiết bị';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
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

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Xưởng</label>
                    <select class="form-select select2" id="filter_xuong" name="xuong">
                        <option value="">Tất cả xưởng</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Line sản xuất</label>
                    <select class="form-select select2" id="filter_line" name="line" disabled>
                        <option value="">Tất cả line</option>
                    </select>
                </div>
                <div class="col-md-3">
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
                    <input type="text" class="form-control" id="filter_search" name="search" placeholder="ID, tên thiết bị...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Lọc
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="EquipmentModule.resetFilter()">
                        <i class="fas fa-times me-2"></i>Xóa bộ lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Equipment List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách thiết bị</h5>
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
                <table class="table table-hover" id="equipmentTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>ID thiết bị</th>
                            <th>Tên thiết bị</th>
                            <th>Vị trí</th>
                            <th>Tình trạng</th>
                            <th>Người chủ quản</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="equipmentTableBody">
                        <!-- Data will be loaded via AJAX -->
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
                <h5 class="modal-title">Chi tiết thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipmentDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mã QR thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContent"></div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="EquipmentModule.printQR()">
                        <i class="fas fa-print me-2"></i>In QR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>