<?php
// modules/tasks/index.php - Task Management
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);

$page_title = 'Quản lý công việc';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý công việc</h1>
            <p class="text-muted">Theo dõi và quản lý các công việc bảo trì, sửa chữa</p>
        </div>
        <div>
            <?php if (hasPermission(['admin', 'to_truong'])): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tạo công việc
            </a>
            <?php endif; ?>
            <?php if (hasPermission(['admin', 'truong_ca'])): ?>
            <a href="requests.php" class="btn btn-outline-warning">
                <i class="fas fa-paper-plane me-2"></i>Yêu cầu công việc
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-info" id="new-tasks-count">0</h5>
                            <small class="text-muted">Công việc mới</small>
                        </div>
                        <i class="fas fa-tasks fa-2x text-info opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-warning" id="assigned-tasks-count">0</h5>
                            <small class="text-muted">Đã giao việc</small>
                        </div>
                        <i class="fas fa-user-check fa-2x text-warning opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-primary" id="progress-tasks-count">0</h5>
                            <small class="text-muted">Đang thực hiện</small>
                        </div>
                        <i class="fas fa-cogs fa-2x text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start border-4 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-0 text-success" id="completed-tasks-count">0</h5>
                            <small class="text-muted">Hoàn thành tuần này</small>
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
                    <label class="form-label">Loại công việc</label>
                    <select class="form-select" id="filter_loai" name="loai_cong_viec">
                        <option value="">Tất cả</option>
                        <option value="bao_tri">Bảo trì</option>
                        <option value="sua_chua">Sửa chữa</option>
                        <option value="kiem_tra">Kiểm tra</option>
                        <option value="lap_dat">Lắp đặt</option>
                        <option value="khac">Khác</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" id="filter_trang_thai" name="trang_thai">
                        <option value="">Tất cả</option>
                        <option value="moi_tao">Mới tạo</option>
                        <option value="da_giao">Đã giao</option>
                        <option value="dang_thuc_hien">Đang thực hiện</option>
                        <option value="cho_xac_nhan">Chờ xác nhận</option>
                        <option value="hoan_thanh">Hoàn thành</option>
                        <option value="huy">Hủy</option>
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
                <div class="col-md-2">
                    <label class="form-label">Người được giao</label>
                    <select class="form-select select2" id="filter_assignee" name="nguoi_duoc_giao">
                        <option value="">Tất cả</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-12">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-info dropdown-toggle btn-sm" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-2"></i>Lọc nhanh
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('my_tasks')">
                                <i class="fas fa-user text-primary me-2"></i>Công việc của tôi
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('urgent')">
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>Khẩn cấp
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('overdue')">
                                <i class="fas fa-clock text-warning me-2"></i>Quá hạn
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="quickFilter('this_week')">
                                <i class="fas fa-calendar-week text-info me-2"></i>Tuần này
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="resetFilter()">
                                <i class="fas fa-times text-muted me-2"></i>Xóa bộ lọc
                            </a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tasks List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách công việc</h5>
            <div>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary btn-sm" id="viewToggle" onclick="toggleView()">
                        <i class="fas fa-th-large me-2"></i>Kanban
                    </button>
                </div>
                <button class="btn btn-outline-success btn-sm" onclick="exportTasks()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Table View -->
            <div id="tableView">
                <div class="table-responsive">
                    <table class="table table-hover" id="tasksTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Tiêu đề</th>
                                <th>Thiết bị</th>
                                <th>Loại</th>
                                <th>Thời hạn</th>
                                <th>Người thực hiện</th>
                                <th>Trạng thái</th>
                                <th>Ưu tiên</th>
                                <th>Tiến độ</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav id="pagination" class="mt-3"></nav>
            </div>

            <!-- Kanban View -->
            <div id="kanbanView" style="display: none;">
                <div class="row">
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">Mới tạo</h6>
                                <span class="badge bg-white text-dark" id="kanban-new-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-moi_tao" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">Đã giao</h6>
                                <span class="badge bg-dark text-white" id="kanban-assigned-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-da_giao" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Đang thực hiện</h6>
                                <span class="badge bg-white text-dark" id="kanban-progress-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-dang_thuc_hien" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">Chờ xác nhận</h6>
                                <span class="badge bg-white text-dark" id="kanban-review-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-cho_xac_nhan" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">Hoàn thành</h6>
                                <span class="badge bg-white text-dark" id="kanban-completed-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-hoan_thanh" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">Hủy</h6>
                                <span class="badge bg-white text-dark" id="kanban-cancelled-count">0</span>
                            </div>
                            <div class="card-body p-2" id="kanban-huy" style="min-height: 400px;">
                                <!-- Kanban items -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Detail Modal -->
<div class="modal fade" id="taskDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết công việc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="taskDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <div id="taskActions">
                    <!-- Action buttons will be added dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật tiến độ công việc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="progressForm">
                    <input type="hidden" id="task_id" name="task_id">
                    
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

                    <div class="mb-3">
                        <label class="form-label">Nội dung thực hiện <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="noi_dung_thuc_hien" rows="3" required 
                                  placeholder="Mô tả chi tiết công việc đã thực hiện..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kết quả</label>
                        <textarea class="form-control" name="ket_qua" rows="2" 
                                  placeholder="Kết quả đạt được..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vấn đề gặp phải</label>
                        <textarea class="form-control" name="van_de_gap_phai" rows="2" 
                                  placeholder="Các vấn đề, khó khăn gặp phải..."></textarea>
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
                                    <input type="number" class="form-control" name="materials[0][so_luong]" 
                                           placeholder="Số lượng" step="0.001">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="materials[0][don_gia]" 
                                           placeholder="Đơn giá">
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
                                <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                                <select class="form-select" name="trang_thai" required>
                                    <option value="dang_lam">Đang làm</option>
                                    <option value="hoan_thanh">Hoàn thành</option>
                                    <option value="gap_van_de">Gặp vấn đề</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveProgress()">
                    <i class="fas fa-save me-2"></i>Lưu tiến độ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentView = 'table'; // 'table' or 'kanban'

$(document).ready(function() {
    loadEquipmentOptions();
    loadUserOptions();
    loadMaterialOptions();
    loadTasksData();
    loadStatistics();
    
    // Form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadTasksData();
    });
    
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.task-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Auto-refresh every 3 minutes
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            loadStatistics();
            if (currentView === 'kanban') {
                loadKanbanData();
            }
        }
    }, 180000);
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

// Load user options
function loadUserOptions() {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_users' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Tất cả</option>';
            response.data.forEach(user => {
                options += `<option value="${user.id}">${user.full_name}</option>`;
            });
            $('#filter_assignee').html(options);
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
            $('#new-tasks-count').text(stats.new_tasks || 0);
            $('#assigned-tasks-count').text(stats.assigned_tasks || 0);
            $('#progress-tasks-count').text(stats.progress_tasks || 0);
            $('#completed-tasks-count').text(stats.completed_this_week || 0);
        }
    });
}

// Load tasks data
function loadTasksData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'list');
    formData.append('page', currentPage);
    
    CMMS.showLoading('#tasksTableBody');
    
    CMMS.ajax('api.php', {
        method: 'POST',
        data: formData
    }).then(response => {
        CMMS.hideLoading('#tasksTableBody');
        
        if (response.success) {
            if (currentView === 'table') {
                displayTasksData(response.data);
                displayPagination(response.pagination);
            } else {
                loadKanbanData();
            }
        } else {
            $('#tasksTableBody').html('<tr><td colspan="10" class="text-center">Không có dữ liệu</td></tr>');
        }
    });
}

// Display tasks data in table
function displayTasksData(data) {
    let html = '';
    
    data.forEach(item => {
        const statusClass = getStatusClass(item.trang_thai);
        const statusText = getStatusText(item.trang_thai);
        const priorityClass = getPriorityClass(item.uu_tien);
        const priorityText = getPriorityText(item.uu_tien);
        const typeClass = getTypeClass(item.loai_cong_viec);
        const typeText = getTypeText(item.loai_cong_viec);
        const progress = calculateProgress(item);
        const isOverdue = isTaskOverdue(item);
        
        html += `
            <tr class="${isOverdue ? 'table-warning' : ''}">
                <td>
                    <input type="checkbox" class="form-check-input task-checkbox" value="${item.id}">
                </td>
                <td>
                    <div>
                        <strong>${item.tieu_de}</strong>
                        ${isOverdue ? '<span class="badge bg-danger ms-2">Quá hạn</span>' : ''}
                        <br>
                        <small class="text-muted">${item.mo_ta ? item.mo_ta.substring(0, 50) + '...' : ''}</small>
                    </div>
                </td>
                <td>
                    ${item.id_thiet_bi ? `
                        <div>
                            <strong>${item.id_thiet_bi}</strong><br>
                            <small class="text-muted">${item.ten_thiet_bi}</small>
                        </div>
                    ` : '<em class="text-muted">Không có</em>'}
                </td>
                <td>
                    <span class="badge ${typeClass}">${typeText}</span>
                </td>
                <td>
                    <div>
                        <strong>${formatDate(item.ngay_ket_thuc)}</strong><br>
                        <small class="text-muted">${getTimeRemaining(item.ngay_ket_thuc)}</small>
                    </div>
                </td>
                <td>
                    <div>
                        ${item.nguoi_duoc_giao_name || '<em class="text-muted">Chưa giao</em>'}<br>
                        <small class="text-muted">Tạo bởi: ${item.nguoi_tao_name}</small>
                    </div>
                </td>
                <td>
                    <span class="badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <span class="badge ${priorityClass}">${priorityText}</span>
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${progress.class}" style="width: ${progress.percent}%">
                            ${progress.percent}%
                        </div>
                    </div>
                    <small class="text-muted">${progress.text}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="viewTaskDetail(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canUpdateProgress(item) ? `
                        <button class="btn btn-outline-primary" onclick="updateProgress(${item.id})" title="Cập nhật tiến độ">
                            <i class="fas fa-tasks"></i>
                        </button>
                        ` : ''}
                        ${canEditTask(item) ? `
                        <button class="btn btn-outline-warning" onclick="editTask(${item.id})" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDeleteTask(item) ? `
                        <button class="btn btn-outline-danger" onclick="deleteTask(${item.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    if (html === '') {
        html = '<tr><td colspan="10" class="text-center text-muted">Không có dữ liệu công việc</td></tr>';
    }
    
    $('#tasksTableBody').html(html);
}

// Load Kanban data
function loadKanbanData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'kanban');
    
    CMMS.ajax('api.php', {
        method: 'POST',
        data: formData
    }).then(response => {
        if (response.success) {
            displayKanbanData(response.data);
        }
    });
}

// Display Kanban data
function displayKanbanData(data) {
    // Clear all columns
    const statuses = ['moi_tao', 'da_giao', 'dang_thuc_hien', 'cho_xac_nhan', 'hoan_thanh', 'huy'];
    statuses.forEach(status => {
        $(`#kanban-${status}`).empty();
    });
    
    // Group tasks by status
    const grouped = {};
    data.forEach(task => {
        if (!grouped[task.trang_thai]) {
            grouped[task.trang_thai] = [];
        }
        grouped[task.trang_thai].push(task);
    });
    
    // Update counts and populate columns
    statuses.forEach(status => {
        const tasks = grouped[status] || [];
        const count = tasks.length;
        
        // Update count badge
        const countElement = status === 'moi_tao' ? '#kanban-new-count' :
                           status === 'da_giao' ? '#kanban-assigned-count' :
                           status === 'dang_thuc_hien' ? '#kanban-progress-count' :
                           status === 'cho_xac_nhan' ? '#kanban-review-count' :
                           status === 'hoan_thanh' ? '#kanban-completed-count' :
                           '#kanban-cancelled-count';
        $(countElement).text(count);
        
        // Populate column
        tasks.forEach(task => {
            const isOverdue = isTaskOverdue(task);
            const progress = calculateProgress(task);
            
            const taskCard = `
                <div class="card mb-2 task-card ${isOverdue ? 'border-danger' : ''}" 
                     data-task-id="${task.id}" onclick="viewTaskDetail(${task.id})">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1" style="font-size: 0.9rem;">${task.tieu_de}</h6>
                        <p class="card-text mb-2" style="font-size: 0.8rem;">
                            ${task.mo_ta ? task.mo_ta.substring(0, 60) + '...' : ''}
                        </p>
                        
                        ${task.id_thiet_bi ? `
                        <div class="mb-2">
                            <small class="badge bg-secondary">${task.id_thiet_bi}</small>
                        </div>
                        ` : ''}
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge ${getPriorityClass(task.uu_tien)}" style="font-size: 0.7rem;">
                                ${getPriorityText(task.uu_tien)}
                            </span>
                            <span class="badge ${getTypeClass(task.loai_cong_viec)}" style="font-size: 0.7rem;">
                                ${getTypeText(task.loai_cong_viec)}
                            </span>
                        </div>
                        
                        <div class="progress mb-2" style="height: 4px;">
                            <div class="progress-bar ${progress.class}" style="width: ${progress.percent}%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">${formatDate(task.ngay_ket_thuc)}</small>
                            ${task.nguoi_duoc_giao_name ? `
                            <small class="text-muted">
                                <i class="fas fa-user"></i> ${task.nguoi_duoc_giao_name.split(' ').pop()}
                            </small>
                            ` : ''}
                        </div>
                        
                        ${isOverdue ? '<div class="text-danger small mt-1"><i class="fas fa-clock"></i> Quá hạn</div>' : ''}
                    </div>
                </div>
            `;
            
            $(`#kanban-${status}`).append(taskCard);
        });
    });
}

// Helper functions
function getStatusClass(status) {
    const classes = {
        'moi_tao': 'bg-secondary',
        'da_giao': 'bg-warning',
        'dang_thuc_hien': 'bg-primary',
        'cho_xac_nhan': 'bg-info',
        'hoan_thanh': 'bg-success',
        'huy': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'moi_tao': 'Mới tạo',
        'da_giao': 'Đã giao',
        'dang_thuc_hien': 'Đang thực hiện',
        'cho_xac_nhan': 'Chờ xác nhận',
        'hoan_thanh': 'Hoàn thành',
        'huy': 'Hủy'
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
        'bao_tri': 'bg-primary',
        'sua_chua': 'bg-warning',
        'kiem_tra': 'bg-info',
        'lap_dat': 'bg-success',
        'khac': 'bg-secondary'
    };
    return classes[type] || 'bg-secondary';
}

function getTypeText(type) {
    const texts = {
        'bao_tri': 'Bảo trì',
        'sua_chua': 'Sửa chữa',
        'kiem_tra': 'Kiểm tra',
        'lap_dat': 'Lắp đặt',
        'khac': 'Khác'
    };
    return texts[type] || 'Không xác định';
}

function calculateProgress(task) {
    let percent = 0;
    let text = 'Chưa bắt đầu';
    let progressClass = 'bg-secondary';
    
    switch (task.trang_thai) {
        case 'moi_tao':
            percent = 0;
            text = 'Chưa bắt đầu';
            progressClass = 'bg-secondary';
            break;
        case 'da_giao':
            percent = 10;
            text = 'Đã giao việc';
            progressClass = 'bg-warning';
            break;
        case 'dang_thuc_hien':
            percent = 50;
            text = 'Đang thực hiện';
            progressClass = 'bg-primary';
            break;
        case 'cho_xac_nhan':
            percent = 80;
            text = 'Chờ xác nhận';
            progressClass = 'bg-info';
            break;
        case 'hoan_thanh':
            percent = 100;
            text = 'Hoàn thành';
            progressClass = 'bg-success';
            break;
        case 'huy':
            percent = 0;
            text = 'Đã hủy';
            progressClass = 'bg-danger';
            break;
    }
    
    return {
        percent: percent,
        text: text,
        class: progressClass
    };
}

function isTaskOverdue(task) {
    if (task.trang_thai === 'hoan_thanh' || task.trang_thai === 'huy') {
        return false;
    }
    
    const today = new Date();
    const endDate = new Date(task.ngay_ket_thuc);
    return endDate < today;
}

function getTimeRemaining(endDate) {
    const today = new Date();
    const end = new Date(endDate);
    const diffTime = end - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 0) {
        return `Quá hạn ${Math.abs(diffDays)} ngày`;
    } else if (diffDays === 0) {
        return 'Hôm nay';
    } else if (diffDays === 1) {
        return 'Ngày mai';
    } else {
        return `Còn ${diffDays} ngày`;
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

function canUpdateProgress(task) {
    return ['admin', 'to_truong', 'user'].includes(CMMS.userRole) && 
           ['da_giao', 'dang_thuc_hien'].includes(task.trang_thai) &&
           (CMMS.userRole === 'admin' || CMMS.userRole === 'to_truong' || task.nguoi_duoc_giao == CMMS.userId);
}

function canEditTask(task) {
    return ['admin', 'to_truong'].includes(CMMS.userRole) && 
           !['hoan_thanh', 'huy'].includes(task.trang_thai);
}

function canDeleteTask(task) {
    return ['admin', 'to_truong'].includes(CMMS.userRole) && 
           task.trang_thai === 'moi_tao';
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
        loadTasksData();
    }
}

// Toggle view between table and kanban
function toggleView() {
    if (currentView === 'table') {
        currentView = 'kanban';
        $('#tableView').hide();
        $('#kanbanView').show();
        $('#viewToggle').html('<i class="fas fa-table me-2"></i>Bảng');
        loadKanbanData();
    } else {
        currentView = 'table';
        $('#kanbanView').hide();
        $('#tableView').show();
        $('#viewToggle').html('<i class="fas fa-th-large me-2"></i>Kanban');
        loadTasksData();
    }
}

// Quick filter
function quickFilter(type) {
    document.getElementById('filterForm').reset();
    
    switch (type) {
        case 'my_tasks':
            $('#filter_assignee').val(CMMS.userId);
            break;
        case 'urgent':
            $('#filter_uu_tien').val('khan_cap');
            break;
        case 'overdue':
            // This will be handled in backend
            $('#filter_search').val('overdue');
            break;
        case 'this_week':
            $('#filter_search').val('this_week');
            break;
    }
    
    currentPage = 1;
    loadTasksData();
}

// Reset filter
function resetFilter() {
    document.getElementById('filterForm').reset();
    currentPage = 1;
    loadTasksData();
}

// View task detail
function viewTaskDetail(id) {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'detail', id: id }
    }).then(response => {
        if (response.success) {
            $('#taskDetailContent').html(generateTaskDetailHTML(response.data));
            $('#taskDetailModal').modal('show');
            
            // Add action buttons
            const actions = generateTaskActionButtons(response.data);
            $('#taskActions').html(actions);
        } else {
            CMMS.showAlert('Không thể tải chi tiết công việc', 'error');
        }
    });
}

// Generate task detail HTML
function generateTaskDetailHTML(data) {
    const progress = calculateProgress(data);
    const isOverdue = isTaskOverdue(data);
    
    return `
        <div class="row">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">${data.tieu_de}</h5>
                    ${isOverdue ? '<span class="badge bg-danger">Quá hạn</span>' : ''}
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr><th width="40%">Thiết bị:</th><td>${data.id_thiet_bi ? `<strong>${data.id_thiet_bi} - ${data.ten_thiet_bi}</strong>` : '<em>Không có</em>'}</td></tr>
                            <tr><th>Loại công việc:</th><td><span class="badge ${getTypeClass(data.loai_cong_viec)}">${getTypeText(data.loai_cong_viec)}</span></td></tr>
                            <tr><th>Ưu tiên:</th><td><span class="badge ${getPriorityClass(data.uu_tien)}">${getPriorityText(data.uu_tien)}</span></td></tr>
                            <tr><th>Thời gian dự kiến:</th><td>${data.gio_du_kien} giờ</td></tr>
                            <tr><th>Ngày bắt đầu:</th><td>${formatDate(data.ngay_bat_dau)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr><th width="40%">Ngày kết thúc:</th><td><strong>${formatDate(data.ngay_ket_thuc)}</strong></td></tr>
                            <tr><th>Trạng thái:</th><td><span class="badge ${getStatusClass(data.trang_thai)}">${getStatusText(data.trang_thai)}</span></td></tr>
                            <tr><th>Người tạo:</th><td>${data.nguoi_tao_name}</td></tr>
                            <tr><th>Người được giao:</th><td>${data.nguoi_duoc_giao_name || 'Chưa giao'}</td></tr>
                            <tr><th>Người xác nhận:</th><td>${data.nguoi_xac_nhan_name || 'Chưa có'}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>Mô tả:</h6>
                    <p class="text-muted">${data.mo_ta || 'Không có mô tả'}</p>
                </div>
                
                <div class="mt-3">
                    <h6>Ghi chú:</h6>
                    <p class="text-muted">${data.ghi_chu || 'Không có ghi chú'}</p>
                </div>
                
                ${data.execution_details && data.execution_details.length > 0 ? `
                <div class="mt-4">
                    <h6>Chi tiết thực hiện:</h6>
                    <div class="timeline">
                        ${data.execution_details.map(detail => `
                            <div class="timeline-item mb-3">
                                <div class="timeline-marker bg-${detail.trang_thai === 'hoan_thanh' ? 'success' : 'primary'}"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1">${formatDate(detail.ngay_thuc_hien)}</h6>
                                        <small class="text-muted">${detail.gio_bat_dau} - ${detail.gio_ket_thuc}</small>
                                    </div>
                                    <p class="mb-1">${detail.noi_dung_thuc_hien}</p>
                                    ${detail.ket_qua ? `<small class="text-success">Kết quả: ${detail.ket_qua}</small><br>` : ''}
                                    ${detail.van_de_gap_phai ? `<small class="text-warning">Vấn đề: ${detail.van_de_gap_phai}</small><br>` : ''}
                                    <small class="text-muted">Bởi: ${detail.nguoi_thuc_hien_name}</small>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Tiến độ</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar ${progress.class}" style="width: ${progress.percent}%">
                                ${progress.percent}%
                            </div>
                        </div>
                        <p class="mb-2">${progress.text}</p>
                        <small class="text-muted">${getTimeRemaining(data.ngay_ket_thuc)}</small>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Thông tin thời gian</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            Ngày tạo: ${formatDate(data.created_at)}<br>
                            Cập nhật: ${formatDate(data.updated_at)}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Generate task action buttons
function generateTaskActionButtons(data) {
    let buttons = '';
    
    if (canUpdateProgress(data)) {
        buttons += `
            <button type="button" class="btn btn-primary me-2" onclick="updateProgress(${data.id})">
                <i class="fas fa-tasks me-2"></i>Cập nhật tiến độ
            </button>
        `;
    }
    
    if (canEditTask(data)) {
        buttons += `
            <button type="button" class="btn btn-warning me-2" onclick="editTask(${data.id})">
                <i class="fas fa-edit me-2"></i>Sửa công việc
            </button>
        `;
    }
    
    // Status change buttons based on current status and user role
    if (data.trang_thai === 'moi_tao' && ['admin', 'to_truong'].includes(CMMS.userRole)) {
        buttons += `
            <button type="button" class="btn btn-success me-2" onclick="assignTask(${data.id})">
                <i class="fas fa-user-check me-2"></i>Giao việc
            </button>
        `;
    }
    
    if (data.trang_thai === 'cho_xac_nhan' && ['admin', 'truong_ca'].includes(CMMS.userRole)) {
        buttons += `
            <button type="button" class="btn btn-success me-2" onclick="approveTask(${data.id})">
                <i class="fas fa-check me-2"></i>Xác nhận hoàn thành
            </button>
        `;
    }
    
    return buttons;
}

// Update progress
function updateProgress(id) {
    $('#task_id').val(id);
    $('#progressForm')[0].reset();
    $('#task_id').val(id); // Set again after reset
    updateMaterialSelects();
    $('#updateProgressModal').modal('show');
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
                <input type="number" class="form-control" name="materials[${index}][so_luong]" 
                       placeholder="Số lượng" step="0.001">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control" name="materials[${index}][don_gia]" 
                       placeholder="Đơn giá">
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

// Save progress
function saveProgress() {
    const formData = new FormData(document.getElementById('progressForm'));
    formData.append('action', 'update_progress');
    
    CMMS.ajax('api.php', {
        data: formData
    }).then(response => {
        if (response.success) {
            CMMS.showAlert('Cập nhật tiến độ thành công!', 'success');
            $('#updateProgressModal').modal('hide');
            loadTasksData();
            loadStatistics();
        } else {
            CMMS.showAlert(response.message, 'error');
        }
    });
}

// Edit task
function editTask(id) {
    window.location.href = `edit.php?id=${id}`;
}

// Delete task
function deleteTask(id) {
    CMMS.confirm('Bạn có chắc chắn muốn xóa công việc này?', 'Xác nhận xóa').then((result) => {
        if (result.isConfirmed) {
            CMMS.ajax('api.php', {
                data: { action: 'delete', id: id }
            }).then(response => {
                if (response.success) {
                    CMMS.showAlert('Xóa công việc thành công', 'success');
                    loadTasksData();
                    loadStatistics();
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            });
        }
    });
}

// Assign task
function assignTask(id) {
    // Implementation for task assignment modal
    CMMS.showAlert('Chức năng giao việc đang được phát triển', 'info');
}

// Approve task
function approveTask(id) {
    CMMS.confirm('Bạn có chắc chắn xác nhận công việc này đã hoàn thành?', 'Xác nhận hoàn thành').then((result) => {
        if (result.isConfirmed) {
            CMMS.ajax('api.php', {
                data: { action: 'approve', id: id }
            }).then(response => {
                if (response.success) {
                    CMMS.showAlert('Xác nhận hoàn thành công việc thành công', 'success');
                    loadTasksData();
                    loadStatistics();
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            });
        }
    });
}

// Export tasks
function exportTasks() {
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
</script>

<style>
/* Kanban specific styles */
.task-card {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

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
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #28a745;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #kanbanView .col-lg-2 {
        margin-bottom: 1rem;
    }
    
    .task-card .card-title {
        font-size: 0.85rem !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>