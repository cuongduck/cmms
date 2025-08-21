<?php
/**
 * Areas Management View - /modules/structure/views/areas.php
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

.line-badge {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: white;
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.3rem;
    font-weight: 500;
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
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                        <label for="searchInput">Tìm kiếm tên, mã khu vực...</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="industryFilter">
                            <option value="">Tất cả ngành</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="industryFilter">Ngành</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="workshopFilter">
                            <option value="">Tất cả xưởng</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="workshopFilter">Xưởng</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="lineFilter">
                            <option value="">Tất cả line</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="lineFilter">Line</label>
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
                        <th style="width: 100px;">Mã KV</th>
                        <th style="width: 250px;">Ngành - Xưởng - Line</th>
                        <th>Mô tả</th>
                        <th style="width: 80px;">Dòng máy</th>
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
                                       placeholder="Mã khu vực" required maxlength="10" pattern="[A-Z0-9_]+">
                                <label for="areaCode">Mã khu vực *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chỉ chữ hoa, số và dấu _</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="areaIndustry" required>
                                    <option value="">Chọn ngành...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="areaIndustry">Ngành sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="areaWorkshop" required>
                                    <option value="">Chọn xưởng...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="areaWorkshop">Xưởng sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="areaLine" name="line_id" required>
                                    <option value="">Chọn line...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="areaLine">Line sản xuất *</label>
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
let linesData = [];
let currentFilters = {
    search: '',
    status: 'all',
    industry_id: '',
    workshop_id: '',
    line_id: '',
    sort_by: 'name',
    sort_order: 'ASC'
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Areas page loaded, initializing...');
    initializeEventListeners();
    loadIndustries();
    loadWorkshops();
    loadLines();
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
            
            // Handle cascading filters
            if (this.id === 'industryFilter') {
                updateWorkshopFilter();
                updateLineFilter();
            } else if (this.id === 'workshopFilter') {
                updateLineFilter();
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
            updateFormLineOptions();
            validateCode();
        });
    }

    const workshopSelect = document.getElementById('areaWorkshop');
    if (workshopSelect) {
        workshopSelect.addEventListener('change', function() {
            updateFormLineOptions();
            validateCode();
        });
    }

    const lineSelect = document.getElementById('areaLine');
    if (lineSelect) {
        lineSelect.addEventListener('change', function() {
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

// Load lines for dropdowns
async function loadLines() {
    try {
        const response = await fetch('../api/areas.php?action=get_lines');
        const result = await response.json();
        
        if (result.success && result.data.lines) {
            linesData = result.data.lines;
            populateLineDropdowns();
        }
    } catch (error) {
        console.error('Error loading lines:', error);
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

// Populate line dropdowns
function populateLineDropdowns() {
    // Filter dropdown
    const filterSelect = document.getElementById('lineFilter');
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">Tất cả line</option>';
        linesData.forEach(line => {
            filterSelect.innerHTML += `<option value="${line.id}">${line.industry_name} - ${line.workshop_name} - ${line.name} (${line.code})</option>`;
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

// Update line filter based on selected workshop
function updateLineFilter() {
    const workshopId = document.getElementById('workshopFilter').value;
    const lineFilter = document.getElementById('lineFilter');
    
    if (lineFilter) {
        lineFilter.innerHTML = '<option value="">Tất cả line</option>';
        
        const filteredLines = workshopId ? 
            linesData.filter(l => l.workshop_id == workshopId) : 
            linesData;
            
        filteredLines.forEach(line => {
            lineFilter.innerHTML += `<option value="${line.id}">${line.name} (${line.code})</option>`;
        });
        
        lineFilter.value = '';
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

// Update form line options
function updateFormLineOptions() {
    const workshopId = document.getElementById('areaWorkshop').value;
    const lineSelect = document.getElementById('areaLine');
    
    if (lineSelect) {
        lineSelect.innerHTML = '<option value="">Chọn line...</option>';
        
        if (workshopId) {
            const filteredLines = linesData.filter(l => l.workshop_id == workshopId);
            filteredLines.forEach(line => {
                lineSelect.innerHTML += `<option value="${line.id}">${line.name} (${line.code})</option>`;
            });
        }
        
        lineSelect.value = '';
        clearFieldError(lineSelect);
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
        const lineEl = document.getElementById('lineFilter');
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            status: statusEl ? statusEl.value : 'all',
            industry_id: industryEl ? industryEl.value : '',
            workshop_id: workshopEl ? workshopEl.value : '',
            line_id: lineEl ? lineEl.value : '',
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
                    <span class="badge bg-warning">${escapeHtml(item.code)}</span>
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
                            <span class="line-badge" title="${escapeHtml(item.line_name)}">
                                ${escapeHtml(item.line_code)}
                            </span>
                        </div>
                        <div class="breadcrumb-path">
                            <span>${escapeHtml(item.industry_name)}</span>
                            <span class="separator">→</span>
                            <span>${escapeHtml(item.workshop_name)}</span>
                            <span class="separator">→</span>
                            <span>${escapeHtml(item.line_name)}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(item.description || '')}">
                        ${item.description || '<em class="text-muted">Chưa có mô tả</em>'}
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-danger">${item.machine_types_count || 0}</span>
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
    document.getElementById('modalTitleText').textContent = 'Thêm khu vực mới';
    document.getElementById('areaId').value = '';
    resetForm();
    
    const modal = new bootstrap.Modal(document.getElementById('areaModal'));
    modal.show();
    
    // Focus on name input
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
                
                // Update line options and set selected line
                updateFormLineOptions();
                setTimeout(() => {
                    document.getElementById('areaLine').value = data.line_id;
                }, 100);
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
            
            // Create info modal content
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
                                            <tr><td><strong>Mã khu vực:</strong></td><td><span class="badge bg-warning">${escapeHtml(data.code)}</span></td></tr>
                                            <tr><td><strong>Mô tả:</strong></td><td>${data.description || '<em class="text-muted">Chưa có</em>'}</td></tr>
                                            <tr><td><strong>Trạng thái:</strong></td><td><span class="badge ${data.status === 'active' ? 'bg-success' : 'bg-secondary'}">${data.status === 'active' ? 'Hoạt động' : 'Không hoạt động'}</span></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Ngành:</strong></td><td><span class="industry-badge">${escapeHtml(data.industry_name)} (${escapeHtml(data.industry_code)})</span></td></tr>
                                            <tr><td><strong>Xưởng:</strong></td><td><span class="workshop-badge">${escapeHtml(data.workshop_name)} (${escapeHtml(data.workshop_code)})</span></td></tr>
                                            <tr><td><strong>Line:</strong></td><td><span class="line-badge">${escapeHtml(data.line_name)} (${escapeHtml(data.line_code)})</span></td></tr>
                                        </table>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Số dòng máy:</strong></td><td><span class="badge bg-danger">${data.machine_types_count || 0}</span></td></tr>
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
        
        const response = await fetch('../api/areas.php', {
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

// Save area
async function saveArea() {
    const form = document.getElementById('areaForm');
    const id = document.getElementById('areaId').value;
    
    // Validate form
    if (!validateForm()) {
        return;
    }
    
    try {
        showLoading(true);
        
        const formData = new FormData(form);
        formData.append('action', id ? 'update' : 'create');
        
        const response = await fetch('../api/areas.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('areaModal')).hide();
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
    const form = document.getElementById('areaForm');
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
    const codeInput = document.getElementById('areaCode');
    const codePattern = /^[A-Z0-9_]+$/;
    if (codeInput.value && !codePattern.test(codeInput.value)) {
        showFieldError(codeInput, 'Mã khu vực chỉ được chứa chữ hoa, số và dấu gạch dưới');
        isValid = false;
    }
    
    // Validate hierarchy selection
    const industrySelect = document.getElementById('areaIndustry');
    const workshopSelect = document.getElementById('areaWorkshop');
    const lineSelect = document.getElementById('areaLine');
    
    if (industrySelect.value && !workshopSelect.value) {
        showFieldError(workshopSelect, 'Vui lòng chọn xưởng');
        isValid = false;
    }
    
    if (workshopSelect.value && !lineSelect.value) {
        showFieldError(lineSelect, 'Vui lòng chọn line sản xuất');
        isValid = false;
    }
    
    return isValid;
}

// Validate code uniqueness
async function validateCode() {
    const codeInput = document.getElementById('areaCode');
    const lineSelect = document.getElementById('areaLine');
    const code = codeInput.value.trim();
    const lineId = lineSelect.value;
    const id = document.getElementById('areaId').value;
    
    if (!code || code.length < 2 || !lineId) {
        return;
    }
    
    try {
        const params = new URLSearchParams({
            action: 'check_code',
            code: code,
            line_id: lineId,
            exclude_id: id || 0
        });
        
        const response = await fetch(`../api/areas.php?${params}`);
        const result = await response.json();
        
        if (result.success && result.data.exists) {
            showFieldError(codeInput, 'Mã khu vực đã tồn tại trong line này');
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
    const form = document.getElementById('areaForm');
    form.reset();
    
    // Clear validation states
    const inputs = form.querySelectorAll('.is-invalid');
    inputs.forEach(input => {
        input.classList.remove('is-invalid');
    });
    
    // Reset description counter
    document.getElementById('descriptionCounter').textContent = '0';
    
    // Reset dropdown options
    document.getElementById('areaWorkshop').innerHTML = '<option value="">Chọn xưởng...</option>';
    document.getElementById('areaLine').innerHTML = '<option value="">Chọn line...</option>';
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('industryFilter').value = '';
    document.getElementById('workshopFilter').value = '';
    document.getElementById('lineFilter').value = '';
    document.getElementById('statusFilter').value = 'all';
    
    // Reset cascading filters
    updateWorkshopFilter();
    updateLineFilter();
    
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

// Auto-save filters to localStorage
function saveFiltersToStorage() {
    const filters = {
        search: document.getElementById('searchInput').value,
        industry_id: document.getElementById('industryFilter').value,
        workshop_id: document.getElementById('workshopFilter').value,
        line_id: document.getElementById('lineFilter').value,
        status: document.getElementById('statusFilter').value
    };
    localStorage.setItem('cmms_areas_filters', JSON.stringify(filters));
}

function loadFiltersFromStorage() {
    const saved = localStorage.getItem('cmms_areas_filters');
    if (saved) {
        try {
            const filters = JSON.parse(saved);
            document.getElementById('searchInput').value = filters.search || '';
            document.getElementById('industryFilter').value = filters.industry_id || '';
            document.getElementById('workshopFilter').value = filters.workshop_id || '';
            document.getElementById('lineFilter').value = filters.line_id || '';
            document.getElementById('statusFilter').value = filters.status || 'all';
            
            // Update cascading filters based on saved values
            if (filters.industry_id) {
                setTimeout(() => {
                    updateWorkshopFilter();
                    document.getElementById('workshopFilter').value = filters.workshop_id || '';
                    
                    if (filters.workshop_id) {
                        setTimeout(() => {
                            updateLineFilter();
                            document.getElementById('lineFilter').value = filters.line_id || '';
                        }, 100);
                    }
                }, 100);
            }
        } catch (e) {
            console.error('Error loading filters:', e);
        }
    }
}

// Load saved filters on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        loadFiltersFromStorage();
    }, 1000); // Wait for dropdowns to populate
    
    // Save filters when they change
    const filterInputs = document.querySelectorAll('#filterForm input, #filterForm select');
    filterInputs.forEach(input => {
        input.addEventListener('change', saveFiltersToStorage);
    });
});

// Add tooltips and enhanced UX
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Show keyboard shortcuts hint
    setTimeout(() => {
        const hint = document.createElement('div');
        hint.className = 'position-fixed bg-dark text-white p-2 rounded';
        hint.style.cssText = 'bottom: 20px; left: 20px; font-size: 0.75rem; z-index: 1000; opacity: 0.8;';
        hint.innerHTML = '💡 Phím tắt: Ctrl+N (thêm mới), Ctrl+R (làm mới), Ctrl+F (tìm kiếm)';
        document.body.appendChild(hint);
        
        setTimeout(() => {
            if (hint.parentElement) {
                hint.style.opacity = '0';
                setTimeout(() => {
                    if (hint.parentElement) {
                        hint.parentElement.removeChild(hint);
                    }
                }, 300);
            }
        }, 8000);
    }, 2000);
});
</script>

<?php require_once '../../../includes/footer.php'; ?>