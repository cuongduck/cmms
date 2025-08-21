<?php
/**
 * Machine Types Management View - /modules/structure/views/machine_types.php
 */

$pageTitle = 'Quản lý dòng máy';
$currentModule = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị', 'url' => '/modules/structure/'],
    ['title' => 'Quản lý dòng máy']
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
            <i class="fas fa-plus me-1"></i> Thêm dòng máy
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
            </ul>
        </div>
    </div>';
}

require_once '../../../includes/header.php';
?>

<style>
.machine-types-container {
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
    gap: 0.4rem;
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

.area-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
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
    font-size: 0.7rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-wrap: wrap;
}

.breadcrumb-path .separator {
    color: #9ca3af;
    font-size: 0.6rem;
}

.machine-type-code {
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
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

<div class="machine-types-container">
    <!-- Filters -->
    <div class="filter-card">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                        <label for="searchInput">Tìm kiếm tên, mã dòng máy...</label>
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
                <div class="col-md-1">
                    <div class="form-floating">
                        <select class="form-select" id="lineFilter">
                            <option value="">Line</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="lineFilter">Line</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <select class="form-select" id="areaFilter">
                            <option value="">Khu vực</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <label for="areaFilter">KV</label>
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
                        <th>Tên dòng máy</th>
                        <th style="width: 120px;">Mã dòng máy</th>
                        <th style="width: 280px;">Cấu trúc</th>
                        <th>Mô tả</th>
                        <th style="width: 80px;">Cụm TB</th>
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
            <i class="fas fa-cogs"></i>
            <h5>Chưa có dòng máy</h5>
            <p>Bắt đầu bằng cách thêm dòng máy đầu tiên</p>
            <?php if (hasPermission('structure', 'create')): ?>
            <button type="button" class="btn btn-primary" onclick="showAddModal()">
                <i class="fas fa-plus me-1"></i> Thêm dòng máy
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
<div class="modal fade" id="machineTypeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-cogs me-2"></i>
                    <span id="modalTitleText">Thêm dòng máy mới</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="machineTypeForm" novalidate>
                    <input type="hidden" id="machineTypeId" name="id">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="machineTypeName" name="name" 
                                       placeholder="Tên dòng máy" required maxlength="255">
                                <label for="machineTypeName">Tên dòng máy *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control text-uppercase" id="machineTypeCode" name="code" 
                                       placeholder="Mã dòng máy" required maxlength="20" pattern="[A-Z0-9_]+">
                                <label for="machineTypeCode">Mã dòng máy *</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Chỉ chữ hoa, số và dấu _</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeIndustry" required>
                                    <option value="">Chọn ngành...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="machineTypeIndustry">Ngành sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeWorkshop" name="workshop_id" required>
                                    <option value="">Chọn xưởng...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="machineTypeWorkshop">Xưởng sản xuất *</label>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeLine" name="line_id">
                                    <option value="">Chọn line...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="machineTypeLine">Line sản xuất</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Tùy chọn - áp dụng cho ngành Mì/Phở</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeArea" name="area_id">
                                    <option value="">Chọn khu vực...</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                                <label for="machineTypeArea">Khu vực</label>
                                <div class="invalid-feedback"></div>
                                <div class="form-text">Tùy chọn - áp dụng cho ngành Mì/Phở</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-select" id="machineTypeStatus" name="status" required>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                                <label for="machineTypeStatus">Trạng thái *</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" id="machineTypeDescription" name="description" 
                                          placeholder="Mô tả" style="height: 100px;" maxlength="1000"></textarea>
                                <label for="machineTypeDescription">Mô tả</label>
                                <div class="form-text">
                                    <span id="descriptionCounter">0</span>/1000 ký tự
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Industry-specific note -->
                    <div class="alert alert-info mt-3" id="industryNote" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Lưu ý:</strong> 
                        <span id="industryNoteText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Hủy
                </button>
                <button type="button" class="btn btn-primary" onclick="saveMachineType()">
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
                <p>Bạn có chắc chắn muốn xóa dòng máy <strong id="deleteItemName"></strong>?</p>
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
let areasData = [];
let currentFilters = {
    search: '',
    status: 'all',
    industry_id: '',
    workshop_id: '',
    line_id: '',
    area_id: '',
    sort_by: 'name',
    sort_order: 'ASC'
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Machine Types page loaded, initializing...');
    initializeEventListeners();
    loadIndustries();
    loadWorkshops();
    loadLines();
    loadAreas();
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
                updateAreaFilter();
            } else if (this.id === 'workshopFilter') {
                updateLineFilter();
                updateAreaFilter();
            } else if (this.id === 'lineFilter') {
                updateAreaFilter();
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
    const form = document.getElementById('machineTypeForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveMachineType();
        });
    }

    // Code input formatting
    const codeInput = document.getElementById('machineTypeCode');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
            validateCode();
        });
    }

    // Form cascading dropdowns
    const industrySelect = document.getElementById('machineTypeIndustry');
    if (industrySelect) {
        industrySelect.addEventListener('change', function() {
            updateFormWorkshopOptions();
            updateFormLineOptions();
            updateFormAreaOptions();
            updateIndustryNote();
            validateCode();
        });
    }

    const workshopSelect = document.getElementById('machineTypeWorkshop');
    if (workshopSelect) {
        workshopSelect.addEventListener('change', function() {
            updateFormLineOptions();
            updateFormAreaOptions();
            validateCode();
        });
    }

    const lineSelect = document.getElementById('machineTypeLine');
    if (lineSelect) {
        lineSelect.addEventListener('change', function() {
            updateFormAreaOptions();
            validateCode();
        });
    }

    const areaSelect = document.getElementById('machineTypeArea');
    if (areaSelect) {
        areaSelect.addEventListener('change', function() {
            validateCode();
        });
    }

    // Description counter
    const descInput = document.getElementById('machineTypeDescription');
    const counter = document.getElementById('descriptionCounter');
    if (descInput && counter) {
        descInput.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }

    // Modal events
    const modal = document.getElementById('machineTypeModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            resetForm();
        });
    }
}

// Load industries for dropdowns
async function loadIndustries() {
    try {
        const response = await fetch('../api/machine_types.php?action=get_industries');
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
        const response = await fetch('../api/machine_types.php?action=get_workshops');
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
        const response = await fetch('../api/machine_types.php?action=get_lines');
        const result = await response.json();
        
        if (result.success && result.data.lines) {
            linesData = result.data.lines;
            populateLineDropdowns();
        }
    } catch (error) {
        console.error('Error loading lines:', error);
    }
}

// Load areas for dropdowns
async function loadAreas() {
    try {
        const response = await fetch('../api/machine_types.php?action=get_areas');
        const result = await response.json();
        
        if (result.success && result.data.areas) {
            areasData = result.data.areas;
            populateAreaDropdowns();
        }
    } catch (error) {
        console.error('Error loading areas:', error);
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
    const formSelect = document.getElementById('machineTypeIndustry');
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Chọn ngành...</option>';
        industriesData.forEach(industry => {
            formSelect.innerHTML += `<option value="${industry.id}" data-code="${industry.code}">${industry.name} (${industry.code})</option>`;
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
        filterSelect.innerHTML = '<option value="">Line</option>';
        linesData.forEach(line => {
            filterSelect.innerHTML += `<option value="${line.id}">${line.name} (${line.code})</option>`;
        });
    }
}

// Populate area dropdowns
function populateAreaDropdowns() {
    // Filter dropdown
    const filterSelect = document.getElementById('areaFilter');
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">Khu vực</option>';
        areasData.forEach(area => {
            filterSelect.innerHTML += `<option value="${area.id}">${area.name} (${area.code})</option>`;
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
        lineFilter.innerHTML = '<option value="">Line</option>';
        
        const filteredLines = workshopId ? 
            linesData.filter(l => l.workshop_id == workshopId) : 
            linesData;
            
        filteredLines.forEach(line => {
            lineFilter.innerHTML += `<option value="${line.id}">${line.name} (${line.code})</option>`;
        });
        
        lineFilter.value = '';
    }
}

// Update area filter based on selected line
function updateAreaFilter() {
    const lineId = document.getElementById('lineFilter').value;
    const areaFilter = document.getElementById('areaFilter');
    
    if (areaFilter) {
        areaFilter.innerHTML = '<option value="">Khu vực</option>';
        
        const filteredAreas = lineId ? 
            areasData.filter(a => a.line_id == lineId) : 
            areasData;
            
        filteredAreas.forEach(area => {
            areaFilter.innerHTML += `<option value="${area.id}">${area.name} (${area.code})</option>`;
        });
        
        areaFilter.value = '';
    }
}

// Update form workshop options
function updateFormWorkshopOptions() {
    const industryId = document.getElementById('machineTypeIndustry').value;
    const workshopSelect = document.getElementById('machineTypeWorkshop');
    
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
    const workshopId = document.getElementById('machineTypeWorkshop').value;
    const lineSelect = document.getElementById('machineTypeLine');
    
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

// Update form area options
function updateFormAreaOptions() {
    const lineId = document.getElementById('machineTypeLine').value;
    const areaSelect = document.getElementById('machineTypeArea');
    
    if (areaSelect) {
        areaSelect.innerHTML = '<option value="">Chọn khu vực...</option>';
        
        if (lineId) {
            const filteredAreas = areasData.filter(a => a.line_id == lineId);
            filteredAreas.forEach(area => {
                areaSelect.innerHTML += `<option value="${area.id}">${area.name} (${area.code})</option>`;
            });
        }
        
        areaSelect.value = '';
        clearFieldError(areaSelect);
    }
}

// Update industry note
function updateIndustryNote() {
    const industrySelect = document.getElementById('machineTypeIndustry');
    const noteDiv = document.getElementById('industryNote');
    const noteText = document.getElementById('industryNoteText');
    
    if (!industrySelect || !noteDiv || !noteText) return;
    
    const selectedOption = industrySelect.options[industrySelect.selectedIndex];
    const industryCode = selectedOption ? selectedOption.dataset.code : '';
    
    if (industryCode === 'NEM') {
        noteText.textContent = 'Đối với ngành Nêm rau, Line và Khu vực có thể để trống.';
        noteDiv.style.display = 'block';
    } else if (industryCode === 'MI' || industryCode === 'PHO') {
        noteText.textContent = 'Đối với ngành Mì/Phở, khuyến khích nhập đầy đủ Line và Khu vực để quản lý tốt hơn.';
        noteDiv.style.display = 'block';
    } else {
        noteDiv.style.display = 'none';
    }
}

// Load data from API
async function loadData() {
    console.log('Loading machine types data...');
    try {
        showLoading(true);
        
        // Get filter values
        const searchEl = document.getElementById('searchInput');
        const statusEl = document.getElementById('statusFilter');
        const industryEl = document.getElementById('industryFilter');
        const workshopEl = document.getElementById('workshopFilter');
        const lineEl = document.getElementById('lineFilter');
        const areaEl = document.getElementById('areaFilter');
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            status: statusEl ? statusEl.value : 'all',
            industry_id: industryEl ? industryEl.value : '',
            workshop_id: workshopEl ? workshopEl.value : '',
            line_id: lineEl ? lineEl.value : '',
            area_id: areaEl ? areaEl.value : '',
            sort_by: 'name',
            sort_order: 'ASC'
        };

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        const apiUrl = `../api/machine_types.php?${params}`;
        console.log('Fetching:', apiUrl);

        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data) {
            currentData = result.data.machine_types || [];
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
        // Build hierarchy path
        let hierarchyPath = `${item.industry_name} → ${item.workshop_name}`;
        if (item.line_name && item.area_name) {
            hierarchyPath += ` → ${item.line_name} → ${item.area_name}`;
        } else if (item.line_name) {
            hierarchyPath += ` → ${item.line_name}`;
        }
        
        // Build hierarchy badges
        let hierarchyBadges = `
            <span class="industry-badge" title="${escapeHtml(item.industry_name)}">
                ${escapeHtml(item.industry_code)}
            </span>
            <span class="workshop-badge" title="${escapeHtml(item.workshop_name)}">
                ${escapeHtml(item.workshop_code)}
            </span>`;
        
        if (item.line_name) {
            hierarchyBadges += `
                <span class="line-badge" title="${escapeHtml(item.line_name)}">
                    ${escapeHtml(item.line_code)}
                </span>`;
        }
        
        if (item.area_name) {
            hierarchyBadges += `
                <span class="area-badge" title="${escapeHtml(item.area_name)}">
                    ${escapeHtml(item.area_code)}
                </span>`;
        }
        
        return `
            <tr>
                <td class="text-muted">${startIndex + index + 1}</td>
                <td>
                    <div class="fw-semibold">${escapeHtml(item.name)}</div>
                </td>
                <td>
                    <span class="machine-type-code">${escapeHtml(item.code)}</span>
                </td>
                <td>
                    <div class="hierarchy-display">
                        <div class="hierarchy-badges">
                            ${hierarchyBadges}
                        </div>
                        <div class="breadcrumb-path">
                            ${escapeHtml(hierarchyPath)}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(item.description || '')}">
                        ${item.description || '<em class="text-muted">Chưa có mô tả</em>'}
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-info">${item.equipment_groups_count || 0}</span>
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
                                onclick="viewMachineType(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canEdit ? `
                        <button type="button" class="btn btn-outline-primary btn-action" 
                                onclick="editMachineType(${item.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button type="button" class="btn btn-outline-danger btn-action" 
                                onclick="deleteMachineType(${item.id}, '${escapeHtml(item.name)}')" title="Xóa">
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

// Render pagination (reuse from previous modules)
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
    document.getElementById('modalTitleText').textContent = 'Thêm dòng máy mới';
    document.getElementById('machineTypeId').value = '';
    resetForm();
    
    const modal = new bootstrap.Modal(document.getElementById('machineTypeModal'));
    modal.show();
    
    setTimeout(() => {
        const nameInput = document.getElementById('machineTypeName');
        if (nameInput) nameInput.focus();
    }, 500);
}

// Edit machine type
async function editMachineType(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/machine_types.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            document.getElementById('modalTitleText').textContent = 'Chỉnh sửa dòng máy';
            document.getElementById('machineTypeId').value = data.id;
            document.getElementById('machineTypeName').value = data.name;
            document.getElementById('machineTypeCode').value = data.code;
            document.getElementById('machineTypeIndustry').value = data.industry_id;
            
            // Update cascading dropdowns
            updateFormWorkshopOptions();
            setTimeout(() => {
                document.getElementById('machineTypeWorkshop').value = data.workshop_id;
                
                updateFormLineOptions();
                setTimeout(() => {
                    if (data.line_id) {
                        document.getElementById('machineTypeLine').value = data.line_id;
                        
                        updateFormAreaOptions();
                        setTimeout(() => {
                            if (data.area_id) {
                                document.getElementById('machineTypeArea').value = data.area_id;
                            }
                        }, 100);
                    }
                }, 100);
            }, 100);
            
            document.getElementById('machineTypeDescription').value = data.description || '';
            document.getElementById('machineTypeStatus').value = data.status;
            
            // Update industry note and description counter
            updateIndustryNote();
            const counter = document.getElementById('descriptionCounter');
            if (counter) counter.textContent = (data.description || '').length;
            
            const modal = new bootstrap.Modal(document.getElementById('machineTypeModal'));
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

// View machine type details
async function viewMachineType(id) {
    try {
        showLoading(true);
        
        const response = await fetch(`../api/machine_types.php?action=get&id=${id}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const data = result.data;
            
            // Build hierarchy display
            let hierarchyDisplay = `
                <span class="industry-badge">${escapeHtml(data.industry_name)} (${escapeHtml(data.industry_code)})</span>
                <span class="workshop-badge">${escapeHtml(data.workshop_name)} (${escapeHtml(data.workshop_code)})</span>`;
            
            if (data.line_name) {
                hierarchyDisplay += ` <span class="line-badge">${escapeHtml(data.line_name)} (${escapeHtml(data.line_code)})</span>`;
            }
            
            if (data.area_name) {
                hierarchyDisplay += ` <span class="area-badge">${escapeHtml(data.area_name)} (${escapeHtml(data.area_code)})</span>`;
            }
            
            const modalContent = `
                <div class="modal fade" id="viewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Thông tin dòng máy
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Tên dòng máy:</strong></td><td>${escapeHtml(data.name)}</td></tr>
                                            <tr><td><strong>Mã dòng máy:</strong></td><td><span class="machine-type-code">${escapeHtml(data.code)}</span></td></tr>
                                            <tr><td><strong>Mô tả:</strong></td><td>${data.description || '<em class="text-muted">Chưa có</em>'}</td></tr>
                                            <tr><td><strong>Trạng thái:</strong></td><td><span class="badge ${data.status === 'active' ? 'bg-success' : 'bg-secondary'}">${data.status === 'active' ? 'Hoạt động' : 'Không hoạt động'}</span></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr><td><strong>Cấu trúc:</strong></td><td>${hierarchyDisplay}</td></tr>
                                            <tr><td><strong>Số cụm thiết bị:</strong></td><td><span class="badge bg-info">${data.equipment_groups_count || 0}</span></td></tr>
                                            <tr><td><strong>Số thiết bị:</strong></td><td><span class="badge bg-secondary">${data.equipment_count || 0}</span></td></tr>
                                        </table>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
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
                                ${canEdit ? `<button type="button" class="btn btn-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide(); setTimeout(() => editMachineType(${data.id}), 300);">Chỉnh sửa</button>` : ''}
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

// Delete machine type
function deleteMachineType(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Tiếp tục từ phần confirmDelete() - đã sửa lỗi bootstrap Modal
async function confirmDelete() {
    const id = document.getElementById('deleteItemId').value;
    
    try {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch('../api/machine_types.php', {
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
            showNotification(result.message || 'Lỗi khi xóa dòng máy', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        showLoading(false);
    }
}

// Save machine type (create/update) - đã sửa lỗi form data
async function saveMachineType() {
    if (!validateForm()) {
        return;
    }
    
    try {
        showLoading(true);
        
        const form = document.getElementById('machineTypeForm');
        const formData = new FormData();
        
        const id = document.getElementById('machineTypeId').value;
        formData.append('action', id ? 'update' : 'create');
        
        // Thêm ID nếu đang update
        if (id) {
            formData.append('id', id);
        }
        
        // Get values manually to ensure they're included correctly
        formData.append('name', document.getElementById('machineTypeName').value.trim());
        formData.append('code', document.getElementById('machineTypeCode').value.trim().toUpperCase());
        formData.append('industry_id', document.getElementById('machineTypeIndustry').value);
        formData.append('workshop_id', document.getElementById('machineTypeWorkshop').value);
        formData.append('line_id', document.getElementById('machineTypeLine').value || '');
        formData.append('area_id', document.getElementById('machineTypeArea').value || '');
        formData.append('description', document.getElementById('machineTypeDescription').value.trim());
        formData.append('status', document.getElementById('machineTypeStatus').value);
        
        const response = await fetch('../api/machine_types.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('machineTypeModal'));
            if (modal) modal.hide();
            loadData(); // Reload data
        } else {
            if (result.errors) {
                showFormErrors(result.errors);
            } else {
                showNotification(result.message || 'Lỗi khi lưu dòng máy', 'error');
            }
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
        
        const response = await fetch('../api/machine_types.php', {
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
    clearAllErrors();
    let isValid = true;
    
    // Required fields
    const requiredFields = [
        { id: 'machineTypeName', message: 'Vui lòng nhập tên dòng máy' },
        { id: 'machineTypeCode', message: 'Vui lòng nhập mã dòng máy' },
        { id: 'machineTypeIndustry', message: 'Vui lòng chọn ngành' },
        { id: 'machineTypeWorkshop', message: 'Vui lòng chọn xưởng' }
    ];
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (!element || !element.value.trim()) {
            showFieldError(element, field.message);
            isValid = false;
        }
    });
    
    // Code validation
    const codeElement = document.getElementById('machineTypeCode');
    if (codeElement && codeElement.value) {
        const code = codeElement.value.trim().toUpperCase();
        if (!/^[A-Z0-9_]+$/.test(code)) {
            showFieldError(codeElement, 'Mã chỉ được chứa chữ hoa, số và dấu gạch dưới');
            isValid = false;
        } else if (code.length < 2 || code.length > 20) {
            showFieldError(codeElement, 'Mã phải từ 2-20 ký tự');
            isValid = false;
        }
    }
    
    // Name validation
    const nameElement = document.getElementById('machineTypeName');
    if (nameElement && nameElement.value) {
        const name = nameElement.value.trim();
        if (name.length < 2 || name.length > 255) {
            showFieldError(nameElement, 'Tên phải từ 2-255 ký tự');
            isValid = false;
        }
    }
    
    // Description validation
    const descElement = document.getElementById('machineTypeDescription');
    if (descElement && descElement.value && descElement.value.length > 1000) {
        showFieldError(descElement, 'Mô tả không được vượt quá 1000 ký tự');
        isValid = false;
    }
    
    return isValid;
}

// Validate code in real-time
function validateCode() {
    const codeElement = document.getElementById('machineTypeCode');
    const industryElement = document.getElementById('machineTypeIndustry');
    const workshopElement = document.getElementById('machineTypeWorkshop');
    
    if (!codeElement || !industryElement || !workshopElement) return;
    
    const code = codeElement.value.trim().toUpperCase();
    const industryId = industryElement.value;
    const workshopId = workshopElement.value;
    
    // Clear previous errors
    clearFieldError(codeElement);
    
    if (!code) return;
    
    // Basic format validation
    if (!/^[A-Z0-9_]+$/.test(code)) {
        showFieldError(codeElement, 'Mã chỉ được chứa chữ hoa, số và dấu gạch dưới');
        return;
    }
    
    // Generate suggested code based on hierarchy
    if (industryId && workshopId) {
        const industry = industriesData.find(i => i.id == industryId);
        const workshop = workshopsData.find(w => w.id == workshopId);
        
        if (industry && workshop) {
            const suggestedPrefix = `${industry.code}_${workshop.code}_`;
            
            if (!code.startsWith(suggestedPrefix)) {
                const helpText = codeElement.parentElement.querySelector('.form-text');
                if (helpText) {
                    helpText.innerHTML = `Đề xuất: ${suggestedPrefix}XXX (ví dụ: ${suggestedPrefix}MIXER)`;
                    helpText.style.color = '#0d6efd';
                }
            }
        }
    }
}

// Show field error
function showFieldError(element, message) {
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
    const form = document.getElementById('machineTypeForm');
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

// Show form errors from API response
function showFormErrors(errors) {
    Object.keys(errors).forEach(field => {
        let element;
        
        switch (field) {
            case 'name':
                element = document.getElementById('machineTypeName');
                break;
            case 'code':
                element = document.getElementById('machineTypeCode');
                break;
            case 'industry_id':
                element = document.getElementById('machineTypeIndustry');
                break;
            case 'workshop_id':
                element = document.getElementById('machineTypeWorkshop');
                break;
            case 'line_id':
                element = document.getElementById('machineTypeLine');
                break;
            case 'area_id':
                element = document.getElementById('machineTypeArea');
                break;
            case 'description':
                element = document.getElementById('machineTypeDescription');
                break;
        }
        
        if (element) {
            showFieldError(element, errors[field]);
        }
    });
}

// Reset form
function resetForm() {
    const form = document.getElementById('machineTypeForm');
    if (form) {
        form.reset();
        clearAllErrors();
        
        // Reset description counter
        const counter = document.getElementById('descriptionCounter');
        if (counter) counter.textContent = '0';
        
        // Hide industry note
        const noteDiv = document.getElementById('industryNote');
        if (noteDiv) noteDiv.style.display = 'none';
        
        // Reset help text
        const helpText = document.getElementById('machineTypeCode')?.parentElement.querySelector('.form-text');
        if (helpText) {
            helpText.innerHTML = 'Chỉ chữ hoa, số và dấu _';
            helpText.style.color = '';
        }
    }
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('industryFilter').value = '';
    document.getElementById('workshopFilter').value = '';
    document.getElementById('lineFilter').value = '';
    document.getElementById('areaFilter').value = '';
    
    // Reset workshop options to show all
    populateWorkshopDropdowns();
    populateLineDropdowns();
    populateAreaDropdowns();
    
    currentPage = 1;
    loadData();
}

// Export data
async function exportData() {
    try {
        showLoading(true);
        
        // Get current filters
        const params = new URLSearchParams({
            action: 'export',
            ...currentFilters
        });
        
        const response = await fetch(`../api/machine_types.php?${params}`);
        
        if (!response.ok) {
            throw new Error('Lỗi khi xuất dữ liệu');
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `machine_types_${new Date().toISOString().split('T')[0]}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showNotification('Xuất dữ liệu thành công', 'success');
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

// Show notification function - đã sửa lại để tương thích với file areas.php
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
    
    // Map type to Bootstrap alert class
    const alertType = type === 'error' ? 'danger' : type;
    notification.className = `alert alert-${alertType} alert-dismissible fade show position-fixed`;
    
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

// Global permission check (from PHP) - đã sửa cú pháp PHP
const canEdit = <?= json_encode(hasPermission('structure', 'edit')); ?>;
const canDelete = <?= json_encode(hasPermission('structure', 'delete')); ?>;
<?php require_once '../../../includes/footer.php'; ?>