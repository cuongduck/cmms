<?php
/**
 * Parts Edit Page - Rewritten Version without Mapping
 * /modules/bom/parts/edit.php
 * Trang chỉnh sửa linh kiện
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../config.php';

// Check permission
requirePermission('bom', 'edit');

// Get part ID from URL
$partId = intval($_GET['id'] ?? 0);
if (!$partId) {
    header('Location: index.php');
    exit;
}

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
        LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
        WHERE p.id = ?";

$part = $db->fetch($sql, [$partId]);
if (!$part) {
    header('Location: index.php');
    exit;
}

// Page metadata
$pageTitle = 'Chỉnh sửa: ' . htmlspecialchars($part['part_name']);
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => 'index.php'],
    ['title' => htmlspecialchars($part['part_name']), 'url' => 'view.php?id=' . $partId],
    ['title' => 'Chỉnh sửa', 'url' => '']
];

// Get categories and units for dropdowns
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

// Get suppliers for this part
$suppliers = $db->fetchAll(
    "SELECT * FROM part_suppliers WHERE part_id = ? ORDER BY is_preferred DESC, supplier_name",
    [$partId]
);

require_once '../../../includes/header.php';
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
                                   placeholder="VD: VKH0001" style="text-transform: uppercase;"
                                   value="<?php echo htmlspecialchars($part['part_code']); ?>">
                            <div class="invalid-feedback">
                                Vui lòng nhập mã linh kiện
                            </div>
                            <div class="form-text">
                                Mã linh kiện phải duy nhất trong hệ thống
                            </div>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="part_name" class="form-label">
                                Tên linh kiện <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="part_name" name="part_name" class="form-control" required
                                   placeholder="Nhập tên linh kiện..."
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
                                   min="0" step="0.01" placeholder="0"
                                   value="<?php echo $part['unit_price']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Mô tả chi tiết về linh kiện..."><?php echo htmlspecialchars($part['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Thông số kỹ thuật</label>
                        <textarea id="specifications" name="specifications" class="form-control" rows="2"
                                  placeholder="Thông số kỹ thuật, kích thước, vật liệu..."><?php echo htmlspecialchars($part['specifications']); ?></textarea>
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
                                <span class="fs-5 ms-2"><?php echo number_format($part['stock_quantity'], 2); ?> <?php echo htmlspecialchars($part['stock_unit']); ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Trạng thái:</strong>
                                <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?> ms-2">
                                    <?php echo getStockStatusText($part['stock_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="min_stock" class="form-label">Mức tồn tối thiểu</label>
                            <input type="number" id="min_stock" name="min_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo $part['min_stock']; ?>">
                            <div class="form-text">Cảnh báo khi tồn kho dưới mức này</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo $part['max_stock']; ?>">
                            <div class="form-text">Mức tồn kho lý tưởng</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="lead_time" class="form-label">Lead time (ngày)</label>
                            <input type="number" id="lead_time" name="lead_time" class="form-control" 
                                   min="0" placeholder="0"
                                   value="<?php echo $part['lead_time']; ?>">
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
                    
                    <div id="suppliersContainer">
                        <?php if (!empty($suppliers)): ?>
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
                                                   name="suppliers[<?php echo $index; ?>][is_preferred]" value="1"
                                                   <?php echo $supplier['is_preferred'] ? 'checked' : ''; ?>>
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
                        <?php else: ?>
                            <div class="supplier-row row mb-2">
                                <div class="col-md-3">
                                    <input type="text" name="suppliers[0][supplier_code]" 
                                           class="form-control form-control-sm" placeholder="Mã NCC">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="suppliers[0][supplier_name]" 
                                           class="form-control form-control-sm" placeholder="Tên NCC">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="suppliers[0][unit_price]" 
                                           class="form-control form-control-sm" placeholder="Giá" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               name="suppliers[0][is_preferred]" value="1">
                                        <label class="form-check-label">Ưu tiên</label>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-supplier">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="bom-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                </button>
                <a href="view.php?id=<?php echo $partId; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Hủy
                </a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Quick Reference -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Tham khảo nhanh
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Mã linh kiện:</strong>
                        <ul class="list-unstyled small mt-1">
                            <li>VKH: Vật tư kho hàng</li>
                            <li>VHC: Vật tư hóa chất</li>
                            <li>VCK: Vật tư cơ khí</li>
                            <li>VDT: Vật tư điện tử</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Danh mục phổ biến:</strong>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    onclick="setCategory('Cơ khí')">Cơ khí</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    onclick="setCategory('Điện')">Điện</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    onclick="setCategory('Hóa chất')">Hóa chất</button>
                        </div>
                    </div>
                    
                    <div>
                        <strong>Đơn vị thông dụng:</strong>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <button type="button" class="btn btn-outline-info btn-sm" 
                                    onclick="setUnit('Cái')">Cái</button>
                            <button type="button" class="btn btn-outline-info btn-sm" 
                                    onclick="setUnit('Bộ')">Bộ</button>
                            <button type="button" class="btn btn-outline-info btn-sm" 
                                    onclick="setUnit('Kg')">Kg</button>
                            <button type="button" class="btn btn-outline-info btn-sm" 
                                    onclick="setUnit('Lít')">Lít</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-eye me-2"></i>
                        Xem trước
                    </h6>
                </div>
                <div class="card-body">
                    <div id="partPreview">
                        <div class="text-muted text-center">
                            <i class="fas fa-cube fa-3x mb-2"></i>
                            <p>Nhập thông tin để xem trước linh kiện</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Wait for DOM and CMMS to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait for CMMS object to be available
    function waitForCMMS() {
        if (typeof CMMS !== 'undefined' && CMMS.Parts) {
            initializeEditPartsPage();
        } else {
            setTimeout(waitForCMMS, 100);
        }
    }
    waitForCMMS();
});

function initializeEditPartsPage() {
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
    
    // Auto-update preview
    document.addEventListener('input', function(e) {
        if (e.target.closest('#partsForm')) {
            updatePreview();
        }
    });

    // Update preview function
    window.updatePreview = function() {
        const partCode = document.getElementById('part_code').value;
        const partName = document.getElementById('part_name').value;
        const category = document.getElementById('category').value;
        const unit = document.getElementById('unit').value;
        const unitPrice = document.getElementById('unit_price').value;
        
        const preview = document.getElementById('partPreview');
        
        if (!partCode && !partName) {
            preview.innerHTML = `
                <div class="text-muted text-center">
                    <i class="fas fa-cube fa-3x mb-2"></i>
                    <p>Nhập thông tin để xem trước linh kiện</p>
                </div>
            `;
            return;
        }
        
        preview.innerHTML = `
            <div class="d-flex flex-column">
                <span class="part-code">${partCode || '[Mã linh kiện]'}</span>
                <strong>${partName || '[Tên linh kiện]'}</strong>
                ${category ? `<small class="text-muted">${category}</small>` : ''}
            </div>
            <hr>
            <div class="row text-center">
                <div class="col-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Đơn vị</small>
                        <div class="fw-bold">${unit}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <small class="text-muted">Đơn giá</small>
                        <div class="fw-bold cost-display">${formatCurrency(unitPrice || 0)}</div>
                    </div>
                </div>
            </div>
        `;
    };

    // Quick set functions
    window.setCategory = function(category) {
        document.getElementById('category').value = category;
        updatePreview();
    };

    window.setUnit = function(unit) {
        document.getElementById('unit').value = unit;
        updatePreview();
    };

    // Supplier autocomplete
    document.getElementById('supplier_code').addEventListener('input', function(e) {
        const code = e.target.value;
        const suppliers = <?php echo json_encode($recentSuppliers); ?>;
        const supplier = suppliers.find(s => s.supplier_code === code);
        if (supplier) {
            document.getElementById('supplier_name').value = supplier.supplier_name;
        }
    });

    document.getElementById('supplier_name').addEventListener('input', function(e) {
        const name = e.target.value;
        const suppliers = <?php echo json_encode($recentSuppliers); ?>;
        const supplier = suppliers.find(s => s.supplier_name === name);
        if (supplier) {
            document.getElementById('supplier_code').value = supplier.supplier_code;
        }
    });

    // Format currency helper
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(amount);
    }

    // Initial preview update
    updatePreview();
    
    // Focus on first input
    document.getElementById('part_code').focus();
}
</script>

<?php require_once '../../../includes/footer.php'; ?>