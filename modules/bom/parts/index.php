<?php
/**
 * Parts Management - Index Page
 * /modules/bom/parts/index.php
 * Quản lý danh sách linh kiện
 */

$pageTitle = 'Quản lý linh kiện';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';
require_once '../config.php';

// Check permission
requirePermission('bom', 'view');

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('bom', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Thêm linh kiện
    </a>';
}

if (hasPermission('bom', 'import')) {
    $pageActions .= ' <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="fas fa-file-import me-2"></i>Import
    </button>';
}

require_once '../../../includes/header.php';

// Get filters
$filters = [
    'category' => $_GET['category'] ?? '',
    'stock_status' => $_GET['stock_status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get categories for filter
$categories = $db->fetchAll(
    "SELECT DISTINCT category FROM parts WHERE category IS NOT NULL ORDER BY category"
);

// Get statistics
$stats = [
    'total_parts' => $db->fetch("SELECT COUNT(*) as count FROM parts")['count'],
    'categories' => $db->fetch("SELECT COUNT(DISTINCT category) as count FROM parts WHERE category IS NOT NULL")['count'],
    'low_stock' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM parts p 
        LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0
    ")['count'],
    'out_of_stock' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM parts p 
        LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) = 0
    ")['count']
];
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Tổng linh kiện</h5>
                        <h2 class="mb-0"><?php echo number_format($stats['total_parts']); ?></h2>
                    </div>
                    <i class="fas fa-cubes fa-2x text-primary opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Danh mục</h5>
                        <h2 class="mb-0"><?php echo number_format($stats['categories']); ?></h2>
                    </div>
                    <i class="fas fa-tags fa-2x text-info opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Sắp hết</h5>
                        <h2 class="mb-0"><?php echo number_format($stats['low_stock']); ?></h2>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Hết hàng</h5>
                        <h2 class="mb-0"><?php echo number_format($stats['out_of_stock']); ?></h2>
                    </div>
                    <i class="fas fa-times-circle fa-2x text-danger opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<form id="partsFilterForm" class="row mb-4">
    <div class="col-lg-4 col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="partsSearch" class="form-control" 
                   placeholder="Tìm theo mã, tên hoặc mô tả..." 
                   value="<?php echo htmlspecialchars($filters['search']); ?>">
        </div>
    </div>
    
    <div class="col-lg-2 col-md-6">
        <label for="category" class="form-label">Danh mục</label>
        <select id="filterCategory" name="category" class="form-select filter-select">
            <option value="">-- Tất cả --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                        <?php echo ($filters['category'] === $cat['category']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-lg-2 col-md-6">
        <label for="stock_status" class="form-label">Tồn kho</label>
        <select id="filterStockStatus" name="stock_status" class="form-select filter-select">
            <option value="">-- Tất cả --</option>
            <option value="ok" <?php echo ($filters['stock_status'] === 'ok') ? 'selected' : ''; ?>>Đủ hàng</option>
            <option value="low" <?php echo ($filters['stock_status'] === 'low') ? 'selected' : ''; ?>>Sắp hết</option>
            <option value="out" <?php echo ($filters['stock_status'] === 'out') ? 'selected' : ''; ?>>Hết hàng</option>
        </select>
    </div>
    
    <div class="col-lg-4 col-md-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search me-1"></i>Tìm kiếm
            </button>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i>Xóa
            </a>
            <?php if (hasPermission('bom', 'export')): ?>
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i>Xuất
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportAllParts('excel')">
                        <i class="fas fa-file-excel me-2"></i>Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportAllParts('csv')">
                        <i class="fas fa-file-csv me-2"></i>CSV
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Parts Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-cubes me-2"></i>
            Danh sách linh kiện
        </h5>
        <?php echo $pageActions; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="partsTable" class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th width="1%">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllParts">
                            </div>
                        </th>
                        <th>Mã linh kiện</th>
                        <th>Tên linh kiện</th>
                        <th>Danh mục</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Đơn giá</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="partsTableBody">
                    <!-- Data will be loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="small text-muted" id="paginationInfo">
            Hiển thị <span id="showingFrom">0</span> - <span id="showingTo">0</span> trong tổng <span id="totalItems">0</span> linh kiện
        </div>
        <div id="partsPagination"></div>
    </div>
</div>

<!-- Selected Actions -->
<div class="mt-3 d-none" id="selectedActions">
    <div class="alert alert-info d-flex align-items-center gap-3">
        <div>
            <i class="fas fa-info-circle me-2"></i>
            Đã chọn <span id="selectedCount">0</span> linh kiện
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                <i class="fas fa-edit me-1"></i>Cập nhật hàng loạt
            </button>
            <button onclick="bulkDeleteParts()" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-trash me-1"></i>Xóa hàng loạt
            </button>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import linh kiện</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm">
                    <div class="mb-3">
                        <label for="partsImportFile" class="form-label">Chọn file (.xlsx, .xls, .csv)</label>
                        <input type="file" id="partsImportFile" name="file" class="form-control" 
                               accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="updateExistingParts" name="update_existing">
                        <label class="form-check-label" for="updateExistingParts">
                            Cập nhật linh kiện hiện có (dựa trên mã linh kiện)
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="importParts()">
                    <i class="fas fa-file-import me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật hàng loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm">
                    <div class="mb-3">
                        <label for="bulkSelectParts" class="form-label">Linh kiện được chọn</label>
                        <select id="bulkSelectParts" multiple class="form-select" size="5"></select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="bulkUnitPrice" class="form-label">Đơn giá (VNĐ)</label>
                            <input type="number" id="bulkUnitPrice" name="unit_price" class="form-control" 
                                   step="0.01" placeholder="0">
                        </div>
                        <div class="col-md-6">
                            <label for="bulkMinStock" class="form-label">Mức tồn tối thiểu</label>
                            <input type="number" id="bulkMinStock" name="min_stock" class="form-control" 
                                   step="0.1" placeholder="0">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label for="bulkMaxStock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="bulkMaxStock" name="max_stock" class="form-control" 
                                   step="0.1" placeholder="0">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="bulkUpdateParts()">
                    <i class="fas fa-save me-1"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Export functions
function exportAllParts(format) {
    const filters = CMMS.Parts.getFilters();
    const params = new URLSearchParams({
        action: 'export_all',
        format: format,
        ...filters
    });
    
    window.open('/modules/bom/api/export.php?' + params, '_blank');
}

// Import parts
function importParts() {
    const fileInput = document.getElementById('partsImportFile');
    if (!fileInput || !fileInput.files[0]) {
        CMMS.showToast('Vui lòng chọn file để import', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('update_existing', document.getElementById('updateExistingParts').checked ? '1' : '0');
    formData.append('action', 'import');
    
    CMMS.ajax({
        url: '/modules/bom/imports/parts_import.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
                CMMS.Parts.loadPartsList();
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

// Bulk update parts
function bulkUpdateParts() {
    const form = document.getElementById('bulkUpdateForm');
    const formData = new FormData(form);
    
    const selectedParts = Array.from(document.getElementById('bulkSelectParts').selectedOptions)
                               .map(option => option.value);
    
    if (selectedParts.length === 0) {
        CMMS.showToast('Vui lòng chọn ít nhất một linh kiện', 'warning');
        return;
    }
    
    formData.append('part_ids', JSON.stringify(selectedParts));
    formData.append('action', 'bulk_update');
    
    CMMS.ajax({
        url: '/modules/bom/api/parts.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal')).hide();
                CMMS.Parts.loadPartsList();
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

// Load parts for bulk update when modal opens
document.getElementById('bulkUpdateModal').addEventListener('show.bs.modal', function() {
    CMMS.ajax({
        url: '/modules/bom/api/parts.php?action=list_simple',
        method: 'GET',
        success: (data) => {
            if (data.success) {
                const select = document.getElementById('bulkSelectParts');
                select.innerHTML = data.data.map(part => 
                    `<option value="${part.id}">${part.part_code} - ${part.part_name}</option>`
                ).join('');
            }
        }
    });
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set initial filter values from URL
    const urlParams = new URLSearchParams(window.location.search);
    
    ['search', 'category', 'stock_status'].forEach(param => {
        const element = document.getElementById('filter' + param.charAt(0).toUpperCase() + param.slice(1)) || 
                       document.getElementById('parts' + param.charAt(0).toUpperCase() + param.slice(1));
        if (element && urlParams.get(param)) {
            element.value = urlParams.get(param);
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>