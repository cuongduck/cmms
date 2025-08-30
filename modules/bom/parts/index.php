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
require_once '../config.php';

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
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0
    ")['count'],
    'out_of_stock' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM parts p 
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) = 0
    ")['count']
];
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
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
        </div>
    </form>
</div>

<!-- Parts Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-cubes me-2"></i>
                Danh sách linh kiện
            </h5>
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Tổng: <span id="totalRecords">0</span> linh kiện
                </small>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table bom-table mb-0" id="partsTable">
                <thead>
                    <tr>
                        <th>Linh kiện</th>
                        <th width="120">Danh mục</th>
                        <th width="80" class="text-center">Đơn vị</th>
                        <th width="120" class="text-end">Đơn giá</th>
                        <th width="120" class="text-center">Tồn kho</th>
                        <th width="150" class="hide-mobile">Nhà cung cấp</th>
                        <th width="80" class="text-center">Sử dụng</th>
                        <th width="120">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="bom-loading">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Đang tải dữ liệu...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Hiển thị <span id="showingFrom">0</span> - <span id="showingTo">0</span> 
                trong tổng số <span id="totalItems">0</span> linh kiện
            </div>
            <div id="partsPagination">
                <!-- Pagination will be rendered by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Info -->
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác nhanh
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('bom', 'create')): ?>
                    <a href="add.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i>Thêm linh kiện mới
                    </a>
                    <?php endif; ?>
                    
                    <a href="../reports/stock_report.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-2"></i>Báo cáo tồn kho
                    </a>
                    
                    <?php if ($stats['low_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
                    <a href="../reports/shortage_report.php" class="btn btn-outline-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Linh kiện thiếu hàng
                    </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                        <i class="fas fa-edit me-2"></i>Cập nhật hàng loạt
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Hướng dẫn
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Mã linh kiện phải duy nhất trong hệ thống
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Tồn kho được đồng bộ tự động từ ERP
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Cảnh báo khi tồn kho < mức tối thiểu
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        Hỗ trợ nhiều nhà cung cấp cho 1 linh kiện
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<?php if (hasPermission('bom', 'import')): ?>
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i>
                    Import danh sách linh kiện
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="partsImportForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="partsImportFile" class="form-label">Chọn file Excel</label>
                        <input type="file" class="form-control" id="partsImportFile" 
                               accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Chỉ chấp nhận file .xlsx hoặc .xls, tối đa 10MB
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="updateExistingParts">
                            <label class="form-check-label" for="updateExistingParts">
                                Cập nhật linh kiện đã tồn tại
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Template:</strong> 
                        <a href="../imports/parts_template.xlsx" target="_blank">Tải template Excel</a> 
                        với định dạng chuẩn.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="importParts()">
                    <i class="fas fa-upload me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Cập nhật hàng loạt
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm">
                    <div class="mb-3">
                        <label class="form-label">Chọn linh kiện cần cập nhật</label>
                        <select id="bulkSelectParts" class="form-select" multiple size="8">
                            <!-- Options will be loaded by JavaScript -->
                        </select>
                        <div class="form-text">
                            Giữ Ctrl/Cmd để chọn nhiều linh kiện
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulkCategory" class="form-label">Danh mục mới</label>
                            <select id="bulkCategory" name="category" class="form-select">
                                <option value="">-- Không thay đổi --</option>
                                <?php foreach ($bomConfig['part_categories'] as $key => $name): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkSupplier" class="form-label">Nhà cung cấp</label>
                            <input type="text" id="bulkSupplier" name="supplier_name" class="form-control" 
                                   placeholder="Tên nhà cung cấp">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulkMinStock" class="form-label">Mức tồn tối thiểu</label>
                            <input type="number" id="bulkMinStock" name="min_stock" class="form-control" 
                                   step="0.1" placeholder="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
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