<?php
/**
 * Stock Report Page
 * /modules/bom/reports/stock_report.php
 * Báo cáo tồn kho theo BOM
 */

$pageTitle = 'Báo cáo tồn kho';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

// Check permission
requirePermission('bom', 'view');

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Báo cáo tồn kho', 'url' => '']
];

require_once '../../../includes/header.php';
require_once '../config.php';

// Get filters
$filters = [
    'bom_id' => intval($_GET['bom_id'] ?? 0),
    'machine_type' => intval($_GET['machine_type'] ?? 0),
    'stock_status' => $_GET['stock_status'] ?? '',
    'category' => $_GET['category'] ?? ''
];

// Get BOMs and Machine Types for filters
$boms = $db->fetchAll(
    "SELECT mb.id, mb.bom_name, mb.bom_code, mt.name as machine_type_name 
     FROM machine_bom mb 
     JOIN machine_types mt ON mb.machine_type_id = mt.id 
     ORDER BY mb.bom_name"
);

$machineTypes = $db->fetchAll(
    "SELECT id, name FROM machine_types WHERE status = 'active' ORDER BY name"
);

$categories = $db->fetchAll(
    "SELECT DISTINCT category FROM parts WHERE category IS NOT NULL ORDER BY category"
);

// Build query based on filters
$whereConditions = ['1=1'];
$params = [];

if ($filters['bom_id']) {
    $whereConditions[] = 'mb.id = ?';
    $params[] = $filters['bom_id'];
}

if ($filters['machine_type']) {
    $whereConditions[] = 'mb.machine_type_id = ?';
    $params[] = $filters['machine_type'];
}

if ($filters['category']) {
    $whereConditions[] = 'p.category = ?';
    $params[] = $filters['category'];
}

$whereClause = implode(' AND ', $whereConditions);
$havingClause = '';

if ($filters['stock_status']) {
    switch ($filters['stock_status']) {
        case 'low':
            $havingClause = 'HAVING stock_status = "Low"';
            break;
        case 'out':
            $havingClause = 'HAVING stock_status = "Out of Stock"';
            break;
        case 'ok':
            $havingClause = 'HAVING stock_status = "OK"';
            break;
    }
}

// Get stock report data
$sql = "SELECT mb.id as bom_id, mb.bom_name, mb.bom_code,
               mt.name as machine_type_name,
               p.part_code, p.part_name, p.category,
               bi.quantity as required_qty, bi.unit, bi.priority,
               p.unit_price, p.min_stock,
               COALESCE(oh.Onhand, 0) as stock_quantity,
               COALESCE(oh.UOM, bi.unit) as stock_unit,
               COALESCE(oh.OH_Value, 0) as stock_value,
               (bi.quantity * p.unit_price) as required_value,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) >= bi.quantity THEN 'Sufficient'
                   WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                   WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                   ELSE 'OK'
               END as stock_status,
               GREATEST(0, bi.quantity - COALESCE(oh.Onhand, 0)) as shortage_qty,
               GREATEST(0, (bi.quantity - COALESCE(oh.Onhand, 0)) * p.unit_price) as shortage_value
        FROM machine_bom mb
        JOIN machine_types mt ON mb.machine_type_id = mt.id
        JOIN bom_items bi ON mb.id = bi.bom_id
        JOIN parts p ON bi.part_id = p.id
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE $whereClause
        $havingClause
        ORDER BY mb.bom_name, p.part_code";

$stockData = $db->fetchAll($sql, $params);

// Calculate summary statistics
$summary = [
    'total_items' => count($stockData),
    'sufficient' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_required_value' => 0,
    'total_shortage_value' => 0
];

foreach ($stockData as $item) {
    $summary['total_required_value'] += $item['required_value'];
    $summary['total_shortage_value'] += $item['shortage_value'];
    
    switch ($item['stock_status']) {
        case 'Sufficient':
        case 'OK':
            $summary['sufficient']++;
            break;
        case 'Low':
            $summary['low_stock']++;
            break;
        case 'Out of Stock':
            $summary['out_of_stock']++;
            break;
    }
}

$pageActions = '<div class="btn-group">
    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-download me-2"></i>Xuất báo cáo
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="exportStockReport(\'excel\')">
            <i class="fas fa-file-excel me-2"></i>Excel
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportStockReport(\'csv\')">
            <i class="fas fa-file-csv me-2"></i>CSV
        </a></li>
    </ul>
</div>';
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($summary['total_items']); ?></div>
                        <div class="stat-label">Tổng linh kiện</div>
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($summary['sufficient']); ?></div>
                        <div class="stat-label">Đủ hàng</div>
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
                        <div class="stat-number"><?php echo number_format($summary['low_stock']); ?></div>
                        <div class="stat-label">Sắp hết</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($summary['out_of_stock']); ?></div>
                        <div class="stat-label">Hết hàng</div>
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
            <div class="col-lg-3 col-md-6">
                <label for="bom_id" class="form-label">BOM cụ thể</label>
                <select name="bom_id" id="bom_id" class="form-select">
                    <option value="">-- Tất cả BOM --</option>
                    <?php foreach ($boms as $bom): ?>
                        <option value="<?php echo $bom['id']; ?>" 
                                <?php echo ($filters['bom_id'] == $bom['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bom['bom_code'] . ' - ' . $bom['bom_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <label for="machine_type" class="form-label">Dòng máy</label>
                <select name="machine_type" id="machine_type" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <?php foreach ($machineTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                <?php echo ($filters['machine_type'] == $type['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <label for="category" class="form-label">Danh mục</label>
                <select name="category" id="category" class="form-select">
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
                <label for="stock_status" class="form-label">Trạng thái</label>
                <select name="stock_status" id="stock_status" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="ok" <?php echo ($filters['stock_status'] === 'ok') ? 'selected' : ''; ?>>Đủ hàng</option>
                    <option value="low" <?php echo ($filters['stock_status'] === 'low') ? 'selected' : ''; ?>>Sắp hết</option>
                    <option value="out" <?php echo ($filters['stock_status'] === 'out') ? 'selected' : ''; ?>>Hết hàng</option>
                </select>
            </div>
            
            <div class="col-lg-3 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Lọc
                    </button>
                    <a href="stock_report.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Xóa
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Financial Summary -->
<?php if ($summary['total_shortage_value'] > 0): ?>
<div class="alert alert-warning">
    <div class="row">
        <div class="col-md-6">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Tổng giá trị cần bổ sung:</strong>
            <span class="cost-display fs-5"><?php echo formatVND($summary['total_shortage_value']); ?></span>
        </div>
        <div class="col-md-6 text-md-end">
            <strong>Tổng giá trị BOM:</strong>
            <span class="cost-display"><?php echo formatVND($summary['total_required_value']); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stock Report Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>
            Báo cáo tồn kho theo BOM
        </h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table bom-table mb-0" id="stockReportTable">
                <thead>
                    <tr>
                        <th>BOM</th>
                        <th>Linh kiện</th>
                        <th class="text-center">Cần</th>
                        <th class="text-center">Tồn</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-end">Giá trị cần</th>
                        <th class="text-end">Thiếu hụt</th>
                        <th class="text-center hide-mobile">Ưu tiên</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockData)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="bom-empty">
                                <div class="bom-empty-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="bom-empty-text">Không có dữ liệu phù hợp với bộ lọc</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $currentBOM = null;
                        foreach ($stockData as $item): 
                        ?>
                        <tr class="bom-item-row priority-<?php echo $item['priority']; ?>"
                            data-stock-status="<?php echo strtolower($item['stock_status']); ?>">
                            <td>
                                <?php if ($currentBOM !== $item['bom_code']): ?>
                                    <?php $currentBOM = $item['bom_code']; ?>
                                    <div class="d-flex flex-column">
                                        <span class="part-code"><?php echo htmlspecialchars($item['bom_code']); ?></span>
                                        <small><strong><?php echo htmlspecialchars($item['bom_name']); ?></strong></small>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['machine_type_name']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="part-code"><?php echo htmlspecialchars($item['part_code']); ?></span>
                                    <strong><?php echo htmlspecialchars($item['part_name']); ?></strong>
                                    <?php if ($item['category']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <strong><?php echo number_format($item['required_qty'], 2); ?></strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                            </td>
                            
                            <td class="text-center">
                                <strong class="<?php echo ($item['stock_quantity'] >= $item['required_qty']) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($item['stock_quantity'], 2); ?>
                                </strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['stock_unit']); ?></small>
                            </td>
                            
                            <td class="text-center">
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch ($item['stock_status']) {
                                    case 'Sufficient':
                                    case 'OK':
                                        $statusClass = 'bg-success';
                                        $statusText = 'Đủ hàng';
                                        break;
                                    case 'Low':
                                        $statusClass = 'bg-warning';
                                        $statusText = 'Sắp hết';
                                        break;
                                    case 'Out of Stock':
                                        $statusClass = 'bg-danger';
                                        $statusText = 'Hết hàng';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                            
                            <td class="text-end">
                                <span class="cost-display"><?php echo formatVND($item['required_value']); ?></span>
                            </td>
                            
                            <td class="text-end">
                                <?php if ($item['shortage_value'] > 0): ?>
                                    <span class="cost-display text-danger fw-bold">
                                        <?php echo formatVND($item['shortage_value']); ?>
                                    </span>
                                    <small class="d-block text-muted">
                                        <?php echo number_format($item['shortage_qty'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-success">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center hide-mobile">
                                <span class="priority-badge priority-<?php echo $item['priority']; ?>">
                                    <?php echo $bomConfig['priorities'][$item['priority']]['name'] ?? $item['priority']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                
                <?php if (!empty($stockData)): ?>
                <tfoot>
                    <tr class="table-secondary fw-bold">
                        <td colspan="5" class="text-end">Tổng cộng:</td>
                        <td class="text-end">
                            <span class="cost-display"><?php echo formatVND($summary['total_required_value']); ?></span>
                        </td>
                        <td class="text-end">
                            <span class="cost-display text-danger"><?php echo formatVND($summary['total_shortage_value']); ?></span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
// Export functions
function exportStockReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'stock_report');
    params.set('format', format);
    
    window.open('/modules/bom/api/export.php?' + params, '_blank');
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize data table
    CMMS.dataTable.init('stockReportTable', {
        searching: true,
        sorting: true,
        pageSize: 50
    });
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        window.location.reload();
    }, 300000);
});
</script>

<?php require_once '../../../includes/footer.php'; ?>