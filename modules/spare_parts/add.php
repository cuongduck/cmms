<?php
/**
 * Spare Parts Add Page
 * /modules/spare_parts/add.php
 */

$pageTitle = 'Thêm Spare Part';
$currentModule = 'spare_parts';
$moduleCSS = 'bom';
$moduleJS = 'spare-parts';
require_once 'config.php';

// Check permission
requirePermission('spare_parts', 'create');

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => 'Thêm spare part', 'url' => '']
];

require_once '../../includes/header.php';

// Get categories, units, users for dropdowns
$categories = $sparePartsConfig['categories'];
$units = $sparePartsConfig['units'];
$users = $db->fetchAll("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
?>

<form id="sparePartsForm" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="bom-form-container">
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-cube"></i>
                        Thông tin cơ bản
                    </h5>
                    
<!-- Thay thế section category trong add.php -->
<div class="row">
    <div class="col-md-4 mb-3">
        <label for="item_code" class="form-label">
            Mã vật tư <span class="text-danger">*</span>
        </label>
        <input type="text" id="item_code" name="item_code" class="form-control" required
               placeholder="VD: SP001" style="text-transform: uppercase;">
        <div class="invalid-feedback">
            Vui lòng nhập mã vật tư
        </div>
        <div class="form-text">
            Mã phải khớp với ItemCode trong bảng onhand
        </div>
    </div>
    
    <div class="col-md-8 mb-3">
        <label for="item_name" class="form-label">
            Tên vật tư <span class="text-danger">*</span>
        </label>
        <input type="text" id="item_name" name="item_name" class="form-control" required
               placeholder="Nhập tên vật tư...">
        <div class="invalid-feedback">
            Vui lòng nhập tên vật tư
        </div>
    </div>
</div>

<!-- Hiển thị category được phân loại tự động -->
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Danh mục (tự động phân loại)</label>
        <div class="form-control bg-light" id="auto_category_display" style="min-height: 38px;">
            <span class="text-muted">Nhập tên vật tư để tự động phân loại</span>
        </div>
        <input type="hidden" id="category" name="category" value="">
        <div id="category_confidence" class="form-text"></div>
    </div>
    
    <div class="col-md-3 mb-3">
        <label for="unit" class="form-label">Đơn vị tính</label>
        <select id="unit" name="unit" class="form-select">
            <?php foreach ($units as $unit): ?>
                <option value="<?php echo $unit; ?>"><?php echo $unit; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-3 mb-3">
        <label for="standard_cost" class="form-label">Giá chuẩn (VNĐ)</label>
        <input type="number" id="standard_cost" name="standard_cost" class="form-control" 
               min="0" step="0.01" placeholder="0">
    </div>
</div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Mô tả chi tiết về vật tư..."></textarea>
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
                            <label for="min_stock" class="form-label">Mức tồn tối thiểu <span class="text-danger">*</span></label>
                            <input type="number" id="min_stock" name="min_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập mức tồn tối thiểu
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="0.1" placeholder="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="reorder_point" class="form-label">Điểm đặt hàng lại</label>
                            <input type="number" id="reorder_point" name="reorder_point" class="form-control" 
                                   min="0" step="0.1" placeholder="0">
                            <div class="form-text">Tự động = min_stock nếu để trống</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="storage_location" class="form-label">Vị trí lưu kho</label>
                            <input type="text" id="storage_location" name="storage_location" class="form-control" 
                                   placeholder="VD: Kho A - Kệ 1">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="lead_time_days" class="form-label">Lead time (ngày)</label>
                            <input type="number" id="lead_time_days" name="lead_time_days" class="form-control" 
                                   min="0" placeholder="0">
                        </div>
                        
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_critical" name="is_critical" value="1">
                                <label class="form-check-label" for="is_critical">
                                    <strong>Vật tư quan trọng</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Management & Supplier -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-users"></i>
                        Quản lý & Nhà cung cấp
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manager_user_id" class="form-label">Người quản lý chính</label>
                            <select id="manager_user_id" name="manager_user_id" class="form-select">
                                <option value="">-- Chọn người quản lý --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="backup_manager_user_id" class="form-label">Người quản lý dự phòng</label>
                            <select id="backup_manager_user_id" name="backup_manager_user_id" class="form-select">
                                <option value="">-- Chọn người dự phòng --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="supplier_code" class="form-label">Mã nhà cung cấp</label>
                            <input type="text" id="supplier_code" name="supplier_code" class="form-control" 
                                   placeholder="Mã NCC">
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="supplier_name" class="form-label">Tên nhà cung cấp</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                                   placeholder="Tên NCC">
                        </div>
                    </div>
                </div>
                
                <!-- Notes -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-sticky-note"></i>
                        Ghi chú
                    </h5>
                    
                    <div class="mb-3">
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                  placeholder="Ghi chú thêm về vật tư..."></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="bom-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Lưu spare part
                </button>
                <button type="button" onclick="saveAndAddNew()" class="btn btn-outline-primary">
                    <i class="fas fa-plus me-2"></i>Lưu và thêm mới
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Hủy
                </a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Preview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-eye me-2"></i>
                        Xem trước
                    </h6>
                </div>
                <div class="card-body">
                    <div id="sparePartPreview">
                        <div class="text-muted text-center">
                            <i class="fas fa-cube fa-3x mb-2"></i>
                            <p>Nhập thông tin để xem trước</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Check -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-warehouse me-2"></i>
                        Kiểm tra tồn kho
                    </h6>
                </div>
                <div class="card-body">
                    <div id="stockCheckResult">
                        <div class="text-muted text-center">
                            <i class="fas fa-search fa-2x mb-2"></i>
                            <p>Nhập mã vật tư để kiểm tra tồn kho</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-fill reorder point
document.getElementById('min_stock').addEventListener('input', function() {
    const reorderPoint = document.getElementById('reorder_point');
    if (!reorderPoint.value) {
        reorderPoint.value = this.value;
    }
});

// Check stock when item_code changes
document.getElementById('item_code').addEventListener('blur', function() {
    checkStock(this.value);
});

// Update preview
document.addEventListener('input', updatePreview);

function updatePreview() {
    const itemCode = document.getElementById('item_code').value;
    const itemName = document.getElementById('item_name').value;
    const category = document.getElementById('category').value;
    const minStock = document.getElementById('min_stock').value;
    const maxStock = document.getElementById('max_stock').value;
    const isCritical = document.getElementById('is_critical').checked;
    
    const preview = document.getElementById('sparePartPreview');
    
    if (!itemCode && !itemName) {
        preview.innerHTML = `
            <div class="text-muted text-center">
                <i class="fas fa-cube fa-3x mb-2"></i>
                <p>Nhập thông tin để xem trước</p>
            </div>
        `;
        return;
    }
    
    preview.innerHTML = `
        <div class="d-flex flex-column">
            <div class="d-flex align-items-center gap-2">
                <span class="part-code">${itemCode || '[Mã vật tư]'}</span>
                ${isCritical ? '<span class="badge bg-warning">Critical</span>' : ''}
            </div>
            <strong>${itemName || '[Tên vật tư]'}</strong>
            ${category ? `<small class="text-muted">${category}</small>` : ''}
        </div>
        <hr>
        <div class="row text-center">
            <div class="col-6">
                <div class="border rounded p-2">
                    <small class="text-muted">Min Stock</small>
                    <div class="fw-bold">${minStock || '0'}</div>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-2">
                    <small class="text-muted">Max Stock</small>
                    <div class="fw-bold">${maxStock || '0'}</div>
                </div>
            </div>
        </div>
    `;
}

function checkStock(itemCode) {
    if (!itemCode) {
        document.getElementById('stockCheckResult').innerHTML = `
            <div class="text-muted text-center">
                <i class="fas fa-search fa-2x mb-2"></i>
                <p>Nhập mã vật tư để kiểm tra tồn kho</p>
            </div>
        `;
        return;
    }
    
    CMMS.ajax({
        url: 'api/spare_parts.php?action=check_stock&item_code=' + encodeURIComponent(itemCode),
        method: 'GET',
        success: (data) => {
            const result = document.getElementById('stockCheckResult');
            if (data.success && data.data) {
                const stock = data.data;
                result.innerHTML = `
                    <div class="alert alert-info">
                        <h6>Tồn kho hiện tại</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <strong>${stock.Onhand || 0}</strong>
                                <small class="d-block">${stock.UOM || ''}</small>
                            </div>
                            <div class="col-6">
                                <strong>${formatVND(stock.OH_Value || 0)}</strong>
                                <small class="d-block">Giá trị</small>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                result.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Không tìm thấy mã vật tư trong kho
                    </div>
                `;
            }
        }
    });
}

function saveAndAddNew() {
    const form = document.getElementById('sparePartsForm');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'save_and_new';
    input.value = '1';
    form.appendChild(input);
    
    submitForm();
}

function submitForm() {
    const form = document.getElementById('sparePartsForm');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    CMMS.ajax({
        url: 'api/spare_parts.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                if (formData.has('save_and_new')) {
                    form.reset();
                    form.classList.remove('was-validated');
                    updatePreview();
                    document.getElementById('item_code').focus();
                } else {
                    setTimeout(() => {
                        window.location.href = 'view.php?id=' + data.data.id;
                    }, 1500);
                }
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

// Form submit handler
document.getElementById('sparePartsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm();
});

function formatVND(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        minimumFractionDigits: 0
    }).format(amount);
}

// Event listener cho auto-categorization khi nhập tên vật tư
document.getElementById('item_name').addEventListener('input', CMMS.utils.debounce(function() {
    autoDetectAndDisplayCategory(this.value);
}, 500));

// Function chính để detect và hiển thị category
function autoDetectAndDisplayCategory(itemName) {
    const categoryDisplay = document.getElementById('auto_category_display');
    const categoryInput = document.getElementById('category');
    const confidenceDiv = document.getElementById('category_confidence');
    
    // Nếu tên quá ngắn hoặc rỗng
    if (!itemName || itemName.length < 3) {
        categoryDisplay.innerHTML = '<span class="text-muted">Nhập tên vật tư để tự động phân loại</span>';
        categoryInput.value = '';
        confidenceDiv.innerHTML = '';
        return;
    }
    
    // Hiển thị loading state
    categoryDisplay.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Đang phân loại...</span>';
    confidenceDiv.innerHTML = '';
    
    // Gọi API để detect category
    CMMS.ajax({
        url: 'api/spare_parts.php?action=detect_category&item_name=' + encodeURIComponent(itemName),
        method: 'GET',
        success: (data) => {
            if (data.success && data.data.category) {
                displayDetectedCategory(data.data);
            } else {
                displayDefaultCategory();
            }
        },
        error: () => {
            displayErrorCategory();
        }
    });
}

// Function hiển thị category đã detect được
function displayDetectedCategory(categoryData) {
    const category = categoryData.category;
    const confidence = categoryData.confidence;
    const suggestions = categoryData.suggestions || [];
    
    const categoryDisplay = document.getElementById('auto_category_display');
    const categoryInput = document.getElementById('category');
    const confidenceDiv = document.getElementById('category_confidence');
    
    // Cập nhật hidden input
    categoryInput.value = category;
    
    // Xác định màu sắc dựa trên confidence
    let confidenceClass = 'success';
    let confidenceIcon = 'check-circle';
    
    if (confidence < 70) {
        confidenceClass = 'warning';
        confidenceIcon = 'exclamation-triangle';
    }
    if (confidence < 40) {
        confidenceClass = 'danger';
        confidenceIcon = 'exclamation-circle';
    }
    
    // Hiển thị category với confidence
    categoryDisplay.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-${confidenceClass} fs-6">
                <i class="fas fa-${confidenceIcon} me-1"></i>${category}
            </span>
            <small class="text-${confidenceClass} fw-bold">${confidence}% tin cậy</small>
        </div>
    `;
    
    // Hiển thị thông tin chi tiết
    let confidenceInfo = `<i class="fas fa-info-circle me-1"></i>Độ tin cậy: <strong>${confidence}%</strong>`;
    
    if (suggestions.length > 1) {
        confidenceInfo += ` | Gợi ý khác: <em>${suggestions.slice(1, 3).join(', ')}</em>`;
    }
    
    // Thêm gợi ý cải thiện nếu confidence thấp
    if (confidence < 50) {
        confidenceInfo += ' | <span class="text-warning">Nên kiểm tra lại hoặc bổ sung từ khóa</span>';
    }
    
    confidenceDiv.innerHTML = confidenceInfo;
    
    // Trigger preview update nếu có
    if (typeof updatePreview === 'function') {
        updatePreview();
    }
    
    // Hiệu ứng nhấp nháy nhẹ để thu hút chú ý
    categoryDisplay.style.animation = 'pulse 0.5s ease-in-out';
    setTimeout(() => {
        categoryDisplay.style.animation = '';
    }, 500);
}

// Function hiển thị category mặc định
function displayDefaultCategory() {
    const categoryDisplay = document.getElementById('auto_category_display');
    const categoryInput = document.getElementById('category');
    const confidenceDiv = document.getElementById('category_confidence');
    
    categoryDisplay.innerHTML = '<span class="badge bg-secondary">Vật tư khác</span>';
    categoryInput.value = 'Vật tư khác';
    confidenceDiv.innerHTML = '<i class="fas fa-info-circle me-1"></i>Không tìm thấy danh mục phù hợp';
}

// Function hiển thị lỗi
function displayErrorCategory() {
    const categoryDisplay = document.getElementById('auto_category_display');
    const confidenceDiv = document.getElementById('category_confidence');
    
    categoryDisplay.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Lỗi phân loại</span>';
    confidenceDiv.innerHTML = '<span class="text-danger">Có lỗi xảy ra khi phân loại. Vui lòng thử lại.</span>';
}

// Cập nhật function updatePreview để sử dụng auto category
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        minimumFractionDigits: 0
    }).format(amount);
}

// CSS animation cho pulse effect
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);

// Focus on first input
document.getElementById('item_code').focus();
</script>


<?php require_once '../../includes/footer.php'; ?>