<?php
/**
 * Areas Management View - /modules/structure/views/areas.php
 * Simplified version (Industry + Workshop only, no Line dependency)
 */

$pageTitle = 'Quản lý khu vực';
$currentModule = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị', 'url' => '/modules/structure/'],
    ['title' => 'Quản lý khu vực']
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
            <i class="fas fa-plus me-1"></i> Thêm khu vực
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
                <li><a class="dropdown-item" href="machine_types.php"><i class="fas fa-cogs me-2"></i>Dòng máy</a></li>
            </ul>
        </div>
    </div>';
}

require_once '../../../includes/header.php';
?>

<style>
.areas-container {
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

.hierarchy-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.industry-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
    font-weight: 500;
}

.workshop-badge {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
    font-weight: 500;
}

.area-code {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
    display: inline-block;
}

.hierarchy-display {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.breadcrumb-path {
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.breadcrumb-path .separator {
    color: #9ca3af;
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
    
    .hierarchy-badges {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .hierarchy-display {
        gap: 0.2rem;
    }
}

@media (max-width: 576px) {
    .breadcrumb-path {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.1rem;
    }
    
    .breadcrumb-path .separator {
        display: none;
    }
}
</style>

<div class="areas-container">
    <!-- Filters -->
    <div class="filter-card">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                        <label for="searchInput">Tìm kiếm tên, mã khu vực...</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="industryFilter">
                            <option value="">Tất cả ngành</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="industryFilter">Ngành</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="workshopFilter">
                            <option value="">Tất cả xưởng</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="workshopFilter">Xưởng</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <select class="form-select" id="statusFilter">
                            <option value="all">Tất cả</option>
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                        <label for="statusFilter">Trạng thái</label>
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
                        <th>Tên khu vực</th>
                        <th style="width: 120px;">Mã khu vực</th>
                        <th style="width: 200px;">Ngành - Xưởng</th>
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
            <i class="fas fa-map-marked-alt"></i>
            <h5>Chưa có khu vực</h5>
            <p>Bắt đầu bằng cách thêm khu vực đầu tiên</p>
            <?php if (hasPermission('structure', 'create')): ?>
            <button type="button" class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus me-1"></i> Thêm khu vực
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
<div class="modal fade" id="areaModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    <span id="modalTitleText">Thêm khu vực mới</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="areaForm" novalidate>
                    <input type="hidden" id="areaId" name="id">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="areaName" name="name" 
                                       placeholder="Tên khu vực" required maxlength="255">
                                <label for="areaName">Tên khu vực *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control text-uppercase" id="areaCode" name="code" 
                                       placeholder="Mã khu vực" required maxlength="20" pattern="[A-Z0-9_]+">
                                <label for="areaCode">Mã khu vực *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chỉ chữ hoa, số và dấu _ (VD: DG_F2)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="areaIndustry" name="industry_id" required>
                                    <option value="">Chọn ngành...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="areaIndustry">Ngành sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="areaWorkshop" name="workshop_id" required>
                                    <option value="">Chọn xưởng...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="areaWorkshop">Xưởng sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="areaStatus" name="status" required>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                                <label for="areaStatus">Trạng thái *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0 py-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Khu vực thuộc trực tiếp xưởng sản xuất</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" id="areaDescription" name="description" 
                                          placeholder="Mô tả" style="height: 100px;" maxlength="1000"></textarea>
                                <label for="areaDescription">Mô tả</label>
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
                <button type="button" class="btn btn-primary" onclick="saveArea()">
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
                <p>Bạn có chắc chắn muốn xóa khu vực <strong id="deleteItemName"></strong>?</p>
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
let workshopsData = [];
let currentFilters = {
    search: '',
    status: 'all',
    industry_id: '',
    workshop_id: '',
    sort_by: 'name',
    sort_order: 'ASC'
};

// Define permissions from PHP
const canEdit = <?php echo json_encode(hasPermission('structure', 'edit')); ?>;
const canDelete = <?php echo json_encode(hasPermission('structure', 'delete')); ?>;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Areas page loaded, initializing...');
    
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
    loadIndustries();
    loadWorkshops();
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
            
            // Handle cascading filters
            if (this.id === 'industryFilter') {
                updateWorkshopFilter();
            }
            
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
    const form = document.getElementById('areaForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveArea();
        });
    }

    // Code input formatting
    const codeInput = document.getElementById('areaCode');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
            validateCode();
        });
    }

    // Form cascading dropdowns
    const industrySelect = document.getElementById('areaIndustry');
    if (industrySelect) {
        industrySelect.addEventListener('change', function() {
            updateFormWorkshopOptions();
            validateCode();
        });
    }

    const workshopSelect = document.getElementById('areaWorkshop');
    if (workshopSelect) {
        workshopSelect.addEventListener('change', function() {
            validateCode();
        });
    }

    // Description counter
    const descInput = document.getElementById('areaDescription');
    const counter = document.getElementById('descriptionCounter');
    if (descInput && counter) {
        descInput.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }

    // Modal events
    const modal = document.getElementById('areaModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            resetForm();
        });
    }
}

// Load industries for dropdowns
async function loadIndustries() {
    try {
        const response = await fetch('../api/areas.php?action=get_industries');
        const result = await response.json();
        
        if (result.success && result.data.industries) {
            industriesData = result.data.industries;
            populateIndustryDropdowns();
        }
    } catch (error) {
        console.error('Error loading industries:', error);
    }
}

// Load workshops for dropdowns
async function loadWorkshops() {
    try {
        const response = await fetch('../api/areas.php?action=get_workshops');
        const result = await response.json();
        
        if (result.success && result.data.workshops) {
            workshopsData = result.data.workshops;
            populateWorkshopDropdowns();
        }
    } catch (error) {
        console.error('Error loading workshops:', error);
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
    const formSelect = document.getElementById('areaIndustry');
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Chọn ngành...</option>';
        industriesData.forEach(industry => {
            formSelect.innerHTML += `<option value="${industry.id}">${industry.name} (${industry.code})</option>`;
        });
    }
}

// Populate workshop dropdowns
function populateWorkshopDropdowns() {
    // Filter dropdown
    const filterSelect = document.getElementById('workshopFilter');
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">Tất cả xưởng</option>';
        workshopsData.forEach(workshop => {
            filterSelect.innerHTML += `<option value="${workshop.id}">${workshop.industry_name} - ${workshop.name} (${workshop.code})</option>`;
        });
    }
}

// Update workshop filter based on selected industry
function updateWorkshopFilter() {
    const industryId = document.getElementById('industryFilter').value;
    const workshopFilter = document.getElementById('workshopFilter');
    
    if (workshopFilter) {
        workshopFilter.innerHTML = '<option value="">Tất cả xưởng</option>';
        
        const filteredWorkshops = industryId ? 
            workshopsData.filter(w => w.industry_id == industryId) : 
            workshopsData;
            
        filteredWorkshops.forEach(workshop => {
            workshopFilter.innerHTML += `<option value="${workshop.id}">${workshop.name} (${workshop.code})</option>`;
        });
        
        workshopFilter.value = '';
    }
}

// Update form workshop options
function updateFormWorkshopOptions() {
    const industryId = document.getElementById('areaIndustry').value;
    const workshopSelect = document.getElementById('areaWorkshop');
    
    if (workshopSelect) {
        workshopSelect.innerHTML = '<option value="">Chọn xưởng...</option>';
        
        if (industryId) {
            const filteredWorkshops = workshopsData.filter(w => w.industry_id == industryId);
            filteredWorkshops.forEach(workshop => {
                workshopSelect.innerHTML += `<option value="${workshop.id}">${workshop.name} (${workshop.code})</option>`;
            });
        }
        
        workshopSelect.value = '';
        clearFieldError(workshopSelect);
    }
}

// Load data from API
async function loadData() {
    console.log('Loading areas data...');
    try {
        showLoading(true);
        
        // Get filter values
        const searchEl = document.getElementById('searchInput');
        const statusEl = document.getElementById('statusFilter');
        const industryEl = document.getElementById('industryFilter');
        const workshopEl = document.getElementById('workshopFilter');
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            status: statusEl ? statusEl.value : 'all',
            industry_id: industryEl ? industryEl.value : '',
            workshop_id: workshopEl ? workshopEl.value : '',
            sort_by: 'name',
            sort_order: 'ASC'
        };

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        const apiUrl = `../api/areas.php?${params}`;
        console.log('Fetching:', apiUrl);

        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data) {
            currentData = result.data.areas || [];
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
                </td>
                <td>
                    <span class="area-code">${escapeHtml(item.code)}</span>
                </td>
                <td>
                    <div class="hierarchy-display">
                        <div class="hierarchy-badges">
                            <span class="industry-badge" title="${escapeHtml(item.industry_name)}">
                                ${escapeHtml(item.industry_code)}
                            </span>
                            <span class="workshop-badge" title="${escapeHtml(item.workshop_name)}">
                                ${escapeHtml(item.workshop_code)}
                            </span>
                        </div>
                        <div class="breadcrumb-path">
                            <span>${escapeHtml(item.industry_name)}</span>
                            <span class="separator">→</span>
                            <span>${escapeHtml(item.workshop_name)}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(item.description || '')}">
                        ${item.description || '<em class="text-muted">Chưa có mô tả</em>'}
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-info">${item.lines_count || 0}</span>
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
                                onclick="viewArea(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canEdit ? `
                        <button type="button" class="btn btn-outline-primary btn-action" 
                                onclick="editArea(${item.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button type="button" class="btn btn-outline-danger btn-action" 
                                onclick="deleteArea(${item.id}, '${escapeHtml(item.name)}')" title="Xóa">
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
    document.getElementById('modalTitleText').textContent = 'Thêm khu vực mới';
    document.getElementById('areaId').value = '';
    resetForm();
    
    const modal = new bootstrap.Modal(document.getElementById('areaModal'));
    modal.show();
    
    setTimeout(() => {
        const nameInput = document.getElementById('areaName');
        if (nameInput) nameInput.focus();
    }, 500);
}

// Edit area
async function editArea(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/areas.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            document.getElementById('modalTitleText').textContent = 'Chỉnh sửa khu vực';
            document.getElementById('areaId').value = data.id;
            document.getElementById('areaName').value = data.name;
            document.getElementById('areaCode').value = data.code;
            document.getElementById('areaIndustry').value = data.industry_id;
            
            // Update workshop options and set selected workshop
            updateFormWorkshopOptions();
            setTimeout(() => {
                document.getElementById('areaWorkshop').value = data.workshop_id;
            }, 100);
            
            document.getElementById('areaDescription').value = data.description || '';
            document.getElementById('areaStatus').value = data.status;
            
            // Update description counter
            const counter = document.getElementById('descriptionCounter');
            if (counter) counter.textContent = (data.description || '').length;
            
            const modal = new bootstrap.Modal(document.getElementById('areaModal'));
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

// View area details
async function viewArea(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/areas.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            const modalContent = `
                <div class="modal fade" id="viewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Thông tin khu vực
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Tên khu vực:</strong></td><td>${escapeHtml(data.name)}</td></tr>
                                            <tr><td><strong>Mã khu vực:</strong></td><td><span class="area-code">${escapeHtml(data.code)}</span></td></tr>
                                            <tr><td><strong>Trạng thái:</strong></td><td><span class="badge ${data.status === 'active' ? 'bg-success' : 'bg-secondary'}">${data.status === 'active' ? 'Hoạt động' : 'Không hoạt động'}</span></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Ngành:</strong></td><td><span class="industry-badge">${escapeHtml(data.industry_name)} (${escapeHtml(data.industry_code)})</span></td></tr>
                                            <tr><td><strong>Xưởng:</strong></td><td><span class="workshop-badge">${escapeHtml(data.workshop_name)} (${escapeHtml(data.workshop_code)})</span></td></tr>
                                        </table>
                                    </div>
                                </div>
                                ${data.description ? `
                                <hr>
                                <h6><strong>Mô tả:</strong></h6>
                                <p class="text-muted">${escapeHtml(data.description)}</p>
                                ` : ''}
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Số lines:</strong></td><td><span class="badge bg-info">${data.lines_count || 0}</span></td></tr>
                                            <tr><td><strong>Số thiết bị:</strong></td><td><span class="badge bg-secondary">${data.equipment_count || 0}</span></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Ngày tạo:</strong></td><td>${formatDateTime(data.created_at)}</td></tr>
                                            <tr><td><strong>Cập nhật:</strong></td><td>${formatDateTime(data.updated_at)}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                ${canEdit ? `<button type="button" class="btn btn-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); setTimeout(() => editArea(${data.id}), 300);">Chỉnh sửa</button>` : ''}
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

// Delete area
function deleteArea(id, name) {
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
        
        const response = await fetch('../api/areas.php', {
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
            showNotification(result.message || 'Lỗi khi xóa khu vực', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Save area (create/update)
async function saveArea() {
    if (!validateForm()) {
        return;
    }
    
    try {
        showLoading(true);
        
        const formData = new FormData();
        
        const id = document.getElementById('areaId').value;
        formData.append('action', id ? 'update' : 'create');
        
        if (id) {
            formData.append('id', id);
        }
        
        formData.append('name', document.getElementById('areaName').value.trim());
        formData.append('code', document.getElementById('areaCode').value.trim().toUpperCase());
        formData.append('industry_id', document.getElementById('areaIndustry').value);
        formData.append('workshop_id', document.getElementById('areaWorkshop').value);
        formData.append('description', document.getElementById('areaDescription').value.trim());
        formData.append('status', document.getElementById('areaStatus').value);
        
        const response = await fetch('../api/areas.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('areaModal'));
            if (modal) modal.hide();
            loadData(); // Reload data
        } else {
            showNotification(result.message || 'Lỗi khi lưu khu vực', 'error');
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
        
        const response = await fetch('../api/areas.php', {
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
    const name = document.getElementById('areaName').value.trim();
    const code = document.getElementById('areaCode').value.trim();
    const industryId = document.getElementById('areaIndustry').value;
    const workshopId = document.getElementById('areaWorkshop').value;
    
    if (!name) {
        showFieldError('areaName', 'Vui lòng nhập tên khu vực');
        isValid = false;
    } else if (name.length < 2 || name.length > 255) {
        showFieldError('areaName', 'Tên phải từ 2-255 ký tự');
        isValid = false;
    }
    
    if (!code) {
        showFieldError('areaCode', 'Vui lòng nhập mã khu vực');
        isValid = false;
    } else if (!/^[A-Z0-9_]+$/.test(code)) {
        showFieldError('areaCode', 'Mã chỉ được chứa chữ hoa, số và dấu gạch dưới');
        isValid = false;
    } else if (code.length < 2 || code.length > 20) {
        showFieldError('areaCode', 'Mã phải từ 2-20 ký tự');
        isValid = false;
    }
    
    if (!industryId) {
        showFieldError('areaIndustry', 'Vui lòng chọn ngành');
        isValid = false;
    }
    
    if (!workshopId) {
        showFieldError('areaWorkshop', 'Vui lòng chọn xưởng');
        isValid = false;
    }
    
    // Optional field validation
    const description = document.getElementById('areaDescription').value;
    if (description && description.length > 1000) {
        showFieldError('areaDescription', 'Mô tả không được vượt quá 1000 ký tự');
        isValid = false;
    }
    
    return isValid;
}

// Validate code in real-time
async function validateCode() {
    const codeElement = document.getElementById('areaCode');
    const workshopElement = document.getElementById('areaWorkshop');
    
    if (!codeElement || !workshopElement) return;
    
    const code = codeElement.value.trim().toUpperCase();
    const workshopId = workshopElement.value;
    const id = document.getElementById('areaId').value;
    
    // Clear previous errors
    clearFieldError(codeElement);
    
    if (!code || code.length < 2 || !workshopId) {
        return;
    }
    
    // Basic format validation
    if (!/^[A-Z0-9_]+$/.test(code)) {
        showFieldError('areaCode', 'Mã chỉ được chứa chữ hoa, số và dấu gạch dưới');
        return;
    }
    
    // Check uniqueness
    try {
        const params = new URLSearchParams({
            action: 'check_code',
            code: code,
            workshop_id: workshopId,
            exclude_id: id || 0
        });
        
        const response = await fetch(`../api/areas.php?${params}`);
        const result = await response.json();
        
        if (result.success && result.data.exists) {
            showFieldError('areaCode', 'Mã khu vực đã tồn tại trong xưởng này');
        }
    } catch (error) {
        console.error('Code validation error:', error);
    }
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

// Clear field error
function clearFieldError(element) {
    if (!element) return;
    
    element.classList.remove('is-invalid');
    const feedback = element.parentElement.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = '';
    }
}

// Clear all form errors
function clearAllErrors() {
    const form = document.getElementById('areaForm');
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
    const form = document.getElementById('areaForm');
    if (form) {
        form.reset();
        clearAllErrors();
        
        // Reset description counter
        const counter = document.getElementById('descriptionCounter');
        if (counter) counter.textContent = '0';
        
        // Reset workshop dropdown
        const workshopSelect = document.getElementById('areaWorkshop');
        if (workshopSelect) {
            workshopSelect.innerHTML = '<option value="">Chọn xưởng...</option>';
        }
    }
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('industryFilter').value = '';
    document.getElementById('workshopFilter').value = '';
    document.getElementById('statusFilter').value = 'all';
    
    // Reset workshop options to show all
    populateWorkshopDropdowns();
    
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
        
        window.location.href = `../api/areas.php?${params}`;
        
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