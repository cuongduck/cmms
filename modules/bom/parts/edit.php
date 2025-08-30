<?php
/**
 * Parts Edit Page
 * /modules/bom/parts/edit.php
 * Trang chỉnh sửa linh kiện
 */

// Check permission
requirePermission('bom', 'edit');

$partId = intval($_GET['id'] ?? 0);
if (!$partId) {
    header('Location: index.php');
    exit;
}

require_once '../../../includes/header.php';
require_once '../config.php';

// Get part details
$sql = "SELECT p.*, 
               COALESCE(oh.Onhand, 0) as stock_quantity,
               COALESCE(oh.UOM, p.unit) as stock_unit,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                   WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                   ELSE 'OK'
               END as stock_status
        FROM parts p
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE p.id = ?";

$part = $db->fetch($sql, [$partId]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chỉnh sửa: ' . $part['part_name'];
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => 'index.php'],
    ['title' => $part['part_name'], 'url' => 'view.php?id=' . $partId],
    ['title' => 'Chỉnh sửa', 'url' => '']
];

// Get suppliers for this part
$suppliers = $db->fetchAll(
    "SELECT * FROM part_suppliers WHERE part_id = ? ORDER BY is_preferred DESC, supplier_name",
    [$partId]
);

// Get categories and units
$categories = $bomConfig['part_categories'];
$units = $bomConfig['units'];

// Get recent suppliers for suggestions
$recentSuppliers = $db->fetchAll(
    "SELECT DISTINCT supplier_code, supplier_name 
     FROM parts 
     WHERE supplier_code IS NOT NULL AND supplier_name IS NOT NULL 
     ORDER BY updated_at DESC 
     LIMIT 20"
);
?>

<form id="partsForm" class="needs-validation" novalidate>
    <input type="hidden" name="part_id" value="<?php echo $partId; ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Part Information -->
            <div class="bom-form-container">
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-cube"></i>
                        Thông tin linh kiện
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="part_code" class="form-label">
                                Mã linh kiện <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="part_code" name="part_code" class="form-control" required
                                   value="<?php echo htmlspecialchars($part['part_code']); ?>"
                                   style="text-transform: uppercase;">
                            <div class="invalid-feedback">
                                Vui lòng nhập mã linh kiện
                            </div>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="part_name" class="form-label">
                                Tên linh kiện <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="part_name" name="part_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($part['part_name']); ?>">
                            <div class="invalid-feedback">
                                Vui lòng nhập tên linh kiện
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Danh mục</label>
                            <select id="category" name="category" class="form-select">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $key => $name): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo ($part['category'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Đơn vị tính</label>
                            <select id="unit" name="unit" class="form-select">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit; ?>" 
                                            <?php echo ($part['unit'] === $unit) ? 'selected' : ''; ?>>
                                        <?php echo $unit; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit_price" class="form-label">Đơn giá (VNĐ)</label>
                            <input type="number" id="unit_price" name="unit_price" class="form-control" 
                                   min="0" step="0.01" value="<?php echo $part['unit_price']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($part['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Thông số kỹ thuật</label>
                        <textarea id="specifications" name="specifications" class="form-control" rows="2"><?php echo htmlspecialchars($part['specifications']); ?></textarea>
                    </div>
                </div>

                <!-- Stock Management -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-warehouse"></i>
                        Quản lý tồn kho
                    </h5>
                    
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Tồn kho hiện tại:</strong>
                                <span class="fs-5 ms-2"><?php echo number_format($part['stock_quantity'], 2); ?> <?php echo $part['stock_unit']; ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Trạng thái:</strong>
                                <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?> ms-2">
                                    <?php echo getStockStatusText($part['stock_status']); ?>
                                </span>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Dữ liệu từ hệ thống ERP</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="min_stock" class="form-label">Mức tồn tối thiểu</label>
                            <input type="number" id="min_stock" name="min_stock" class="form-control" 
                                   min="0" step="0.1" value="<?php echo $part['min_stock']; ?>">
                            <div class="form-text">Cảnh báo khi tồn kho dưới mức này</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="0.1" value="<?php echo $part['max_stock']; ?>">
                            <div class="form-text">Mức tồn kho lý tưởng</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="lead_time" class="form-label">Lead time (ngày)</label>
                            <input type="number" id="lead_time" name="lead_time" class="form-control" 
                                   min="0" value="<?php echo $part['lead_time']; ?>">
                            <div class="form-text">Thời gian giao hàng từ nhà cung cấp</div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Information -->
                <div class="form-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="form-section-title mb-0">
                            <i class="fas fa-truck"></i>
                            Thông tin nhà cung cấp
                        </h5>
                        <button type="button" id="addSupplier" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus"></i> Thêm NCC
                        </button>
                    </div>
                    
                    <!-- Main Supplier -->
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2">
                            <label for="supplier_code" class="form-label">Mã NCC chính</label>
                            <input type="text" id="supplier_code" name="supplier_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['supplier_code']); ?>"
                                   list="supplier-codes">
                            <datalist id="supplier-codes">
                                <?php foreach ($recentSuppliers as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['supplier_code']); ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="col-md-4 mb-2">
                            <label for="supplier_name" class="form-label">Tên nhà cung cấp</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['supplier_name']); ?>"
                                   list="supplier-names">
                            <datalist id="supplier-names">
                                <?php foreach ($recentSuppliers as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" 
                                            data-code="<?php echo htmlspecialchars($supplier['supplier_code']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="col-md-3 mb-2">
                            <label for="manufacturer" class="form-label">Nhà sản xuất</label>
                            <input type="text" id="manufacturer" name="manufacturer" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['manufacturer']); ?>">
                        </div>
                        
                        <div class="col-md-2 mb-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-text">Nhà cung cấp chính</div>
                        </div>
                    </div>
                    
                    <!-- Additional Suppliers -->
                    <div id="suppliersContainer">
                        <?php foreach ($suppliers as $index => $supplier): ?>
                        <div class="supplier-row row mb-2">
                            <div class="col-md-3">
                                <input type="text" name="suppliers[<?php echo $index; ?>][supplier_code]" 
                                       class="form-control form-control-sm" placeholder="Mã NCC"
                                       value="<?php echo htmlspecialchars($supplier['supplier_code']); ?>">
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="suppliers[<?php echo $index; ?>][supplier_name]" 
                                       class="form-control form-control-sm" placeholder="Tên NCC"
                                       value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="suppliers[<?php echo $index; ?>][unit_price]" 
                                       class="form-control form-control-sm" placeholder="Giá" step="0.01"
                                       value="<?php echo $supplier['unit_price']; ?>">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           name="suppliers[<?php echo $index; ?>][is_preferred]"
                                           value="1" <?php echo $supplier['is_preferred'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Ưu tiên</label>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-supplier">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Thông tin bổ sung
                    </h5>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($part['notes']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Action Panel -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-save me-2"></i>
                        Cập nhật linh kiện
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Cập nhật
                        </button>
                        <a href="view.php?id=<?php echo $partId; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-eye me-2"></i>Xem chi tiết
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>Danh sách
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Usage Information -->
            <?php
            // Get usage information
            $usage = $db->fetchAll(
                "SELECT mb.bom_name, mb.bom_code, bi.quantity, bi.unit, bi.priority,
                        mt.name as machine_type_name
                 FROM bom_items bi
                 JOIN machine_bom mb ON bi.bom_id = mb.id
                 JOIN machine_types mt ON mb.machine_type_id = mt.id
                 WHERE bi.part_id = ?
                 ORDER BY mb.bom_name",
                [$partId]
            );
            ?>
            
            <?php if (!empty($usage)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Sử dụng trong BOM (<?php echo count($usage); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($usage as $use): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="part-code"><?php echo htmlspecialchars($use['bom_code']); ?></span>
                                    <small class="d-block"><?php echo htmlspecialchars($use['bom_name']); ?></small>
                                    <small class="text-muted"><?php echo htmlspecialchars($use['machine_type_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo number_format($use['quantity'], 2); ?></strong>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($use['unit']); ?></small>
                                    <span class="priority-badge priority-<?php echo $use['priority']; ?>">
                                        <?php echo $bomConfig['priorities'][$use['priority']]['name'] ?? $use['priority']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Change History -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Lịch sử thay đổi
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get change history from audit trails
                    $history = $db->fetchAll(
                        "SELECT at.*, u.full_name as user_name 
                         FROM audit_trails at 
                         LEFT JOIN users u ON at.user_id = u.id 
                         WHERE at.table_name = 'parts' AND at.record_id = ? 
                         ORDER BY at.created_at DESC 
                         LIMIT 5", 
                        [$partId]
                    );
                    ?>
                    
                    <?php if (empty($history)): ?>
                        <div class="text-center text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Chưa có lịch sử thay đổi
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($history as $entry): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <strong><?php echo ucfirst($entry['action']); ?></strong>
                                            <small class="text-muted">
                                                <?php echo formatDateTime($entry['created_at']); ?>
                                            </small>
                                        </div>
                                        <div class="timeline-body small">
                                            Bởi: <?php echo htmlspecialchars($entry['user_name'] ?? 'System'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stock Transactions -->
            <?php
            // Get recent transactions
            $transactions = $db->fetchAll(
                "SELECT t.* FROM transaction t 
                 JOIN part_inventory_mapping pim ON t.ItemCode = pim.item_code
                 WHERE pim.part_id = ?
                 ORDER BY t.TransactionDate DESC 
                 LIMIT 5",
                [$partId]
            );
            ?>
            
            <?php if (!empty($transactions)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Giao dịch gần đây
                    </h6>
                </div>
                <div class="card-body">
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($transactions as $trans): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="badge <?php echo ($trans['TransactionType'] === 'Issue') ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $trans['TransactionType']; ?>
                                    </span>
                                    <small class="d-block mt-1"><?php echo formatDateTime($trans['TransactionDate']); ?></small>
                                    <small class="text-muted"><?php echo htmlspecialchars($trans['Reason']); ?></small>
                                </div>
                                <div class="text-end">
                                    <strong class="<?php echo ($trans['TransactionType'] === 'Issue') ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo ($trans['TransactionType'] === 'Issue') ? '-' : '+'; ?>
                                        <?php echo number_format($trans['TransactedQty'], 2); ?>
                                    </strong>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($trans['UOM']); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-2">
                        <a href="/modules/inventory/transactions.php?part_id=<?php echo $partId; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>Xem tất cả
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// Track changes for confirmation
let hasChanges = false;
const form = document.getElementById('partsForm');

form.addEventListener('change', () => {
    hasChanges = true;
});

form.addEventListener('input', () => {
    hasChanges = true;
});

// Warn before leaving if there are unsaved changes
window.addEventListener('beforeunload', (e) => {
    if (hasChanges) {
        e.preventDefault();
        e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc muốn rời khỏi trang?';
    }
});

// Clear warning when form is submitted
form.addEventListener('submit', () => {
    hasChanges = false;
});

// Supplier autocomplete
document.getElementById('supplier_code').addEventListener('input', function(e) {
    const code = e.target.value;
    const supplier = <?php echo json_encode($recentSuppliers); ?>.find(s => s.supplier_code === code);
    if (supplier) {
        document.getElementById('supplier_name').value = supplier.supplier_name;
    }
});

document.getElementById('supplier_name').addEventListener('input', function(e) {
    const name = e.target.value;
    const supplier = <?php echo json_encode($recentSuppliers); ?>.find(s => s.supplier_name === name);
    if (supplier) {
        document.getElementById('supplier_code').value = supplier.supplier_code;
    }
});

// Initialize supplier row count
let supplierRowCount = <?php echo count($suppliers); ?>;

// Override add supplier function
CMMS.Parts.addSupplierRow = function() {
    const container = document.getElementById('suppliersContainer');
    if (!container) return;
    
    const supplierRow = document.createElement('div');
    supplierRow.className = 'supplier-row row mb-2';
    supplierRow.innerHTML = `
        <div class="col-md-3">
            <input type="text" name="suppliers[${supplierRowCount}][supplier_code]" 
                   class="form-control form-control-sm" placeholder="Mã NCC">
        </div>
        <div class="col-md-4">
            <input type="text" name="suppliers[${supplierRowCount}][supplier_name]" 
                   class="form-control form-control-sm" placeholder="Tên NCC">
        </div>
        <div class="col-md-2">
            <input type="number" name="suppliers[${supplierRowCount}][unit_price]" 
                   class="form-control form-control-sm" placeholder="Giá" step="0.01">
        </div>
        <div class="col-md-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" 
                       name="suppliers[${supplierRowCount}][is_preferred]" value="1">
                <label class="form-check-label">Ưu tiên</label>
            </div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm remove-supplier">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(supplierRow);
    supplierRowCount++;
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Focus on first input
    document.getElementById('part_code').focus();
    
    // Initialize supplier management
    CMMS.Parts.initializeSuppliers();
});
</script>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline-item {
    position: relative;
    padding-bottom: 15px;
    border-left: 2px solid #e2e8f0;
}

.timeline-item:last-child {
    border-left: none;
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -6px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-content {
    margin-left: 15px;
    margin-bottom: 10px;
}

.timeline-header {
    display: flex;
    justify-content-between;
    align-items: center;
    margin-bottom: 5px;
}

.timeline-body {
    color: #64748b;
}
</style>

<?php require_once '../../../includes/footer.php'; ?>