<?php
/**
 * Workshops Management View - /modules/structure/views/workshops.php
 */

$pageTitle = 'Quản lý xưởng sản xuất';
$currentModule = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị', 'url' => '/modules/structure/'],
    ['title' => 'Quản lý xưởng']
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
            <i class="fas fa-plus me-1"></i> Thêm xưởng
        </button>
        <button type="button" class="btn btn-outline-success" onclick="exportData()">
            <i class="fas fa-download me-1"></i> Xuất Excel
        </button>
        <a href="industries.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Quản lý ngành
        </a>
    </div>';
}

require_once '../../../includes/header.php';
?>

<style>
.workshops-container {
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

.form-floating .form-control:focus {
    border-color: #1e3a8a;
    box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
}

.pagination .page-link {
    border-radius: 0.375rem;
    margin: 0 0.125rem;
    border: 1px solid #e5e7eb;
    color: #1e3a8a;
}

.pagination .page-link:hover {
    background-color: #1e3a8a;
    border-color: #1e3a8a;
    color: white;
}

.pagination .page-item.active .page-link {
    background-color: #1e3a8a;
    border-color: #1e3a8a;
}

.industry-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    display: inline-block;
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

<div class="workshops-container">
    <!-- Filters -->
    <div class="filter-card">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                        <label for="searchInput">Tìm kiếm tên, mã xưởng...</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="industryFilter">
                            <option value="">Tất cả ngành</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="industryFilter">Ngành sản xuất</label>
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
                        <select class="form-select" id="sortBy">
                            <option value="name">Tên xưởng</option>
                            <option value="code">Mã xưởng</option>
                            <option value="industry_name">Ngành</option>
                            <option value="created_at">Ngày tạo</option>
                        </select>
                        <label for="sortBy">Sắp xếp theo</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <select class="form-select" id="sortOrder">
                            <option value="ASC">Tăng dần</option>
                            <option value="DESC">Giảm dần</option>
                        </select>
                        <label for="sortOrder">Thứ tự</label>
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
                        <th>Tên xưởng</th>
                        <th style="width: 100px;">Mã xưởng</th>
                        <th style="width: 140px;">Ngành</th>
                        <th>Mô tả</th>
                        <th style="width: 80px;">Lines</th>
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
            <i class="fas fa-building"></i>
            <h5>Chưa có xưởng sản xuất</h5>
            <p>Bắt đầu bằng cách thêm xưởng sản xuất đầu tiên</p>
            <?php if (hasPermission('structure', 'create')): ?>
            <button type="button" class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus me-1"></i> Thêm xưởng
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
<div class="modal fade" id="workshopModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-building me-2"></i>
                    <span id="modalTitleText">Thêm xưởng mới</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="workshopForm" novalidate>
                    <input type="hidden" id="workshopId" name="id">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="workshopName" name="name" 
                                       placeholder="Tên xưởng" required maxlength="255">
                                <label for="workshopName">Tên xưởng *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control text-uppercase" id="workshopCode" name="code" 
                                       placeholder="Mã xưởng" required maxlength="10" pattern="[A-Z0-9_]+">
                                <label for="workshopCode">Mã xưởng *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chỉ chữ hoa, số và dấu _</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating">
                                <select class="form-select" id="workshopIndustry" name="industry_id" required>
                                    <option value="">Chọn ngành...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="workshopIndustry">Ngành sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="workshopStatus" name="status" required>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                                <label for="workshopStatus">Trạng thái *</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" id="workshopDescription" name="description" 
                                          placeholder="Mô tả" style="height: 100px;" maxlength="1000"></textarea>
                                <label for="workshopDescription">Mô tả</label>
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
                <button type="button" class="btn btn-primary" onclick="saveWorkshop()">
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
                <p>Bạn có chắc chắn muốn xóa xưởng <strong id="deleteItemName"></strong>?</p>
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
let industriesData = [];
let currentFilters = {
    search: '',
    status: 'all',
    industry_id: '',
    sort_by: 'name',
    sort_order: 'ASC'
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Workshops page loaded, initializing...');
    initializeEventListeners();
    loadIndustries();
    loadData();
});

// Show/hide loading overlay
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        if (show) {
            overlay.style.display = 'flex';
        } else {
            overlay.style.display = 'none';
        }
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
    const form = document.getElementById('workshopForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveWorkshop();
        });
    }

    // Code input formatting
    const codeInput = document.getElementById('workshopCode');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
            validateCode();
        });
    }

    // Industry selection change
    const industrySelect = document.getElementById('workshopIndustry');
    if (industrySelect) {
        industrySelect.addEventListener('change', function() {
            validateCode(); // Re-validate code when industry changes
        });
    }

    // Description counter
    const descInput = document.getElementById('workshopDescription');
    const counter = document.getElementById('descriptionCounter');
    if (descInput && counter) {
        descInput.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }

    // Modal events
    const modal = document.getElementById('workshopModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            resetForm();
        });
    }
}

// Load industries for dropdowns
async function loadIndustries() {
    try {
        const response = await fetch('../api/workshops.php?action=get_industries');
        const result = await response.json();
        
        if (result.success && result.data.industries) {
            industriesData = result.data.industries;
            populateIndustryDropdowns();
        }
    } catch (error) {
        console.error('Error loading industries:', error);
    }
}

// Populate industry dropdowns
function populateIndustryDropdowns() {
    // Filter dropdown
    const filterSelect = document.getElementById('industryFilter');
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">Tất cả ngành</option>';
        industriesData.forEach(industry => {
            filterSelect.innerHTML += `<option value="${industry.id}">${industry.name} (${industry.code})</option>`;
        });
    }

    // Form dropdown
    const formSelect = document.getElementById('workshopIndustry');
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Chọn ngành...</option>';
        industriesData.forEach(industry => {
            formSelect.innerHTML += `<option value="${industry.id}">${industry.name} (${industry.code})</option>`;
        });
    }
}

// Load data from API
async function loadData() {
    console.log('Loading workshops data...');
    try {
        showLoading(true);
        
        // Get filter values with fallback
        const searchEl = document.getElementById('searchInput');
        const statusEl = document.getElementById('statusFilter');
        const industryEl = document.getElementById('industryFilter');
        const sortByEl = document.getElementById('sortBy');
        const sortOrderEl = document.getElementById('sortOrder');
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            status: statusEl ? statusEl.value : 'all',
            industry_id: industryEl ? industryEl.value : '',
            sort_by: sortByEl ? sortByEl.value : 'name',
            sort_order: sortOrderEl ? sortOrderEl.value : 'ASC'
        };

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        const apiUrl = `../api/workshops.php?${params}`;
        console.log('Fetching:', apiUrl);

        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data) {
            currentData = result.data.workshops || [];
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
    
    const canEdit = <?php echo json_encode(hasPermission('structure', 'edit')); ?>;
    const canDelete = <?php echo json_encode(hasPermission('structure', 'delete')); ?>;
    
    const rows = data.map((item, index) => {
        return `
            <tr>
                <td class="text-muted">${startIndex + index + 1}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.name)}</div>
                </td>
                <td>
                    <span class="badge bg-info">${escapeHtml(item.code)}</span>
                </td>
                <td>
                    <span class="industry-badge" title="${escapeHtml(item.industry_name)}">
                        ${escapeHtml(item.industry_code)}
                    </span>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(item.description || '')}">
                        ${item.description || '<em class="text-muted">Chưa có mô tả</em>'}
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-success">${item.lines_count || 0}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-warning">${item.equipment_count || 0}</span>
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
                                onclick="viewWorkshop(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canEdit ? `
                        <button type="button" class="btn btn-outline-primary btn-action" 
                                onclick="editWorkshop(${item.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button type="button" class="btn btn-outline-danger btn-action" 
                                onclick="deleteWorkshop(${item.id}, '${escapeHtml(item.name)}')" title="Xóa">
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
    
    // Previous button
    if (pagination.has_previous) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">‹</a>
        </li>`;
    }
    
    // Page numbers
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
    
    // Next button
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
    document.getElementById('modalTitleText').textContent = 'Thêm xưởng mới';
    document.getElementById('workshopId').value = '';
    resetForm();
    
    const modal = new bootstrap.Modal(document.getElementById('workshopModal'));
    modal.show();
    
    // Focus on name input
    setTimeout(() => {
        const nameInput = document.getElementById('workshopName');
        if (nameInput) nameInput.focus();
    }, 500);
}

// Edit workshop
async function editWorkshop(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/workshops.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            document.getElementById('modalTitleText').textContent = 'Chỉnh sửa xưởng';
            document.getElementById('workshopId').value = data.id;
            document.getElementById('workshopName').value = data.name;
            document.getElementById('workshopCode').value = data.code;
            document.getElementById('workshopIndustry').value = data.industry_id;
            document.getElementById('workshopDescription').value = data.description || '';
            document.getElementById('workshopStatus').value = data.status;
            
            // Update description counter
            const counter = document.getElementById('descriptionCounter');
            if (counter) counter.textContent = (data.description || '').length;
            
            const modal = new bootstrap.Modal(document.getElementById('workshopModal'));
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

// View workshop details
async function viewWorkshop(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/workshops.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            // Create info modal content
            const modalContent = `
                <div class="modal fade" id="viewModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Thông tin xưởng
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-borderless">
                                    <tr><td><strong>Tên xưởng:</strong></td><td>${escapeHtml(data.name)}</td></tr>
                                    <tr><td><strong>Mã xưởng:</strong></td><td><span class="badge bg-info">${escapeHtml(data.code)}</span></td></tr>
                                    <tr><td><strong>Ngành:</strong></td><td><span class="industry-badge">${escapeHtml(data.industry_name)} (${escapeHtml(data.industry_code)})</span></td></tr>
                                    <tr><td><strong>Mô tả:</strong></td><td>${data.description || '<em class="text-muted">Chưa có</em>'}</td></tr>
                                    <tr><td><strong>Trạng thái:</strong></td><td><span class="badge ${data.status === 'active' ? 'bg-success' : 'bg-secondary'}">${data.status === 'active' ? 'Hoạt động' : 'Không hoạt động'}</span></td></tr>
                                    <tr><td><strong>Số line sản xuất:</strong></td><td>${data.lines_count || 0}</td></tr>
                                    <tr><td><strong>Số thiết bị:</strong></td><td>${data.equipment_count || 0}</td></tr>
                                    <tr><td><strong>Ngày tạo:</strong></td><td>${formatDateTime(data.created_at)}</td></tr>
                                    <tr><td><strong>Cập nhật:</strong></td><td>${formatDateTime(data.updated_at)}</td></tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing view modal
            const existingModal = document.getElementById('viewModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalContent);
            
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
            
            // Clean up after modal is hidden
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

// Delete workshop
function deleteWorkshop(id, name) {
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
        
        const response = await fetch('../api/workshops.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadData();
        } else {
            showNotification(result.message || 'Lỗi khi xóa', 'error');
        }
    } catch (error) {
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
        
        const response = await fetch('../api/workshops.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            loadData();
        } else {
            showNotification(result.message || 'Lỗi khi thay đổi trạng thái', 'error');
        }
    } catch (error) {
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Save workshop
async function saveWorkshop() {
    const form = document.getElementById('workshopForm');
    const id = document.getElementById('workshopId').value;
    
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    try {
        showLoading(true);
        
        const formData = new FormData(form);
        formData.append('action', id ? 'update' : 'create');
        
        const response = await fetch('../api/workshops.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('workshopModal')).hide();
            loadData();
        } else {
            showNotification(result.message || 'Lỗi khi lưu dữ liệu', 'error');
        }
    } catch (error) {
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Validate form
function validateForm() {
    const form = document.getElementById('workshopForm');
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'Trường này không được trống');
            isValid = false;
        } else {
            clearFieldError(input);
        }
    });
    
    // Validate code format
    const codeInput = document.getElementById('workshopCode');
    const codePattern = /^[A-Z0-9_]+$/;
    if (codeInput.value && !codePattern.test(codeInput.value)) {
        showFieldError(codeInput, 'Mã xưởng chỉ được chứa chữ hoa, số và dấu gạch dưới');
        isValid = false;
    }
    
    return isValid;
}

// Validate code uniqueness
async function validateCode() {
    const codeInput = document.getElementById('workshopCode');
    const industrySelect = document.getElementById('workshopIndustry');
    const code = codeInput.value.trim();
    const industryId = industrySelect.value;
    const id = document.getElementById('workshopId').value;
    
    if (!code || code.length < 2 || !industryId) {
        return;
    }
    
    try {
        const params = new URLSearchParams({
            action: 'check_code',
            code: code,
            industry_id: industryId,
            exclude_id: id || 0
        });
        
        const response = await fetch(`../api/workshops.php?${params}`);
        const result = await response.json();
        
        if (result.success && result.data.exists) {
            showFieldError(codeInput, 'Mã xưởng đã tồn tại trong ngành này');
        } else {
            clearFieldError(codeInput);
        }
    } catch (error) {
        console.error('Code validation error:', error);
    }
}

// Show field error
function showFieldError(input, message) {
    input.classList.add('is-invalid');
    const feedback = input.parentElement.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = message;
    }
}

// Clear field error
function clearFieldError(input) {
    input.classList.remove('is-invalid');
}

// Reset form
function resetForm() {
    const form = document.getElementById('workshopForm');
    form.reset();
    
    // Clear validation states
    const inputs = form.querySelectorAll('.is-invalid');
    inputs.forEach(input => {
        input.classList.remove('is-invalid');
    });
    
    // Reset description counter
    document.getElementById('descriptionCounter').textContent = '0';
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('industryFilter').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('sortBy').value = 'name';
    document.getElementById('sortOrder').value = 'ASC';
    
    currentPage = 1;
    loadData();
}

// Export data
function exportData() {
    showNotification('Chức năng xuất Excel đang được phát triển', 'info');
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN');
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
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

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'n':
                e.preventDefault();
                <?php if (hasPermission('structure', 'create')): ?>
                showAddModal();
                <?php endif; ?>
                break;
            case 'r':
                e.preventDefault();
                loadData();
                break;
            case 'f':
                e.preventDefault();
                document.getElementById('searchInput').focus();
                break;
        }
    }
    
    // ESC to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            bootstrap.Modal.getInstance(modal)?.hide();
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>