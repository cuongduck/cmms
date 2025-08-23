<?php
/**
 * Equipment Groups Management View - /modules/structure/views/equipment_groups.php
 * Quản lý cụm thiết bị - chỉ phụ thuộc vào dòng máy
 */

$pageTitle = 'Quản lý cụm thiết bị';
$currentModule = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị', 'url' => '/modules/structure/'],
    ['title' => 'Quản lý cụm thiết bị']
];

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('structure', 'view');

$pageActions = '';
if (hasPermission('structure', 'create')) {
    $pageActions = '
    <div class="btn-group">
        <button type="button" class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus me-1"></i> Thêm cụm thiết bị
        </button>
        <button type="button" class="btn btn-outline-success" onclick="exportData()">
            <i class="fas fa-download me-1"></i> Xuất Excel
        </button>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-arrow-left me-1"></i> Quản lý khác
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="industries.php"><i class="fas fa-industry me-2"></i>Ngành</a></li>
                <li><a class="dropdown-item" href="workshops.php"><i class="fas fa-building me-2"></i>Xưởng</a></li>
                <li><a class="dropdown-item" href="lines.php"><i class="fas fa-stream me-2"></i>Line sản xuất</a></li>
                <li><a class="dropdown-item" href="areas.php"><i class="fas fa-map-marked-alt me-2"></i>Khu vực</a></li>
                <li><a class="dropdown-item" href="machine_types.php"><i class="fas fa-cogs me-2"></i>Dòng máy</a></li>
            </ul>
        </div>
    </div>';
}

require_once '../../../includes/header.php';

// Get machine types for filter
$machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
?>

<style>
.equipment-groups-container {
    background: #f8fafc;
}

.filter-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

.data-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table th {
    background: #f8fafc;
    border: none;
    font-weight: 600;
    color: #374151;
    padding: 1rem 0.75rem;
}

.table td {
    border: none;
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}

.table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

.btn-action {
    padding: 0.25rem 0.5rem;
    margin: 0 0.125rem;
    border-radius: 0.375rem;
    border: none;
    transition: all 0.2s ease;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
    z-index: 10;
    border-radius: 12px;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.modal-header {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    border-radius: 12px 12px 0 0;
}

.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.form-floating .form-control:focus,
.form-floating .form-select:focus {
    border-color: #1e3a8a;
    box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
}

.equipment-group-code {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
    display: inline-block;
}

.machine-type-badge {
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: white;
    font-size: 0.7rem;
    padding: 0.3rem 0.6rem;
    border-radius: 0.3rem;
    font-weight: 500;
}

.info-card {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.info-card .icon {
    color: #2563eb;
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .table-responsive {
        border-radius: 12px;
    }
    
    .btn-action {
        padding: 0.125rem 0.25rem;
        font-size: 0.75rem;
    }
    
    .filter-card .row > div {
        margin-bottom: 0.5rem;
    }
}
</style>

<div class="equipment-groups-container">
    <!-- Info Card -->
    <div class="info-card">
        <div class="d-flex align-items-center">
            <div class="icon me-3">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h6 class="mb-1">Cụm thiết bị theo dòng máy</h6>
                <p class="mb-0 text-muted small">
                    Mỗi cụm thiết bị chỉ thuộc về một dòng máy cụ thể và có thể được sử dụng chung cho nhiều ngành/xưởng/line khác nhau.
                    Điều này giúp tối ưu hóa việc quản lý và phân loại thiết bị theo chức năng.
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                        <label for="searchInput">Tìm kiếm tên, mã cụm thiết bị...</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="machineTypeFilter">
                            <option value="">Tất cả dòng máy</option>
                            <?php foreach ($machineTypes as $machineType): ?>
                                <option value="<?php echo $machineType['id']; ?>">
                                    <?php echo htmlspecialchars($machineType['name']); ?> (<?php echo htmlspecialchars($machineType['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="machineTypeFilter">Dòng máy</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="statusFilter">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                        <label for="statusFilter">Trạng thái</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="sortFilter">
                            <option value="name_asc">Tên A-Z</option>
                            <option value="name_desc">Tên Z-A</option>
                            <option value="code_asc">Mã A-Z</option>
                            <option value="code_desc">Mã Z-A</option>
                            <option value="machine_type_name_asc">Dòng máy A-Z</option>
                            <option value="created_desc">Mới nhất</option>
                            <option value="created_asc">Cũ nhất</option>
                        </select>
                        <label for="sortFilter">Sắp xếp</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-secondary w-100 h-100" onclick="resetFilters()" title="Reset bộ lọc">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="data-table position-relative">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Tên cụm thiết bị</th>
                        <th style="width: 120px;">Mã cụm</th>
                        <th style="width: 200px;">Dòng máy</th>
                        <th style="width: 250px;">Mô tả</th>
                        <th style="width: 80px;">Thiết bị</th>
                        <th style="width: 100px;">Trạng thái</th>
                        <th style="width: 120px;">Ngày tạo</th>
                        <th style="width: 120px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div class="empty-state d-none" id="emptyState">
            <i class="fas fa-layer-group"></i>
            <h5>Chưa có cụm thiết bị</h5>
            <p>Bắt đầu bằng cách thêm cụm thiết bị đầu tiên</p>
            <?php if (hasPermission('structure', 'create')): ?>
            <button type="button" class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus me-1"></i> Thêm cụm thiết bị
            </button>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted">
                <span id="paginationInfo">Hiển thị 0 - 0 trong tổng số 0 mục</span>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="pagination">
                    <!-- Pagination will be generated here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="equipmentGroupModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-layer-group me-2"></i>
                    <span id="modalTitleText">Thêm cụm thiết bị mới</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="equipmentGroupForm" novalidate>
                    <input type="hidden" id="equipmentGroupId" name="id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeId" name="machine_type_id" required>
                                    <option value="">Chọn dòng máy</option>
                                    <?php foreach ($machineTypes as $machineType): ?>
                                        <option value="<?php echo $machineType['id']; ?>">
                                            <?php echo htmlspecialchars($machineType['name']); ?> (<?php echo htmlspecialchars($machineType['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="machineTypeId">Dòng máy *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chọn dòng máy mà cụm thiết bị này thuộc về</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="equipmentGroupName" name="name" 
                                       placeholder="Tên cụm thiết bị" required maxlength="100">
                                <label for="equipmentGroupName">Tên cụm thiết bị *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control text-uppercase" id="equipmentGroupCode" name="code" 
                                       placeholder="Mã cụm" required maxlength="20" pattern="[A-Z0-9_]+">
                                <label for="equipmentGroupCode">Mã cụm *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chỉ chữ hoa, số và dấu _ (VD: GROUP_01)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="equipmentGroupStatus" name="status" required>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                                <label for="equipmentGroupStatus">Trạng thái *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0 py-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Mã cụm chỉ cần duy nhất trong từng dòng máy</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" id="equipmentGroupDescription" name="description" 
                                          placeholder="Mô tả" style="height: 100px;" maxlength="1000"></textarea>
                                <label for="equipmentGroupDescription">Mô tả chi tiết</label>
                                <div class="form-text">
                                    <span id="descriptionCounter">0</span>/1000 ký tự
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Hủy
                </button>
                <button type="button" class="btn btn-primary" onclick="saveEquipmentGroup()">
                    <i class="fas fa-save me-1"></i> Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Xác nhận xóa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa cụm thiết bị <strong id="deleteItemName"></strong>?</p>
                <p class="text-muted small">Hành động này sẽ xóa tất cả dữ liệu liên quan và không thể hoàn tác.</p>
                <input type="hidden" id="deleteItemId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash me-1"></i> Xóa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentPage = 1;
let currentData = [];
let currentFilters = {
    search: '',
    machine_type_id: '',
    status: 'all',
    sort_by: 'name',
    sort_order: 'ASC'
};

// Define permissions from PHP
const canEdit = <?php echo json_encode(hasPermission('structure', 'edit')); ?>;
const canDelete = <?php echo json_encode(hasPermission('structure', 'delete')); ?>;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Equipment Groups page loaded, initializing...');
    
    // Wait for Bootstrap to be available
    if (typeof bootstrap === 'undefined') {
        console.log('Bootstrap not yet loaded, waiting...');
        const checkBootstrap = setInterval(() => {
            if (typeof bootstrap !== 'undefined') {
                clearInterval(checkBootstrap);
                initializePage();
            }
        }, 100);
    } else {
        initializePage();
    }
});

function initializePage() {
    console.log('Initializing page with Bootstrap ready...');
    initializeEventListeners();
    loadData();
}

// Show/hide loading overlay
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
    }
}

// Initialize event listeners
function initializeEventListeners() {
    console.log('Initializing event listeners...');
    
    // Filter form
    const filterInputs = document.querySelectorAll('#filterForm input, #filterForm select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            console.log('Filter changed:', this.id, this.value);
            currentPage = 1;
            loadData();
        });
    });

    // Search input with debounce
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                console.log('Search input:', this.value);
                currentPage = 1;
                loadData();
            }, 500);
        });
    }

    // Form validation
    const form = document.getElementById('equipmentGroupForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveEquipmentGroup();
        });
    }

    // Code input formatting
    const codeInput = document.getElementById('equipmentGroupCode');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
        });
    }

    // Character counter
    setupCharacterCounter('equipmentGroupDescription', 'descriptionCounter');

    // Modal events
    const modal = document.getElementById('equipmentGroupModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            resetForm();
        });
    }
}

// Setup character counter for textarea
function setupCharacterCounter(textareaId, counterId) {
    const textarea = document.getElementById(textareaId);
    const counter = document.getElementById(counterId);
    
    if (textarea && counter) {
        textarea.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }
}

// Load data from API
async function loadData() {
    console.log('Loading equipment groups data...');
    try {
        showLoading(true);
        
        // Get filter values
        const searchEl = document.getElementById('searchInput');
        const machineTypeEl = document.getElementById('machineTypeFilter');
        const statusEl = document.getElementById('statusFilter');
        const sortEl = document.getElementById('sortFilter');
        
        // Parse sort filter
        let sortBy = 'name';
        let sortOrder = 'ASC';
        if (sortEl && sortEl.value) {
            const [field, order] = sortEl.value.split('_');
            sortBy = field === 'created' ? 'created_at' : field;
            sortOrder = order.toUpperCase();
        }
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            machine_type_id: machineTypeEl ? machineTypeEl.value : '',
            status: statusEl ? statusEl.value : 'all',
            sort_by: sortBy,
            sort_order: sortOrder
        };

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        const apiUrl = `../api/equipment_groups.php?${params}`;
        console.log('Fetching:', apiUrl);

        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data) {
            currentData = result.data.equipment_groups || [];
            renderTable(currentData);
            renderPagination(result.data.pagination);
            updatePaginationInfo(result.data.pagination);
        } else {
            console.error('API Error:', result.message);
            showNotification(result.message || 'Lỗi khi tải dữ liệu', 'error');
            renderTable([]);
        }
    } catch (error) {
        console.error('Load data error:', error);
        showNotification('Lỗi kết nối: ' + error.message, 'error');
        renderTable([]);
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable(data) {
    console.log('renderTable called with data:', data);
    
    const tbody = document.getElementById('dataTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (!tbody) {
        console.error('dataTableBody element not found!');
        return;
    }
    
    if (!data || data.length === 0) {
        console.log('No data to display, showing empty state');
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('d-none');
        return;
    }
    
    if (emptyState) emptyState.classList.add('d-none');
    
    const startIndex = (currentPage - 1) * 20;
    console.log('Rendering', data.length, 'items starting from index', startIndex);
    
    const rows = data.map((item, index) => {
        return `
            <tr>
                <td class="text-muted">${startIndex + index + 1}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.name)}</div>
                    <small class="text-muted">Thuộc dòng máy: ${escapeHtml(item.machine_type_name)}</small>
                </td>
                <td>
                    <span class="equipment-group-code">${escapeHtml(item.code)}</span>
                </td>
                <td>
                    <div>
                        <div class="fw-semibold">${escapeHtml(item.machine_type_name)}</div>
                        <span class="machine-type-badge">${escapeHtml(item.machine_type_code)}</span>
                    </div>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(item.description || '')}">
                        ${item.description || '<em class="text-muted">Chưa có mô tả</em>'}
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${item.equipment_count || 0}</span>
                </td>
                <td>
                    <span class="badge ${item.status_class}" style="cursor: pointer;" 
                          onclick="${canEdit ? `toggleStatus(${item.id})` : ''}"
                          title="${canEdit ? 'Click để thay đổi trạng thái' : ''}">
                        ${item.status_text}
                    </span>
                </td>
                <td class="text-muted small">${item.created_at_formatted}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-info btn-action" 
                                onclick="viewEquipmentGroup(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canEdit ? `
                        <button type="button" class="btn btn-outline-primary btn-action" 
                                onclick="editEquipmentGroup(${item.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button type="button" class="btn btn-outline-danger btn-action" 
                                onclick="deleteEquipmentGroup(${item.id}, '${escapeHtml(item.name)}')" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = rows.join('');
    console.log('Table rendered successfully with', rows.length, 'rows');
}

// Render pagination
function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    if (!container || !pagination) return;
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    
    if (pagination.has_previous) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">‹</a>
        </li>`;
    }
    
    const start = Math.max(1, pagination.current_page - 2);
    const end = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    if (start > 1) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(1)">1</a>
        </li>`;
        if (start > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    for (let i = start; i <= end; i++) {
        html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
        </li>`;
    }
    
    if (end < pagination.total_pages) {
        if (end < pagination.total_pages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(${pagination.total_pages})">${pagination.total_pages}</a>
        </li>`;
    }
    
    if (pagination.has_next) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">›</a>
        </li>`;
    }
    
    container.innerHTML = html;
}

// Update pagination info
function updatePaginationInfo(pagination) {
    const info = document.getElementById('paginationInfo');
    if (!info || !pagination) return;
    
    const start = (pagination.current_page - 1) * pagination.per_page + 1;
    const end = Math.min(start + pagination.per_page - 1, pagination.total_items);
    
    info.textContent = `Hiển thị ${start} - ${end} trong tổng số ${pagination.total_items} mục`;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadData();
}

// Show add modal
function showAddModal() {
    document.getElementById('modalTitleText').textContent = 'Thêm cụm thiết bị mới';
    document.getElementById('equipmentGroupId').value = '';
    resetForm();
    
    const modal = new bootstrap.Modal(document.getElementById('equipmentGroupModal'));
    modal.show();
    
    setTimeout(() => {
        const machineTypeSelect = document.getElementById('machineTypeId');
        if (machineTypeSelect) machineTypeSelect.focus();
    }, 500);
}

// Edit equipment group
async function editEquipmentGroup(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/equipment_groups.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            document.getElementById('modalTitleText').textContent = 'Chỉnh sửa cụm thiết bị';
            document.getElementById('equipmentGroupId').value = data.id;
            document.getElementById('machineTypeId').value = data.machine_type_id;
            document.getElementById('equipmentGroupName').value = data.name;
            document.getElementById('equipmentGroupCode').value = data.code;
            document.getElementById('equipmentGroupDescription').value = data.description || '';
            document.getElementById('equipmentGroupStatus').value = data.status;
            
            // Update counter
            updateCounter('descriptionCounter', data.description || '');
            
            const modal = new bootstrap.Modal(document.getElementById('equipmentGroupModal'));
            modal.show();
        } else {
            showNotification(result.message || 'Lỗi khi tải dữ liệu', 'error');
        }
    } catch (error) {
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// View equipment group details
async function viewEquipmentGroup(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/equipment_groups.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            const equipmentListHtml = data.equipment_list && data.equipment_list.length > 0 
                ? data.equipment_list.map(eq => `
                    <tr>
                        <td>${escapeHtml(eq.code)}</td>
                        <td>${escapeHtml(eq.name)}</td>
                        <td><span class="badge ${getStatusClass(eq.status)}">${getStatusText(eq.status)}</span></td>
                    </tr>
                `).join('')
                : '<tr><td colspan="3" class="text-center text-muted">Chưa có thiết bị nào</td></tr>';
            
            const modalContent = `
                <div class="modal fade" id="viewModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-layer-group me-2"></i>
                                    Chi tiết cụm thiết bị
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Tên cụm thiết bị:</strong></td><td>${escapeHtml(data.name)}</td></tr>
                                            <tr><td><strong>Mã cụm:</strong></td><td><span class="equipment-group-code">${escapeHtml(data.code)}</span></td></tr>
                                            <tr><td><strong>Dòng máy:</strong></td><td>
                                                <div>${escapeHtml(data.machine_type_name)}</div>
                                                <span class="machine-type-badge">${escapeHtml(data.machine_type_code)}</span>
                                            </td></tr>
                                            <tr><td><strong>Trạng thái:</strong></td><td><span class="badge ${data.status === 'active' ? 'bg-success' : 'bg-secondary'}">${data.status === 'active' ? 'Hoạt động' : 'Không hoạt động'}</span></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Số thiết bị:</strong></td><td><span class="badge bg-secondary">${data.equipment_count || 0}</span></td></tr>
                                            <tr><td><strong>Người tạo:</strong></td><td>${escapeHtml(data.created_by_name || 'N/A')}</td></tr>
                                            <tr><td><strong>Ngày tạo:</strong></td><td>${formatDateTime(data.created_at)}</td></tr>
                                            <tr><td><strong>Cập nhật:</strong></td><td>${formatDateTime(data.updated_at)}</td></tr>
                                        </table>
                                    </div>
                                </div>
                                ${data.description ? `
                                <hr>
                                <h6><strong>Mô tả:</strong></h6>
                                <p class="text-muted">${escapeHtml(data.description)}</p>
                                ` : ''}
                                <hr>
                                <h6><strong>Danh sách thiết bị trong cụm:</strong></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Mã thiết bị</th>
                                                <th>Tên thiết bị</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${equipmentListHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                ${canEdit ? `<button type="button" class="btn btn-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); setTimeout(() => editEquipmentGroup(${data.id}), 300);">Chỉnh sửa</button>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal and add new one
            const existingModal = document.getElementById('viewModal');
            if (existingModal) existingModal.remove();
            
            document.body.insertAdjacentHTML('beforeend', modalContent);
            
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
            
            document.getElementById('viewModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            showNotification(result.message || 'Lỗi khi tải dữ liệu', 'error');
        }
    } catch (error) {
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Delete equipment group
function deleteEquipmentGroup(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Confirm delete
async function confirmDelete() {
    const id = document.getElementById('deleteItemId').value;
    
    try {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch('../api/equipment_groups.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
            if (modal) modal.hide();
            loadData(); // Reload data
        } else {
            showNotification(result.message || 'Lỗi khi xóa cụm thiết bị', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Save equipment group (create/update)
async function saveEquipmentGroup() {
    if (!validateForm()) {
        return;
    }
    
    try {
        showLoading(true);
        
        const formData = new FormData();
        
        const id = document.getElementById('equipmentGroupId').value;
        formData.append('action', id ? 'update' : 'create');
        
        if (id) {
            formData.append('id', id);
        }
        
        formData.append('machine_type_id', document.getElementById('machineTypeId').value);
        formData.append('name', document.getElementById('equipmentGroupName').value.trim());
        formData.append('code', document.getElementById('equipmentGroupCode').value.trim().toUpperCase());
        formData.append('description', document.getElementById('equipmentGroupDescription').value.trim());
        formData.append('status', document.getElementById('equipmentGroupStatus').value);
        
        const response = await fetch('../api/equipment_groups.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('equipmentGroupModal'));
            if (modal) modal.hide();
            loadData(); // Reload data
        } else {
            showNotification(result.message || 'Lỗi khi lưu cụm thiết bị', 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Toggle status
async function toggleStatus(id) {
    try {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('id', id);
        
        const response = await fetch('../api/equipment_groups.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            loadData(); // Reload data
        } else {
            showNotification(result.message || 'Lỗi khi thay đổi trạng thái', 'error');
        }
    } catch (error) {
        console.error('Toggle status error:', error);
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Form validation
function validateForm() {
    let isValid = true;
    
    // Clear previous errors
    clearAllErrors();
    
    // Required fields
    const machineTypeId = document.getElementById('machineTypeId').value;
    const name = document.getElementById('equipmentGroupName').value.trim();
    const code = document.getElementById('equipmentGroupCode').value.trim();
    
    if (!machineTypeId) {
        showFieldError('machineTypeId', 'Vui lòng chọn dòng máy');
        isValid = false;
    }
    
    if (!name) {
        showFieldError('equipmentGroupName', 'Vui lòng nhập tên cụm thiết bị');
        isValid = false;
    } else if (name.length < 2 || name.length > 100) {
        showFieldError('equipmentGroupName', 'Tên phải từ 2-100 ký tự');
        isValid = false;
    }
    
    if (!code) {
        showFieldError('equipmentGroupCode', 'Vui lòng nhập mã cụm thiết bị');
        isValid = false;
    } else if (!/^[A-Z0-9_]+$/.test(code)) {
        showFieldError('equipmentGroupCode', 'Mã chỉ được chứa chữ hoa, số và dấu gạch dưới');
        isValid = false;
    } else if (code.length < 2 || code.length > 20) {
        showFieldError('equipmentGroupCode', 'Mã phải từ 2-20 ký tự');
        isValid = false;
    }
    
    // Optional field validation
    const description = document.getElementById('equipmentGroupDescription').value;
    
    if (description && description.length > 1000) {
        showFieldError('equipmentGroupDescription', 'Mô tả không được vượt quá 1000 ký tự');
        isValid = false;
    }
    
    return isValid;
}

// Show field error
function showFieldError(fieldId, message) {
    const element = document.getElementById(fieldId);
    if (!element) return;
    
    element.classList.add('is-invalid');
    
    let feedback = element.parentElement.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        element.parentElement.appendChild(feedback);
    }
    feedback.textContent = message;
}

// Clear all form errors
function clearAllErrors() {
    const form = document.getElementById('equipmentGroupForm');
    if (!form) return;
    
    const invalidElements = form.querySelectorAll('.is-invalid');
    invalidElements.forEach(element => {
        element.classList.remove('is-invalid');
    });
    
    const feedbacks = form.querySelectorAll('.invalid-feedback');
    feedbacks.forEach(feedback => {
        feedback.textContent = '';
    });
}

// Reset form
function resetForm() {
    const form = document.getElementById('equipmentGroupForm');
    if (form) {
        form.reset();
        clearAllErrors();
        
        // Reset counter
        updateCounter('descriptionCounter', '');
    }
}

// Update character counter
function updateCounter(counterId, text) {
    const counter = document.getElementById(counterId);
    if (counter) {
        counter.textContent = text.length;
    }
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('machineTypeFilter').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('sortFilter').value = 'name_asc';
    
    currentPage = 1;
    loadData();
}

// Export data
async function exportData() {
    try {
        showLoading(true);
        
        const params = new URLSearchParams({
            action: 'export',
            ...currentFilters
        });
        
        window.location.href = `../api/equipment_groups.php?${params}`;
        
        showNotification('Đang tải file xuất...', 'info');
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Lỗi khi xuất dữ liệu: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
}

function getStatusClass(status) {
    const classes = {
        'active': 'bg-success',
        'inactive': 'bg-secondary',
        'maintenance': 'bg-warning',
        'broken': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'active': 'Hoạt động',
        'inactive': 'Không hoạt động',
        'maintenance': 'Bảo trì',
        'broken': 'Hỏng'
    };
    return texts[status] || status;
}

// Show notification function
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    `;
    
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    // Map type to Bootstrap alert class
    const alertType = type === 'error' ? 'danger' : type;
    notification.className = `alert alert-${alertType} alert-dismissible fade show`;
    
    notification.innerHTML = `
        <i class="${icons[type] || icons.info} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.parentElement.removeChild(notification);
                }
            }, 150);
        }
    }, 5000);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>