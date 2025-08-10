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
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilter()">
                        <i class="fas fa-times me-2"></i>Xóa bộ lọc
                    </button>
                    <div class="btn-group ms-2">
                        <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-2"></i>Lọc nhanh
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('overdue')">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>Quá hạn
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('upcoming')">
                                <i class="fas fa-clock text-warning me-2"></i>Sắp đến hạn
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('high_priority')">
                                <i class="fas fa-arrow-up text-danger me-2"></i>Ưu tiên cao
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('this_week')">
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
                <button class="btn btn-outline-success btn-sm" onclick="exportMaintenance()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="printCalendar()">
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
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="addMaterialRow()">
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
                <button type="button" class="btn btn-primary" onclick="saveMaintenanceExecution()">
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

<script>
let currentPage = 1;
let totalPages = 1;

$(document).ready(function() {
    loadEquipmentOptions();
    loadMaterialOptions();
    loadMaintenanceData();
    loadStatistics();
    
    // Form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadMaintenanceData();
    });
    
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.maintenance-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            loadStatistics();
        }
    }, 300000);
});

// Load equipment options
function loadEquipmentOptions() {
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'list_simple' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Tất cả thiết bị</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
            });
            $('#filter_equipment').html(options);
        }
    });
}

// Load material options
function loadMaterialOptions() {
    CMMS.ajax('../bom/api.php', {
        method: 'GET',
        data: { action: 'get_vat_tu' }
    }).then(response => {
        if (response.success) {
            window.materialOptions = response.data;
            updateMaterialSelects();
        }
    });
}

function updateMaterialSelects() {
    let options = '<option value="">Chọn vật tư</option>';
    if (window.materialOptions) {
        window.materialOptions.forEach(item => {
            options += `<option value="${item.id}" data-gia="${item.gia}">${item.ma_item} - ${item.ten_vat_tu}</option>`;
        });
    }
    $('select[name*="[id_vat_tu]"]').html(options);
}

// Load statistics
function loadStatistics() {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'statistics' }
    }).then(response => {
        if (response.success) {
            const stats = response.data;
            $('#overdue-count').text(stats.overdue || 0);
            $('#upcoming-count').text(stats.upcoming || 0);
            $('#progress-count').text(stats.in_progress || 0);
            $('#completed-count').text(stats.completed_this_month || 0);
            
            // Update card colors based on urgency
            updateStatisticCards(stats);
        }
    });
}

function updateStatisticCards(stats) {
    // Animate counters
    $('.card h5').each(function() {
        const target = parseInt($(this).text());
        if (target > 0) {
            $(this).closest('.card').addClass('shadow-sm');
        }
    });
}

// Load maintenance data
function loadMaintenanceData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'list');
    formData.append('page', currentPage);
    
    CMMS.showLoading('#maintenanceTableBody');
    
    CMMS.ajax('api.php', {
        method: 'POST',
        data: formData
    }).then(response => {
        CMMS.hideLoading('#maintenanceTableBody');
        
        if (response.success) {
            displayMaintenanceData(response.data);
            displayPagination(response.pagination);
        } else {
            $('#maintenanceTableBody').html('<tr><td colspan="10" class="text-center">Không có dữ liệu</td></tr>');
        }
    });
}

// Display maintenance data
function displayMaintenanceData(data) {
    let html = '';
    
    data.forEach(item => {
        const statusClass = getStatusClass(item.trang_thai);
        const statusText = getStatusText(item.trang_thai);
        const priorityClass = getPriorityClass(item.uu_tien);
        const priorityText = getPriorityText(item.uu_tien);
        const typeClass = getTypeClass(item.loai_bao_tri);
        const typeText = getTypeText(item.loai_bao_tri);
        const daysRemaining = getDaysRemaining(item.ngay_bao_tri_tiep_theo);
        
        html += `
            <tr class="${getRowClass(item.trang_thai, daysRemaining)}">
                <td>
                    <input type="checkbox" class="form-check-input maintenance-checkbox" value="${item.id}">
                </td>
                <td>
                    <div>
                        <strong>${item.id_thiet_bi}</strong><br>
                        <small class="text-muted">${item.ten_thiet_bi}</small>
                    </div>
                </td>
                <td>
                    <div>
                        <strong>${item.ten_ke_hoach}</strong><br>
                        <small class="text-muted">${item.mo_ta ? item.mo_ta.substring(0, 50) + '...' : ''}</small>
                    </div>
                </td>
                <td>
                    <span class="badge ${typeClass}">${typeText}</span>
                </td>
                <td>
                    ${item.chu_ky_ngay ? item.chu_ky_ngay + ' ngày' : '<em>Không định kỳ</em>'}
                </td>
                <td>
                    <div>
                        <strong>${formatDate(item.ngay_bao_tri_tiep_theo)}</strong><br>
                        ${getDateStatus(daysRemaining)}
                    </div>
                </td>
                <td>
                    <span class="badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <span class="badge ${priorityClass}">${priorityText}</span>
                </td>
                <td>
                    <small>${item.nguoi_thuc_hien_name || 'Chưa phân công'}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="viewMaintenanceDetail(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canExecuteMaintenance(item) ? `
                        <button class="btn btn-outline-success" onclick="executeMaintenance(${item.id})" title="Thực hiện">
                            <i class="fas fa-play"></i>
                        </button>
                        ` : ''}
                        ${hasEditPermission() && item.trang_thai === 'chua_thuc_hien' ? `
                        <button class="btn btn-outline-warning" onclick="editMaintenance(${item.id})" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteMaintenance(${item.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    if (html === '') {
        html = '<tr><td colspan="10" class="text-center text-muted">Không có dữ liệu bảo trì</td></tr>';
    }
    
    $('#maintenanceTableBody').html(html);
}

// Helper functions
function getStatusClass(status) {
    const classes = {
        'chua_thuc_hien': 'bg-secondary',
        'dang_thuc_hien': 'bg-info',
        'hoan_thanh': 'bg-success',
        'qua_han': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'chua_thuc_hien': 'Chưa thực hiện',
        'dang_thuc_hien': 'Đang thực hiện',
        'hoan_thanh': 'Hoàn thành',
        'qua_han': 'Quá hạn'
    };
    return texts[status] || 'Không xác định';
}

function getPriorityClass(priority) {
    const classes = {
        'khan_cap': 'bg-danger',
        'cao': 'bg-warning',
        'trung_binh': 'bg-info',
        'thap': 'bg-secondary'
    };
    return classes[priority] || 'bg-secondary';
}

function getPriorityText(priority) {
    const texts = {
        'khan_cap': 'Khẩn cấp',
        'cao': 'Cao',
        'trung_binh': 'Trung bình',
        'thap': 'Thấp'
    };
    return texts[priority] || 'Không xác định';
}

function getTypeClass(type) {
    const classes = {
        'dinh_ky': 'bg-primary',
        'du_phong': 'bg-info',
        'sua_chua': 'bg-warning',
        'cap_cuu': 'bg-danger'
    };
    return classes[type] || 'bg-secondary';
}

function getTypeText(type) {
    const texts = {
        'dinh_ky': 'Định kỳ',
        'du_phong': 'Dự phòng',
        'sua_chua': 'Sửa chữa',
        'cap_cuu': 'Cấp cứu'
    };
    return texts[type] || 'Không xác định';
}

function getDaysRemaining(targetDate) {
    if (!targetDate) return null;
    
    const today = new Date();
    const target = new Date(targetDate);
    const diffTime = target - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
}

function getDateStatus(daysRemaining) {
    if (daysRemaining === null) return '';
    
    if (daysRemaining < 0) {
        return `<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Quá hạn ${Math.abs(daysRemaining)} ngày</small>`;
    } else if (daysRemaining <= 3) {
        return `<small class="text-danger"><i class="fas fa-clock me-1"></i>Còn ${daysRemaining} ngày</small>`;
    } else if (daysRemaining <= 7) {
        return `<small class="text-warning"><i class="fas fa-clock me-1"></i>Còn ${daysRemaining} ngày</small>`;
    } else {
        return `<small class="text-muted">Còn ${daysRemaining} ngày</small>`;
    }
}

function getRowClass(status, daysRemaining) {
    if (status === 'qua_han' || daysRemaining < 0) {
        return 'table-danger';
    } else if (daysRemaining <= 3) {
        return 'table-warning';
    }
    return '';
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

function hasEditPermission() {
    return ['admin', 'to_truong'].includes(CMMS.userRole);
}

function canExecuteMaintenance(item) {
    return ['admin', 'to_truong', 'user'].includes(CMMS.userRole) && 
           ['chua_thuc_hien', 'dang_thuc_hien'].includes(item.trang_thai);
}

// Display pagination
function displayPagination(pagination) {
    totalPages = pagination.total_pages;
    currentPage = pagination.current_page;
    
    let html = '';
    
    if (totalPages > 1) {
        html += '<ul class="pagination justify-content-center">';
        
        if (pagination.has_previous) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Trước</a></li>`;
        }
        
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }
        
        if (pagination.has_next) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Tiếp</a></li>`;
        }
        
        html += '</ul>';
    }
    
    $('#pagination').html(html);
}

// Change page
function changePage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        loadMaintenanceData();
    }
}

// Reset filter
function resetFilter() {
    document.getElementById('filterForm').reset();
    currentPage = 1;
    loadMaintenanceData();
}

// Quick filter
function quickFilter(type) {
    const today = new Date().toISOString().split('T')[0];
    const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    document.getElementById('filterForm').reset();
    
    switch (type) {
        case 'overdue':
            $('#filter_trang_thai').val('qua_han');
            break;
        case 'upcoming':
            // Custom filter for upcoming (handled in backend)
            $('#filter_search').val('upcoming_7_days');
            break;
        case 'high_priority':
            $('#filter_uu_tien').val('khan_cap');
            break;
        case 'this_week':
            $('#filter_search').val('this_week');
            break;
    }
    
    currentPage = 1;
    loadMaintenanceData();
}

// View maintenance detail
function viewMaintenanceDetail(id) {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'detail', id: id }
    }).then(response => {
        if (response.success) {
            $('#maintenanceDetailContent').html(generateMaintenanceDetailHTML(response.data));
            $('#maintenanceDetailModal').modal('show');
            
            // Add action buttons
            const actions = generateActionButtons(response.data);
            $('#maintenanceActions').html(actions);
        } else {
            CMMS.showAlert('Không thể tải chi tiết kế hoạch bảo trì', 'error');
        }
    });
}

// Generate maintenance detail HTML
function generateMaintenanceDetailHTML(data) {
    const daysRemaining = getDaysRemaining(data.ngay_bao_tri_tiep_theo);
    
    return `
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-3">${data.ten_ke_hoach}</h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr><th width="40%">Thiết bị:</th><td><strong>${data.id_thiet_bi} - ${data.ten_thiet_bi}</strong></td></tr>
                            <tr><th>Vị trí:</th><td>${data.ten_xuong} - ${data.ten_line}</td></tr>
                            <tr><th>Loại bảo trì:</th><td><span class="badge ${getTypeClass(data.loai_bao_tri)}">${getTypeText(data.loai_bao_tri)}</span></td></tr>
                            <tr><th>Chu kỳ:</th><td>${data.chu_ky_ngay ? data.chu_ky_ngay + ' ngày' : 'Không định kỳ'}</td></tr>
                            <tr><th>Lần cuối:</th><td>${formatDate(data.lan_bao_tri_cuoi) || 'Chưa thực hiện'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr><th width="40%">Ngày tiếp theo:</th><td><strong>${formatDate(data.ngay_bao_tri_tiep_theo)}</strong></td></tr>
                            <tr><th>Trạng thái:</th><td><span class="badge ${getStatusClass(data.trang_thai)}">${getStatusText(data.trang_thai)}</span></td></tr>
                            <tr><th>Ưu tiên:</th><td><span class="badge ${getPriorityClass(data.uu_tien)}">${getPriorityText(data.uu_tien)}</span></td></tr>
                            <tr><th>Người thực hiện:</th><td>${data.nguoi_thuc_hien_name || 'Chưa phân công'}</td></tr>
                            <tr><th>Người tạo:</th><td>${data.created_by_name}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>Mô tả:</h6>
                    <p class="text-muted">${data.mo_ta || 'Không có mô tả'}</p>
                </div>
                
                ${data.maintenance_history && data.maintenance_history.length > 0 ? `
                <div class="mt-4">
                    <h6>Lịch sử bảo trì:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Công việc</th>
                                    <th>Người thực hiện</th>
                                    <th>Chi phí</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.maintenance_history.map(history => `
                                    <tr>
                                        <td>${formatDate(history.ngay_thuc_hien)}</td>
                                        <td>${history.ten_cong_viec}</td>
                                        <td>${history.nguoi_thuc_hien_name}</td>
                                        <td>${formatCurrency(history.chi_phi)}</td>
                                        <td><span class="badge ${history.trang_thai === 'hoan_thanh' ? 'bg-success' : 'bg-warning'}">${history.trang_thai === 'hoan_thanh' ? 'Hoàn thành' : 'Chưa hoàn thành'}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                ` : ''}
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Thông tin thời gian</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            ${getDateStatus(daysRemaining)}
                        </div>
                        
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar ${daysRemaining < 0 ? 'bg-danger' : (daysRemaining <= 7 ? 'bg-warning' : 'bg-success')}" 
                                 style="width: ${Math.max(0, Math.min(100, (30 - Math.abs(daysRemaining)) / 30 * 100))}%"></div>
                        </div>
                        
                        <small class="text-muted">
                            Ngày tạo: ${formatDate(data.created_at)}<br>
                            Cập nhật: ${formatDate(data.updated_at)}
                        </small>
                    </div>
                </div>
                
                ${data.bom_items && data.bom_items.length > 0 ? `
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Vật tư cần thiết</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            ${data.bom_items.slice(0, 5).map(item => `
                                <div class="list-group-item p-2">
                                    <div class="d-flex justify-content-between">
                                        <small><strong>${item.ma_item}</strong></small>
                                        <small>${item.so_luong} ${item.dvt}</small>
                                    </div>
                                    <small class="text-muted">${item.ten_vat_tu}</small>
                                </div>
                            `).join('')}
                            ${data.bom_items.length > 5 ? `<small class="text-muted text-center">Và ${data.bom_items.length - 5} vật tư khác...</small>` : ''}
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `;
}

// Generate action buttons
function generateActionButtons(data) {
    let buttons = '';
    
    if (canExecuteMaintenance(data)) {
        buttons += `
            <button type="button" class="btn btn-success me-2" onclick="executeMaintenance(${data.id})">
                <i class="fas fa-play me-2"></i>Thực hiện bảo trì
            </button>
        `;
    }
    
    if (hasEditPermission() && data.trang_thai === 'chua_thuc_hien') {
        buttons += `
            <button type="button" class="btn btn-warning me-2" onclick="editMaintenance(${data.id})">
                <i class="fas fa-edit me-2"></i>Sửa kế hoạch
            </button>
        `;
    }
    
    if (data.trang_thai === 'hoan_thanh') {
        buttons += `
            <a href="history.php?maintenance_id=${data.id}" class="btn btn-info me-2" target="_blank">
                <i class="fas fa-history me-2"></i>Xem lịch sử
            </a>
        `;
    }
    
    return buttons;
}

// Execute maintenance
function executeMaintenance(id) {
    // Load maintenance data first
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'detail', id: id }
    }).then(response => {
        if (response.success) {
            const data = response.data;
            $('#maintenance_id').val(id);
            $('input[name="ten_cong_viec"]').val(data.ten_ke_hoach);
            $('textarea[name="mo_ta"]').val(data.mo_ta);
            
            // Load equipment BOM for material suggestions
            if (data.bom_items && data.bom_items.length > 0) {
                // Pre-fill first material if available
                const firstMaterial = data.bom_items[0];
                $('select[name="materials[0][id_vat_tu]"]').val(firstMaterial.id_vat_tu);
                $('input[name="materials[0][so_luong]"]').val(firstMaterial.so_luong);
                $('input[name="materials[0][don_gia]"]').val(firstMaterial.gia);
            }
            
            $('#executeMaintenanceModal').modal('show');
        }
    });
}

// Add material row
function addMaterialRow() {
    const index = $('.material-row').length;
    const newRow = `
        <div class="row material-row mt-2">
            <div class="col-md-6">
                <select class="form-select select2" name="materials[${index}][id_vat_tu]">
                    <option value="">Chọn vật tư</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" name="materials[${index}][so_luong]" placeholder="Số lượng" step="0.001">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control" name="materials[${index}][don_gia]" placeholder="Đơn giá">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMaterialRow(this)">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
    `;
    
    $('#materialUsage').append(newRow);
    updateMaterialSelects();
}

// Remove material row
function removeMaterialRow(button) {
    $(button).closest('.material-row').remove();
}

// Save maintenance execution
function saveMaintenanceExecution() {
    const formData = new FormData(document.getElementById('executeForm'));
    formData.append('action', 'execute');
    
    CMMS.ajax('api.php', {
        data: formData
    }).then(response => {
        if (response.success) {
            CMMS.showAlert('Lưu kết quả bảo trì thành công!', 'success');
            $('#executeMaintenanceModal').modal('hide');
            loadMaintenanceData();
            loadStatistics();
        } else {
            CMMS.showAlert(response.message, 'error');
        }
    });
}

// Edit maintenance
function editMaintenance(id) {
    window.location.href = `edit.php?id=${id}`;
}

// Delete maintenance
function deleteMaintenance(id) {
    CMMS.confirm('Bạn có chắc chắn muốn xóa kế hoạch bảo trì này?', 'Xác nhận xóa').then((result) => {
        if (result.isConfirmed) {
            CMMS.ajax('api.php', {
                data: { action: 'delete', id: id }
            }).then(response => {
                if (response.success) {
                    CMMS.showAlert('Xóa kế hoạch bảo trì thành công', 'success');
                    loadMaintenanceData();
                    loadStatistics();
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            });
        }
    });
}

// Export maintenance
function exportMaintenance() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'export');
    
    // Create download link
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api.php';
    
    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Print calendar
function printCalendar() {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'calendar_data' }
    }).then(response => {
        if (response.success) {
            generateCalendarHTML(response.data);
            $('#calendarModal').modal('show');
        } else {
            CMMS.showAlert('Không thể tải dữ liệu lịch', 'error');
        }
    });
}

// Generate calendar HTML
function generateCalendarHTML(data) {
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    
    // Simple calendar implementation
    let calendarHTML = `
        <div class="calendar-header text-center mb-3">
            <h4>Lịch bảo trì - Tháng ${currentMonth + 1}/${currentYear}</h4>
        </div>
        <div class="calendar-grid">
            <div class="row">
                <div class="col text-center fw-bold">CN</div>
                <div class="col text-center fw-bold">T2</div>
                <div class="col text-center fw-bold">T3</div>
                <div class="col text-center fw-bold">T4</div>
                <div class="col text-center fw-bold">T5</div>
                <div class="col text-center fw-bold">T6</div>
                <div class="col text-center fw-bold">T7</div>
            </div>
    `;
    
    // Generate calendar days with maintenance data
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const startingDayOfWeek = firstDay.getDay();
    
    let dayCount = 1;
    
    for (let week = 0; week < 6; week++) {
        calendarHTML += '<div class="row border-top">';
        
        for (let day = 0; day < 7; day++) {
            let cellContent = '';
            let cellClass = 'col border-end p-2 calendar-cell';
            
            if (week === 0 && day < startingDayOfWeek) {
                cellContent = '';
            } else if (dayCount <= lastDay.getDate()) {
                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;
                const dayMaintenance = data.filter(item => item.ngay_bao_tri_tiep_theo === dateStr);
                
                cellContent = `<div class="fw-bold">${dayCount}</div>`;
                
                if (dayMaintenance.length > 0) {
                    cellClass += ' bg-light';
                    dayMaintenance.forEach(item => {
                        const priority = item.uu_tien === 'khan_cap' ? 'text-danger' : 
                                       item.uu_tien === 'cao' ? 'text-warning' : 'text-info';
                        cellContent += `<div class="maintenance-item ${priority}" style="font-size: 10px;">${item.id_thiet_bi}</div>`;
                    });
                }
                
                dayCount++;
            }
            
            calendarHTML += `<div class="${cellClass}" style="min-height: 80px;">${cellContent}</div>`;
        }
        
        calendarHTML += '</div>';
        
        if (dayCount > lastDay.getDate()) break;
    }
    
    calendarHTML += '</div>';
    
    $('#maintenanceCalendar').html(calendarHTML);
}

function formatCurrency(amount) {
    if (!amount) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { 
        style: 'currency', 
        currency: 'VND' 
    }).format(amount);
}
</script>

<?php require_once '../../includes/footer.php'; ?>