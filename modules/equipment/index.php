<?php
/**
 * Equipment Management - Main Page
 * modules/equipment/index.php
 */

$pageTitle = 'Quản lý thiết bị';
$currentModule = 'equipment';
$moduleCSS = 'equipment';
$moduleJS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị']
];

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'view');

$pageActions = '';
if (hasPermission('equipment', 'create')) {
    $pageActions = '
    <div class="btn-group" role="group">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Thêm thiết bị
        </a>
        <button type="button" class="btn btn-outline-success" onclick="exportData()">
            <i class="fas fa-download me-1"></i> Xuất Excel
        </button>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-filter me-1"></i> Bộ lọc nâng cao
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="showFilterModal()">
                    <i class="fas fa-search me-2"></i>Tìm kiếm nâng cao
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="showBulkActions()">
                    <i class="fas fa-tasks me-2"></i>Thao tác hàng loạt
                </a></li>
            </ul>
        </div>
    </div>';
}

require_once '../../includes/header.php';

// Lấy dữ liệu filter options
$industries = $db->fetchAll("SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name");
$workshops = $db->fetchAll("SELECT id, name, code, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
$lines = $db->fetchAll("SELECT id, name, code, workshop_id FROM production_lines WHERE status = 'active' ORDER BY name"); // ✅ THÊM MỚI
$areas = $db->fetchAll("SELECT id, name, code, workshop_id FROM areas WHERE status = 'active' ORDER BY name"); // ✅ THÊM MỚI
$machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
$users = $db->fetchAll("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");

// Thống kê nhanh
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM equipment")['count'],
    'active' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'active'")['count'],
    'maintenance' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'maintenance'")['count'],
    'broken' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'broken'")['count'],
    'inactive' => $db->fetch("SELECT COUNT(*) as count FROM equipment WHERE status = 'inactive'")['count']
];
?>

<style>
.equipment-container {
    background: #f8fafc;
}

.stats-cards {
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    white-space: nowrap;
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

.equipment-code {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
    border-radius: 0.4rem;
    display: inline-block;
}

.equipment-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 0.5rem;
    border: 2px solid #e5e7eb;
}

.no-image {
    width: 50px;
    height: 50px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
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

.equipment-location {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.2;
}

.equipment-specs {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.criticality-critical { background-color: #dc2626; }
.criticality-high { background-color: #ef4444; }
.criticality-medium { background-color: #f59e0b; }
.criticality-low { background-color: #10b981; }

.maintenance-indicator {
    position: relative;
}

.equipment-serial {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Courier New', monospace; /* Font đẹp hơn cho số seri */
    font-size: 0.75rem !important;
}

.maintenance-indicator::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background: #f59e0b;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .table-responsive {
        border-radius: 12px;
    }
    
    .btn-action {
        padding: 0.125rem 0.25rem;
        font-size: 0.75rem;
    }
}
</style>

<div class="equipment-container">
    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="row">
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Tổng thiết bị</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">Hoạt động</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo number_format($stats['maintenance']); ?></div>
                    <div class="stat-label">Bảo trì</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-danger"><?php echo number_format($stats['broken']); ?></div>
                    <div class="stat-label">Hỏng</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo number_format($stats['inactive']); ?></div>
                    <div class="stat-label">Ngưng hoạt động</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <div class="stat-number text-info">
                        <?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="stat-label">Tỷ lệ hoạt động</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <div class="form-floating">
                    <input type="text" class="form-control" id="searchInput" placeholder="Tìm kiếm...">
                    <label for="searchInput">Tìm kiếm thiết bị...</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-floating">
                    <select class="form-select" id="industryFilter">
                        <option value="">Tất cả ngành</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?php echo $industry['id']; ?>">
                                <?php echo htmlspecialchars($industry['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="industryFilter">Ngành</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-floating">
                    <select class="form-select" id="workshopFilter">
                        <option value="">Tất cả xưởng</option>
                        <?php foreach ($workshops as $workshop): ?>
                            <option value="<?php echo $workshop['id']; ?>" data-industry="<?php echo $workshop['industry_id']; ?>">
                                <?php echo htmlspecialchars($workshop['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="workshopFilter">Xưởng</label>
                </div>
            </div>
            <!-- ✅ THAY ĐỔI: Từ "Trạng thái" sang "Khu vực" -->
            <div class="col-md-2">
                <div class="form-floating">
                    <select class="form-select" id="areaFilter">
                        <option value="">Tất cả khu vực</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['id']; ?>" data-workshop="<?php echo $area['workshop_id']; ?>">
                                <?php echo htmlspecialchars($area['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="areaFilter">Khu vực</label>
                </div>
            </div>
            <!-- ✅ THAY ĐỔI: Từ "Mức độ quan trọng" sang "Dòng máy" -->
            <div class="col-md-2">
                <div class="form-floating">
                    <select class="form-select" id="machineTypeFilter">
                        <option value="">Tất cả dòng máy</option>
                        <?php foreach ($machineTypes as $machineType): ?>
                            <option value="<?php echo $machineType['id']; ?>">
                                <?php echo htmlspecialchars($machineType['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="machineTypeFilter">Dòng máy</label>
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
                        <th style="width: 50px;">
                            <input type="checkbox" id="selectAll" class="form-check-input">
                        </th>
                        <th style="width: 80px;">Hình ảnh</th>
                        <th style="width: 120px;">Mã thiết bị</th>
                        <th>Tên thiết bị</th>
                        <th style="width: 200px;">Vị trí máy</th>
                        <th style="width: 120px;">Dòng máy</th>
                        <th style="width: 100px;">Người quản lý</th>
                        <th style="width: 120px;">Level</th>
                        <th style="width: 100px;">Trạng thái</th>
                        <th style="width: 100px;">Bảo trì tiếp theo</th>
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
            <h5>Chưa có thiết bị</h5>
            <p>Bắt đầu bằng cách thêm thiết bị đầu tiên vào hệ thống</p>
            <?php if (hasPermission('equipment', 'create')): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Thêm thiết bị
            </a>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted">
                <span id="paginationInfo">Hiển thị 0 - 0 trong tổng số 0 thiết bị</span>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="pagination">
                    <!-- Pagination will be generated here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>
                    Thao tác hàng loạt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Đã chọn <strong id="selectedCount">0</strong> thiết bị</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-warning" onclick="bulkUpdateStatus('maintenance')">
                        <i class="fas fa-wrench me-2"></i>Chuyển sang trạng thái bảo trì
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="bulkUpdateStatus('active')">
                        <i class="fas fa-play me-2"></i>Kích hoạt thiết bị
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="bulkUpdateStatus('inactive')">
                        <i class="fas fa-pause me-2"></i>Ngưng hoạt động
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="bulkDelete()">
                        <i class="fas fa-trash me-2"></i>Xóa thiết bị
                    </button>
                </div>
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
    industry_id: '',
    workshop_id: '',
    status: '',
    criticality: '',
    sort_by: 'name',
    sort_order: 'ASC'
};
let selectedItems = new Set();

// Define permissions from PHP
const canEdit = <?php echo json_encode(hasPermission('equipment', 'edit')); ?>;
const canDelete = <?php echo json_encode(hasPermission('equipment', 'delete')); ?>;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Equipment page loaded, initializing...');
    
    if (typeof bootstrap === 'undefined') {
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
    // Filter form
    const filterInputs = document.querySelectorAll('#filterForm input, #filterForm select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.id === 'industryFilter') {
                updateWorkshopFilter();
                updateAreaFilter(); // ✅ THÊM MỚI
            } else if (this.id === 'workshopFilter') {
                updateAreaFilter(); // ✅ THÊM MỚI
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
                currentPage = 1;
                loadData();
            }, 500);
        });
    }

    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selectedItems"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                if (this.checked) {
                    selectedItems.add(parseInt(checkbox.value));
                } else {
                    selectedItems.delete(parseInt(checkbox.value));
                }
            });
            updateBulkActionsButton();
        });
    }
}

// Update workshop filter based on selected industry
function updateWorkshopFilter() {
    const industrySelect = document.getElementById('industryFilter');
    const workshopSelect = document.getElementById('workshopFilter');
    
    if (!industrySelect || !workshopSelect) return;
    
    const selectedIndustryId = industrySelect.value;
    const workshopOptions = workshopSelect.querySelectorAll('option[data-industry]');
    
    // Reset workshop selection
    workshopSelect.value = '';
    
    // Show/hide workshop options based on selected industry
    workshopOptions.forEach(option => {
        const industryId = option.getAttribute('data-industry');
        if (!selectedIndustryId || industryId === selectedIndustryId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
}
function updateAreaFilter() {
    const workshopSelect = document.getElementById('workshopFilter');
    const areaSelect = document.getElementById('areaFilter');
    
    if (!workshopSelect || !areaSelect) return;
    
    const selectedWorkshopId = workshopSelect.value;
    const areaOptions = areaSelect.querySelectorAll('option[data-workshop]');
    
    // Reset area selection
    areaSelect.value = '';
    
    // Show/hide area options based on selected workshop
    areaOptions.forEach(option => {
        const workshopId = option.getAttribute('data-workshop');
        if (!selectedWorkshopId || workshopId === selectedWorkshopId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
}
// Load data from API
async function loadData() {
    console.log('Loading equipment data...');
    try {
        showLoading(true);
        
        // Get filter values
          const searchEl = document.getElementById('searchInput');
        const industryEl = document.getElementById('industryFilter');
        const workshopEl = document.getElementById('workshopFilter');
        const areaEl = document.getElementById('areaFilter'); // ✅ THAY ĐỔI
        const machineTypeEl = document.getElementById('machineTypeFilter'); // ✅ THAY ĐỔI
        
        currentFilters = {
            search: searchEl ? searchEl.value.trim() : '',
            industry_id: industryEl ? industryEl.value : '',
            workshop_id: workshopEl ? workshopEl.value : '',
            area_id: areaEl ? areaEl.value : '', // ✅ THAY ĐỔI
            machine_type_id: machineTypeEl ? machineTypeEl.value : '', // ✅ THAY ĐỔI
            sort_by: 'name',
            sort_order: 'ASC'
        };

        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 20,
            ...currentFilters
        });

        const response = await fetch(`api/equipment.php?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);

        if (result.success && result.data) {
            currentData = result.data.equipment || [];
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
    const tbody = document.getElementById('dataTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (!tbody) {
        console.error('dataTableBody element not found!');
        return;
    }
    
    if (!data || data.length === 0) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('d-none');
        return;
    }
    
    if (emptyState) emptyState.classList.add('d-none');
    
    const rows = data.map((item) => {
        const criticalityClass = `criticality-${item.criticality.toLowerCase()}`;
        const maintenanceIndicator = item.maintenance_due ? 'maintenance-indicator' : '';
        
        return `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input" name="selectedItems" 
                           value="${item.id}" onchange="handleItemSelection(this)">
                </td>
                <td>
    ${item.image_url ? 
        `<img src="${item.image_url}" alt="Equipment Image" class="equipment-image">` : 
        `<div class="no-image"><i class="fas fa-image"></i></div>`
    }
</td>
                <td>
                    <span class="equipment-code">${escapeHtml(item.code)}</span>
                </td>
                <td>
    <div class="fw-semibold">${escapeHtml(item.name)}</div>
    <div class="equipment-serial text-muted small" title="Số seri: ${escapeHtml(item.serial_number || '')}">
        ${item.serial_number ? `S/N: ${escapeHtml(item.serial_number)}` : 'Chưa có số seri'}
    </div>
</td>
                <td>
                    <div class="equipment-location">
                        <div class="fw-medium">${escapeHtml(item.industry_name || '')}</div>
                        <div>${escapeHtml(item.workshop_name || '')}</div>
                        ${item.line_name ? `<div class="text-muted">${escapeHtml(item.line_name)}</div>` : ''}
                        ${item.area_name ? `<div class="text-muted">${escapeHtml(item.area_name)}</div>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-info">${escapeHtml(item.machine_type_name || 'Chưa phân loại')}</span>
                </td>
                <td>
                    <div class="fw-medium">${escapeHtml(item.owner_name || 'Chưa có')}</div>
                    ${item.backup_owner_name ? `<div class="text-muted small">Owner: ${escapeHtml(item.backup_owner_name)}</div>` : ''}
                </td>
                <td>
                    <span class="badge ${criticalityClass}">${item.criticality}</span>
                </td>
                <td>
                    <span class="badge ${item.status_class} ${maintenanceIndicator}" style="cursor: pointer;" 
                          onclick="${canEdit ? `toggleStatus(${item.id})` : ''}"
                          title="${canEdit ? 'Click để thay đổi trạng thái' : ''}">
                        ${item.status_text}
                    </span>
                </td>
                <td class="text-muted small">
                    ${item.next_maintenance || 'Chưa lên lịch'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-info btn-action" 
                                onclick="viewEquipment(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${canEdit ? `
                        <button type="button" class="btn btn-outline-primary btn-action" 
                                onclick="editEquipment(${item.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        ${canDelete ? `
                        <button type="button" class="btn btn-outline-danger btn-action" 
                                onclick="deleteEquipment(${item.id}, '${escapeHtml(item.name)}')" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = rows.join('');
}

// Handle item selection
function handleItemSelection(checkbox) {
    const itemId = parseInt(checkbox.value);
    if (checkbox.checked) {
        selectedItems.add(itemId);
    } else {
        selectedItems.delete(itemId);
    }
    updateBulkActionsButton();
}

// Update bulk actions button
function updateBulkActionsButton() {
    const count = selectedItems.size;
    const bulkButton = document.querySelector('[onclick="showBulkActions()"]');
    
    if (bulkButton) {
        if (count > 0) {
            bulkButton.innerHTML = `<i class="fas fa-tasks me-1"></i> Thao tác (${count})`;
            bulkButton.classList.remove('btn-outline-info');
            bulkButton.classList.add('btn-warning');
        } else {
            bulkButton.innerHTML = `<i class="fas fa-tasks me-1"></i> Thao tác hàng loạt`;
            bulkButton.classList.remove('btn-warning');
            bulkButton.classList.add('btn-outline-info');
        }
    }
}

// Show bulk actions modal
function showBulkActions() {
    if (selectedItems.size === 0) {
        showNotification('Vui lòng chọn ít nhất một thiết bị', 'warning');
        return;
    }
    
    document.getElementById('selectedCount').textContent = selectedItems.size;
    const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
    modal.show();
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
    
    info.textContent = `Hiển thị ${start} - ${end} trong tổng số ${pagination.total_items} thiết bị`;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadData();
}

// View equipment details
async function viewEquipment(id) {
    window.location.href = `view.php?id=${id}`;
}

// Edit equipment
function editEquipment(id) {
    window.location.href = `edit.php?id=${id}`;
}

// Delete equipment
function deleteEquipment(id, name) {
    if (confirm(`Bạn có chắc chắn muốn xóa thiết bị "${name}"?\nHành động này không thể hoàn tác.`)) {
        performDelete(id);
    }
}

// Perform delete
async function performDelete(id) {
    try {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch('api/equipment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            loadData(); // Reload data
        } else {
            showNotification(result.message || 'Lỗi khi xóa thiết bị', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
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
        
        const response = await fetch('api/equipment.php', {
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

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('industryFilter').value = '';
    document.getElementById('workshopFilter').value = '';
    document.getElementById('areaFilter').value = ''; // ✅ THAY ĐỔI
    document.getElementById('machineTypeFilter').value = ''; // ✅ THAY ĐỔI
    
    updateWorkshopFilter();
    updateAreaFilter(); // ✅ THÊM MỚI
    currentPage = 1;
    loadData();
}

// Export data
function exportData() {
    const params = new URLSearchParams({
        action: 'export',
        ...currentFilters
    });
    
    window.location.href = `api/equipment.php?${params}`;
    showNotification('Đang tải file xuất...', 'info');
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show notification function
function showNotification(message, type = 'info') {
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
    
    const alertType = type === 'error' ? 'danger' : type;
    notification.className = `alert alert-${alertType} alert-dismissible fade show`;
    
    notification.innerHTML = `
        <i class="${icons[type] || icons.info} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
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

<?php require_once '../../includes/footer.php'; ?>