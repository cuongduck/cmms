<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * BOM Module - Index Page
 * /modules/bom/index.php
 * Trang danh sách BOM thiết bị
 */

$pageTitle = 'Danh sách BOM thiết bị';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

// Breadcrumb
//$breadcrumb = [
 //   ['title' => 'BOM thiết bị', 'url' => '']
//];
$breadcrumb = [
    ['title' => 'BOM thiết bị']
];

require_once 'config.php';

// Page actions
$pageActions = '';
if (hasPermission('bom', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Tạo BOM mới
    </a>';
}

if (hasPermission('bom', 'import')) {
    $pageActions .= ' <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="fas fa-file-import me-2"></i>Import BOM
    </button>';
}

require_once '../../includes/header.php';

// Get filters
$filters = [
    'machine_type' => $_GET['machine_type'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => max(1, intval($_GET['page'] ?? 1))
];

// Get machine types for filter
$machineTypes = $db->fetchAll(
    "SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name"
);

// Get BOM statistics
$stats = [
    'total_boms' => $db->fetch("SELECT COUNT(*) as count FROM machine_bom")['count'],
    'total_parts' => $db->fetch("SELECT COUNT(*) as count FROM parts")['count'],
    'total_machine_types' => $db->fetch("SELECT COUNT(*) as count FROM machine_types WHERE status = 'active'")['count'],
    'low_stock_parts' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM parts p 
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0
    ")['count']
];
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_boms']); ?></div>
                        <div class="stat-label">Tổng BOM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_parts']); ?></div>
                        <div class="stat-label">Linh kiện</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_machine_types']); ?></div>
                        <div class="stat-label">Dòng máy</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['low_stock_parts']); ?></div>
                        <div class="stat-label">Sắp hết hàng</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bom-filters">
    <form method="GET" class="filter-group">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="search" class="form-label">Tìm kiếm</label>
                <input type="text" id="bomSearch" name="search" class="form-control filter-input" 
                       placeholder="Tìm theo tên BOM, mã BOM, dòng máy..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            
            <div class="col-lg-3 col-md-6">
                <label for="machine_type" class="form-label">Dòng máy</label>
                <select id="filterMachineType" name="machine_type" class="form-select filter-select bom-filter">
                    <option value="">-- Tất cả dòng máy --</option>
                    <?php foreach ($machineTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                <?php echo ($filters['machine_type'] == $type['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <label for="stock_status" class="form-label">Trạng thái kho</label>
                <select id="filterStockStatus" name="stock_status" class="form-select filter-select bom-filter">
                    <option value="">-- Tất cả --</option>
                    <option value="ok">Đủ hàng</option>
                    <option value="low">Sắp hết</option>
                    <option value="out">Hết hàng</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-6">
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
                            <li><a class="dropdown-item" href="#" onclick="exportAllBOM('excel')">
                                <i class="fas fa-file-excel me-2"></i>Excel
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportAllBOM('pdf')">
                                <i class="fas fa-file-pdf me-2"></i>PDF
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportAllBOM('csv')">
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

<!-- BOM List Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Danh sách BOM thiết bị
            </h5>
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Tổng: <span id="totalRecords">0</span> BOM
                </small>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table bom-table mb-0" id="bomTable">
                <thead>
                    <tr>
                        <th>BOM</th>
                        <th>Dòng máy</th>
                        <th class="text-center">Số linh kiện</th>
                        <th class="text-end">Tổng chi phí</th>
                        <th class="hide-mobile">Ngày tạo</th>
                        <th class="hide-mobile">Người tạo</th>
                        <th width="120">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4">
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
                trong tổng số <span id="totalItems">0</span> BOM
            </div>
            <div id="bomPagination">
                <!-- Pagination will be rendered by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
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
                        <i class="fas fa-plus me-2"></i>Tạo BOM mới
                    </a>
                    <?php endif; ?>
                    
                    <a href="parts/" class="btn btn-outline-info">
                        <i class="fas fa-cubes me-2"></i>Quản lý linh kiện
                    </a>
                    
                    <a href="reports/stock_report.php" class="btn btn-outline-warning">
                        <i class="fas fa-chart-bar me-2"></i>Báo cáo tồn kho
                    </a>
                    
                    <a href="reports/shortage_report.php" class="btn btn-outline-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Báo cáo thiếu hàng
                    </a>
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
                        BOM được tạo theo từng dòng máy
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Tự động tính toán chi phí và tồn kho
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Hỗ trợ import/export Excel
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        Báo cáo thiếu hàng theo BOM
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
                    Import BOM từ Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="bomImportFile" class="form-label">Chọn file Excel</label>
                        <input type="file" class="form-control" id="bomImportFile" 
                               accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Chỉ chấp nhận file .xlsx hoặc .xls, tối đa 10MB
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="updateExisting">
                            <label class="form-check-label" for="updateExisting">
                                Cập nhật BOM đã tồn tại
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Lưu ý:</strong> 
                        <a href="imports/template.xlsx" target="_blank">Tải template Excel</a> 
                        để đảm bảo định dạng đúng.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="importBOM()">
                    <i class="fas fa-upload me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Export functions
function exportAllBOM(format) {
    const filters = CMMS.BOM.getFilters();
    const params = new URLSearchParams({
        action: 'export_all',
        format: format,
        ...filters
    });
    
    window.open('/modules/bom/api/export.php?' + params, '_blank');
}

// Initialize page data
document.addEventListener('DOMContentLoaded', function() {
    // Set initial filter values from URL
    const urlParams = new URLSearchParams(window.location.search);
    
    const searchInput = document.getElementById('bomSearch');
    if (searchInput && urlParams.get('search')) {
        searchInput.value = urlParams.get('search');
    }
    
    const machineTypeSelect = document.getElementById('filterMachineType');
    if (machineTypeSelect && urlParams.get('machine_type')) {
        machineTypeSelect.value = urlParams.get('machine_type');
    }
    
    // Load BOM list with current filters
    CMMS.BOM.config.currentPage = <?php echo $filters['page']; ?>;
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        CMMS.BOM.loadBOMList(CMMS.BOM.getFilters());
    }, 300000);
});
</script>

<?php require_once '../../includes/footer.php'; ?>