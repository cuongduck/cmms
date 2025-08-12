<?php
// modules/maintenance/index.php - Maintenance Management
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$page_title = 'Kế hoạch bảo trì';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Kế hoạch bảo trì</h1>
            <p class="text-muted">Quản lý kế hoạch bảo trì định kỳ và sửa chữa thiết bị</p>
        </div>
        <div>
            <?php if (hasPermission(['admin', 'to_truong'])): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tạo kế hoạch bảo trì
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-danger" id="overdue-count">0</h5>
                            <small class="text-muted">Quá hạn</small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-warning" id="upcoming-count">0</h5>
                            <small class="text-muted">Sắp đến hạn (7 ngày)</small>
                        </div>
                        <i class="fas fa-clock fa-2x text-warning opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-info" id="progress-count">0</h5>
                            <small class="text-muted">Đang thực hiện</small>
                        </div>
                        <i class="fas fa-wrench fa-2x text-info opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-success" id="completed-count">0</h5>
                            <small class="text-muted">Hoàn thành tháng này</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Thiết bị</label>
                    <select class="form-select select2" id="filter_equipment" name="equipment_id">
                        <option value="">Tất cả thiết bị</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Loại bảo trì</label>
                    <select class="form-select" id="filter_loai" name="loai_bao_tri">
                        <option value="">Tất cả</option>
                        <option value="dinh_ky">Định kỳ</option>
                        <option value="du_phong">Dự phòng</option>
                        <option value="sua_chua">Sửa chữa</option>
                        <option value="cap_cuu">Cấp cứu</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" id="filter_trang_thai" name="trang_thai">
                        <option value="">Tất cả</option>
                        <option value="chua_thuc_hien">Chưa thực hiện</option>
                        <option value="dang_thuc_hien">Đang thực hiện</option>
                        <option value="hoan_thanh">Hoàn thành</option>
                        <option value="qua_han">Quá hạn</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ưu tiên</label>
                    <select class="form-select" id="filter_uu_tien" name="uu_tien">
                        <option value="">Tất cả</option>
                        <option value="khan_cap">Khẩn cấp</option>
                        <option value="cao">Cao</option>
                        <option value="trung_binh">Trung bình</option>
                        <option value="thap">Thấp</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="filter_search" name="search" placeholder="Tên kế hoạch, thiết bị...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Lọc
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="MaintenanceModule.resetFilter()">
                        <i class="fas fa-times me-2"></i>Xóa bộ lọc
                    </button>
                    <div class="btn-group ms-2">
                        <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-2"></i>Lọc nhanh
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="MaintenanceModule.quickFilter('overdue')">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>Quá hạn
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="MaintenanceModule.quickFilter('upcoming')">
                                <i class="fas fa-clock text-warning me-2"></i>Sắp đến hạn
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="MaintenanceModule.quickFilter('high_priority')">
                                <i class="fas fa-arrow-up text-danger me-2"></i>Ưu tiên cao
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="MaintenanceModule.quickFilter('this_week')">
                                <i class="fas fa-calendar-week text-info me-2"></i>Tuần này
                            </a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance Plans List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách kế hoạch bảo trì</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="MaintenanceModule.exportMaintenance()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="MaintenanceModule.printCalendar()">
                    <i class="fas fa-calendar-alt me-2"></i>Lịch bảo trì
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="maintenanceTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Thiết bị</th>
                            <th>Tên kế hoạch</th>
                            <th>Loại</th>
                            <th>Chu kỳ</th>
                            <th>Ngày tiếp theo</th>
                            <th>Trạng thái</th>
                            <th>Ưu tiên</th>
                            <th>Người thực hiện</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="maintenanceTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav id="pagination" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- Maintenance Detail Modal -->
<div class="modal fade" id="maintenanceDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết kế hoạch bảo trì</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="maintenanceDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <div id="maintenanceActions">
                    <!-- Action buttons will be added dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Execute Maintenance Modal -->
<div class="modal fade" id="executeMaintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thực hiện bảo trì</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="executeForm">
                    <input type="hidden" id="maintenance_id" name="maintenance_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ngày thực hiện <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="ngay_thuc_hien" required value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Giờ bắt đầu</label>
                                <input type="time" class="form-control" name="gio_bat_dau" value="<?= date('H:i') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Giờ kết thúc</label>
                                <input type="time" class="form-control" name="gio_ket_thuc">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Tên công việc <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="ten_cong_viec" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Chi phí (VNĐ)</label>
                                <input type="number" class="form-control" name="chi_phi" min="0" step="1000">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả công việc thực hiện</label>
                        <textarea class="form-control" name="mo_ta" rows="3" placeholder="Mô tả chi tiết công việc đã thực hiện..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kết quả</label>
                        <textarea class="form-control" name="ket_qua" rows="2" placeholder="Kết quả sau khi bảo trì..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vật tư sử dụng</label>
                        <div id="materialUsage">
                            <div class="row material-row">
                                <div class="col-md-6">
                                    <select class="form-select select2" name="materials[0][id_vat_tu]">
                                        <option value="">Chọn vật tư</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control" name="materials[0][so_luong]" placeholder="Số lượng" step="0.001">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="materials[0][don_gia]" placeholder="Đơn giá">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="MaintenanceModule.addMaterialRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Hình ảnh</label>
                                <input type="file" class="form-control" name="hinh_anh[]" accept="image/*" multiple>
                                <div class="form-text">Có thể chọn nhiều ảnh. Tối đa 5MB mỗi ảnh.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="trang_thai" required>
                                    <option value="hoan_thanh">Hoàn thành</option>
                                    <option value="chua_hoan_thanh">Chưa hoàn thành</option>
                                    <option value="loi">Có lỗi</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control" name="ghi_chu" rows="2" placeholder="Ghi chú thêm..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="MaintenanceModule.saveMaintenanceExecution()">
                    <i class="fas fa-save me-2"></i>Lưu kết quả
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Calendar View Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch bảo trì</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="maintenanceCalendar" style="height: 600px;">
                    <!-- Calendar content -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>