<?php
/**
 * Parts Add Page
 * /modules/bom/parts/add.php
 * Trang thêm linh kiện mới
 */

$pageTitle = 'Thêm linh kiện mới';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';
require_once '../config.php';

// Check permission
requirePermission('bom', 'create');

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => 'index.php'],
    ['title' => 'Thêm linh kiện', 'url' => '']
];

require_once '../../../includes/header.php';

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
?>

<form id="partsForm" class="needs-validation" novalidate>
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
                                   placeholder="VD: VKH0001" style="text-transform: uppercase;">
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
                                   placeholder="Nhập tên linh kiện...">
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
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Đơn vị tính</label>
                            <select id="unit" name="unit" class="form-select">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit; ?>" <?php echo ($unit === 'Cái') ? 'selected' : ''; ?>>
                                        <?php echo $unit; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit_price" class="form-label">Đơn giá (VNĐ)</label>
                            <input type="number" id="unit_price" name="unit_price" class="form-control" 
                                   min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Mô tả chi tiết về linh kiện..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Thông số kỹ thuật</label>
                        <textarea id="specifications" name="specifications" class="form-control" rows="2"
                                  placeholder="Thông số kỹ thuật, kích thước, vật liệu..."></textarea>
                    </div>
                </div>

                <!-- Stock Management -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-warehouse"></i>
                        Quản lý tồn kho
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="min_stock" class="form-label">Mức tồn tối thiểu</label>
                            <input type="number" id="min_stock" name="min_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0">
                            <div class="form-text">Cảnh báo khi tồn kho dưới mức này</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0">
                            <div class="form-text">Mức tồn kho lý tưởng</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="lead_time" class="form-label">Lead time (ngày)</label>
                            <input type="number" id="lead_time" name="lead_time" class="form-control" 
                                   min="0" placeholder="0">
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
                                   placeholder="Mã NCC" list="supplier-codes">
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
                                   placeholder="Tên nhà cung cấp" list="supplier-names">
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
                                   placeholder="Tên nhà sản xuất">
                        </div>
                        
                        <div class="col-md-2 mb-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="form-text">Nhà cung cấp chính</div>
                        </div>
                    </div>
                    
                    <!-- Additional Suppliers -->
                    <div id="suppliersContainer">
                        <!-- Additional supplier rows will be added here -->
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
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                  placeholder="Ghi chú thêm về linh kiện..."></textarea>
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
                        Lưu linh kiện
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu linh kiện
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="saveAndAddNew()">
                            <i class="fas fa-plus me-2"></i>Lưu và thêm mới
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Hủy bỏ
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Reference -->
            <div class="card mb-3">
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
// Auto-update preview
document.addEventListener('input', function(e) {
    if (e.target.closest('#partsForm')) {
        updatePreview();
    }
});

// Update preview function
function updatePreview() {
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
                    <div class="fw-bold cost-display">${CMMS.Parts.formatCurrency(unitPrice || 0)}</div>
                </div>
            </div>
        </div>
    `;
}

// Quick set functions
function setCategory(category) {
    document.getElementById('category').value = category;
    updatePreview();
}

function setUnit(unit) {
    document.getElementById('unit').value = unit;
    updatePreview();
}

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

// Save and add new function
function saveAndAddNew() {
    const form = document.getElementById('partsForm');
    
    // Add a flag to indicate save and add new
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'save_and_new';
    input.value = '1';
    form.appendChild(input);
    
    // Submit form
    CMMS.Parts.savePart();
}

// Override save function to handle save and add new
CMMS.Parts.savePart = function() {
    const form = document.getElementById('partsForm');
    const formData = new FormData(form);
    
    // Validate form
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Check for required fields
    const partCode = formData.get('part_code');
    const partName = formData.get('part_name');
    
    if (!partCode || !partName) {
        CMMS.showToast('Vui lòng nhập đầy đủ mã và tên linh kiện', 'error');
        return;
    }
    
    // Add action
    formData.append('action', 'save');
    
    const saveAndNew = formData.has('save_and_new');
    
    CMMS.ajax({
        url: '/modules/bom/api/parts.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                
                if (saveAndNew) {
                    // Reset form for new entry
                    form.reset();
                    form.classList.remove('was-validated');
                    updatePreview();
                    
                    // Focus on part code
                    document.getElementById('part_code').focus();
                } else {
                    // Redirect to view page
                    setTimeout(() => {
                        window.location.href = 'view.php?id=' + data.part_id;
                    }, 1500);
                }
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
};

// Initial preview update
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    
    // Focus on first input
    document.getElementById('part_code').focus();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>