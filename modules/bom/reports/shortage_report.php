<?php
/**
 * Shortage Report Page
 * /modules/bom/reports/shortage_report.php
 * Báo cáo thiếu hàng theo BOM
 */

$pageTitle = 'Báo cáo thiếu hàng';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

require_once '../config.php';

// Check permission
requirePermission('bom', 'view');

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Báo cáo thiếu hàng', 'url' => '']
];

require_once '../../../includes/header.php';

// Get filters
$filters = [
    'bom_id' => intval($_GET['bom_id'] ?? 0),
    'machine_type' => intval($_GET['machine_type'] ?? 0),
    'priority' => $_GET['priority'] ?? '',
    'category' => $_GET['category'] ?? '',
    'shortage_threshold' => floatval($_GET['shortage_threshold'] ?? 0)
];

// Get reference data for filters
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

// Build shortage report query
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

if ($filters['priority']) {
    $whereConditions[] = 'bi.priority = ?';
    $params[] = $filters['priority'];
}

$whereClause = implode(' AND ', $whereConditions);

// Shortage conditions
$shortageConditions = [
    'COALESCE(oh.Onhand, 0) < bi.quantity', // Stock less than required
];

if ($filters['shortage_threshold'] > 0) {
    $shortageConditions[] = '(bi.quantity - COALESCE(oh.Onhand, 0)) * p.unit_price >= ?';
    $params[] = $filters['shortage_threshold'];
}

$shortageClause = '(' . implode(' AND ', $shortageConditions) . ')';

// Get shortage data
$sql = "SELECT mb.id as bom_id, mb.bom_name, mb.bom_code,
               mt.name as machine_type_name,
               p.id as part_id, p.part_code, p.part_name, p.category,
               bi.quantity as required_qty, bi.unit, bi.priority,
               p.unit_price, p.min_stock, p.supplier_name,
               COALESCE(oh.Onhand, 0) as stock_quantity,
               COALESCE(oh.UOM, bi.unit) as stock_unit,
               (bi.quantity - COALESCE(oh.Onhand, 0)) as shortage_qty,
               (bi.quantity - COALESCE(oh.Onhand, 0)) * p.unit_price as shortage_value,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                   WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Below Min'
                   ELSE 'Insufficient'
               END as shortage_type,
               -- Get recent transactions
               (SELECT COUNT(*) FROM transaction t WHERE t.ItemCode IN 
                   (SELECT pim2.item_code FROM part_inventory_mapping pim2 WHERE pim2.part_id = p.id)
                   AND t.TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND t.TransactionType = 'Issue'
               ) as recent_usage,
               -- Get last transaction date
               (SELECT MAX(t.TransactionDate) FROM transaction t WHERE t.ItemCode IN
                   (SELECT pim2.item_code FROM part_inventory_mapping pim2 WHERE pim2.part_id = p.id)
               ) as last_transaction_date
        FROM machine_bom mb
        JOIN machine_types mt ON mb.machine_type_id = mt.id
        JOIN bom_items bi ON mb.id = bi.bom_id
        JOIN parts p ON bi.part_id = p.id
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE $whereClause AND $shortageClause
        ORDER BY (bi.quantity - COALESCE(oh.Onhand, 0)) * p.unit_price DESC, bi.priority DESC";

$shortageData = $db->fetchAll($sql, $params);

// Calculate summary
$summary = [
    'total_shortage_items' => count($shortageData),
    'total_shortage_value' => array_sum(array_column($shortageData, 'shortage_value')),
    'critical_items' => 0,
    'high_items' => 0,
    'medium_items' => 0,
    'low_items' => 0,
    'out_of_stock' => 0,
    'below_min' => 0,
    'insufficient' => 0
];

foreach ($shortageData as $item) {
    // Count by priority
    switch ($item['priority']) {
        case 'Critical':
            $summary['critical_items']++;
            break;
        case 'High':
            $summary['high_items']++;
            break;
        case 'Medium':
            $summary['medium_items']++;
            break;
        case 'Low':
            $summary['low_items']++;
            break;
    }
    
    // Count by shortage type
    switch ($item['shortage_type']) {
        case 'Out of Stock':
            $summary['out_of_stock']++;
            break;
        case 'Below Min':
            $summary['below_min']++;
            break;
        case 'Insufficient':
            $summary['insufficient']++;
            break;
    }
}

$pageActions = '<div class="btn-group">
    <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-download me-2"></i>Xuất báo cáo
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="exportShortageReport(\'excel\')">
            <i class="fas fa-file-excel me-2"></i>Excel
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportShortageReport(\'csv\')">
            <i class="fas fa-file-csv me-2"></i>CSV
        </a></li>
    </ul>
</div>
<button class="btn btn-warning" onclick="generatePurchaseOrder()">
    <i class="fas fa-shopping-cart me-2"></i>Tạo đề xuất mua hàng
</button>';
?>

<!-- Alert for Critical Shortage -->
<?php if ($summary['critical_items'] > 0 || $summary['out_of_stock'] > 0): ?>
<div class="alert alert-danger">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
        <div>
            <h5 class="alert-heading mb-1">Cảnh báo thiếu hàng nghiêm trọng!</h5>
            <p class="mb-0">
                Có <strong><?php echo $summary['critical_items']; ?> linh kiện ưu tiên cao</strong> 
                và <strong><?php echo $summary['out_of_stock']; ?> linh kiện hết hàng</strong> cần bổ sung ngay lập tức.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo number_format($summary['total_shortage_items']); ?></div>
                <div class="stat-label">Thiếu hàng</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo number_format($summary['critical_items']); ?></div>
                <div class="stat-label">Nghiêm trọng</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #ea580c, #c2410c);">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo number_format($summary['high_items']); ?></div>
                <div class="stat-label">Ưu tiên cao</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo number_format($summary['out_of_stock']); ?></div>
                <div class="stat-label">Hết hàng</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-8 col-sm-12 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #1f2937, #111827);">
            <div class="card-body text-center">
                <div class="stat-number"><?php echo formatVND($summary['total_shortage_value']); ?></div>
                <div class="stat-label">Tổng giá trị thiếu</div>
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
                <label for="priority" class="form-label">Ưu tiên</label>
                <select name="priority" id="priority" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="Critical" <?php echo ($filters['priority'] === 'Critical') ? 'selected' : ''; ?>>Nghiêm trọng</option>
                    <option value="High" <?php echo ($filters['priority'] === 'High') ? 'selected' : ''; ?>>Cao</option>
                    <option value="Medium" <?php echo ($filters['priority'] === 'Medium') ? 'selected' : ''; ?>>Trung bình</option>
                    <option value="Low" <?php echo ($filters['priority'] === 'Low') ? 'selected' : ''; ?>>Thấp</option>
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
                <label for="shortage_threshold" class="form-label">Giá trị tối thiểu</label>
                <input type="number" name="shortage_threshold" id="shortage_threshold" class="form-control"
                       placeholder="VND" value="<?php echo $filters['shortage_threshold']; ?>">
            </div>
            
            <div class="col-lg-3 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Lọc
                    </button>
                    <a href="shortage_report.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Xóa
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Shortage Report Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
            Báo cáo thiếu hàng chi tiết
        </h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table bom-table mb-0" id="shortageReportTable">
                <thead>
                    <tr>
                        <th width="40">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllShortage">
                            </div>
                        </th>
                        <th>BOM</th>
                        <th>Linh kiện</th>
                        <th class="text-center">Cần</th>
                        <th class="text-center">Tồn</th>
                        <th class="text-center">Thiếu</th>
                        <th class="text-end">Giá trị thiếu</th>
                        <th class="text-center">Ưu tiên</th>
                        <th class="hide-mobile">Nhà cung cấp</th>
                        <th class="hide-mobile text-center">Giao dịch gần nhất</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shortageData)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <div class="bom-empty">
                                <div class="bom-empty-icon text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="bom-empty-text text-success">Không có linh kiện thiếu hàng!</div>
                                <p class="text-muted">Tất cả linh kiện đều đủ số lượng theo yêu cầu BOM.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $currentBOM = null;
                        foreach ($shortageData as $index => $item): 
                        ?>
                        <tr class="bom-item-row priority-<?php echo $item['priority']; ?>" 
                            data-part-id="<?php echo $item['part_id']; ?>">
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input shortage-checkbox" type="checkbox" 
                                           value="<?php echo $item['part_id']; ?>"
                                           data-shortage-value="<?php echo $item['shortage_value']; ?>"
                                           data-shortage-qty="<?php echo $item['shortage_qty']; ?>">
                                </div>
                            </td>
                            
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
                                <strong class="text-danger">
                                    <?php echo number_format($item['stock_quantity'], 2); ?>
                                </strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['stock_unit']); ?></small>
                                <span class="badge <?php echo getStockStatusClass($item['shortage_type']); ?>">
                                    <?php echo getStockStatusText($item['shortage_type']); ?>
                                </span>
                            </td>
                            
                            <td class="text-center">
                                <strong class="text-danger fs-6">
                                    <?php echo number_format($item['shortage_qty'], 2); ?>
                                </strong>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                            </td>
                            
                            <td class="text-end">
                                <span class="cost-display text-danger fw-bold">
                                    <?php echo formatVND($item['shortage_value']); ?>
                                </span>
                            </td>
                            
                            <td class="text-center">
                                <span class="priority-badge priority-<?php echo $item['priority']; ?>">
                                    <?php echo $bomConfig['priorities'][$item['priority']]['name'] ?? $item['priority']; ?>
                                </span>
                            </td>
                            
                            <td class="hide-mobile">
                                <small><?php echo htmlspecialchars($item['supplier_name'] ?: 'N/A'); ?></small>
                                <?php if ($item['recent_usage'] > 0): ?>
                                    <small class="d-block text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo $item['recent_usage']; ?> lần xuất trong 30 ngày
                                    </small>
                                <?php endif; ?>
                            </td>
                            
                            <td class="hide-mobile text-center">
                                <?php if ($item['last_transaction_date']): ?>
                                    <small><?php echo formatDate($item['last_transaction_date']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Chưa có giao dịch</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                
                <?php if (!empty($shortageData)): ?>
                <tfoot>
                    <tr class="table-danger fw-bold">
                        <td colspan="6" class="text-end">Tổng giá trị thiếu hụt:</td>
                        <td class="text-end">
                            <span class="cost-display fs-5"><?php echo formatVND($summary['total_shortage_value']); ?></span>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <?php if (!empty($shortageData)): ?>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                <span id="selectedCount">0</span> items đã chọn |
                Giá trị: <span id="selectedValue" class="cost-display">0 ₫</span>
            </div>
            
            <div class="btn-group">
                <button class="btn btn-primary" onclick="addToCartSelected()" disabled id="addToCartBtn">
                    <i class="fas fa-cart-plus me-1"></i>Thêm vào giỏ hàng
                </button>
                <button class="btn btn-warning" onclick="createPurchaseRequest()" disabled id="purchaseRequestBtn">
                    <i class="fas fa-file-invoice me-1"></i>Tạo đề xuất mua
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Export functions
function exportShortageReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'shortage_report');
    params.set('format', format);
    
    window.open('/modules/bom/api/export.php?' + params, '_blank');
}

// Selection management
let selectedItems = [];

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.shortage-checkbox:checked');
    const count = checkboxes.length;
    let totalValue = 0;
    
    selectedItems = [];
    
    checkboxes.forEach(cb => {
        const value = parseFloat(cb.dataset.shortageValue || 0);
        const qty = parseFloat(cb.dataset.shortageQty || 0);
        totalValue += value;
        
        selectedItems.push({
            partId: cb.value,
            shortageQty: qty,
            shortageValue: value
        });
    });
    
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedValue').textContent = CMMS.BOM.formatCurrency(totalValue);
    
    // Enable/disable buttons
    const addToCartBtn = document.getElementById('addToCartBtn');
    const purchaseRequestBtn = document.getElementById('purchaseRequestBtn');
    
    if (addToCartBtn) addToCartBtn.disabled = count === 0;
    if (purchaseRequestBtn) purchaseRequestBtn.disabled = count === 0;
}

// Select all functionality
document.getElementById('selectAllShortage')?.addEventListener('change', function(e) {
    const checkboxes = document.querySelectorAll('.shortage-checkbox');
    checkboxes.forEach(cb => cb.checked = e.target.checked);
    updateSelectedCount();
});

// Individual checkbox events
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('shortage-checkbox')) {
        updateSelectedCount();
    }
});

// Add to cart functionality
function addToCartSelected() {
    if (selectedItems.length === 0) {
        CMMS.showToast('Vui lòng chọn ít nhất một linh kiện', 'warning');
        return;
    }
    
    // Implement shopping cart functionality
    CMMS.showToast(`Đã thêm ${selectedItems.length} linh kiện vào giỏ hàng`, 'success');
}

// Create purchase request
function createPurchaseRequest() {
    if (selectedItems.length === 0) {
        CMMS.showToast('Vui lòng chọn ít nhất một linh kiện', 'warning');
        return;
    }
    
    CMMS.ajax({
        url: '/modules/bom/api/purchase_request.php',
        method: 'POST',
        body: JSON.stringify({
            action: 'create_from_shortage',
            items: selectedItems
        }),
        headers: {
            'Content-Type': 'application/json'
        },
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                // Redirect to purchase request page
                setTimeout(() => {
                    window.location.href = '/modules/purchase/view.php?id=' + data.request_id;
                }, 1500);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

// Generate purchase order from all shortage
function generatePurchaseOrder() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'generate_po');
    
    window.open('/modules/bom/api/purchase_order.php?' + params, '_blank');
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize data table
    CMMS.dataTable.init('shortageReportTable', {
        searching: true,
        sorting: true,
        pageSize: 100
    });
    
    // Initialize selection tracking
    updateSelectedCount();
    
    // Auto-refresh every 10 minutes
    setInterval(function() {
        window.location.reload();
    }, 600000);
});
</script>

<?php require_once '../../../includes/footer.php'; ?>