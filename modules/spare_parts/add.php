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
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="item_code" class="form-label">
                                Mã vật tư <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="item_code" name="item_code" class="form-control" required
                                   placeholder="VD: SP001" style="text-transform: uppercase;" autocomplete="off">
                            <div class="invalid-feedback">
                                Vui lòng nhập mã vật tư
                            </div>
                            <div id="item_code_suggestions" class="list-group position-absolute" style="z-index: 1000; max-height: 200px; overflow-y: auto; display: none;"></div>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="item_name" class="form-label">
                                Tên vật tư <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="item_name" name="item_name" class="form-control" required readonly
                                   placeholder="Tên sẽ tự động load..." style="background-color: #e9ecef;">
                            <div class="invalid-feedback">
                                Vui lòng nhập tên vật tư
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Danh mục (tự động phân loại)</label>
                            <div class="form-control bg-light" id="auto_category_display" style="min-height: 38px;">
                                <span class="text-muted">Nhập mã vật tư để tự động phân loại</span>
                            </div>
                            <input type="hidden" id="category" name="category" value="">
                            <div id="category_confidence" class="form-text"></div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Đơn vị tính</label>
                            <input type="text" id="unit" name="unit" class="form-control" readonly style="background-color: #e9ecef;">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="standard_cost" class="form-label">Giá (VNĐ)</label>
                            <input type="number" id="standard_cost" name="standard_cost" class="form-control" 
                                   min="0" step="0.01" readonly style="background-color: #e9ecef;">
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
                                   min="0" step="1" placeholder="0" required>
                            <div class="invalid-feedback">
                                Vui lòng nhập mức tồn tối thiểu
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="1" placeholder="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="reorder_point" class="form-label">Điểm đặt hàng lại</label>
                            <input type="number" id="reorder_point" name="reorder_point" class="form-control" 
                                   min="0" step="1" placeholder="0">
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
        <div class="col-md-12 mb-3">
            <label for="manager_user_id" class="form-label">Người quản lý</label>
            <input type="text" class="form-control" readonly 
                   value="<?php echo htmlspecialchars(getCurrentUser()['full_name']); ?>" 
                   style="background-color: #e9ecef;">
            <input type="hidden" id="manager_user_id" name="manager_user_id" 
                   value="<?php echo getCurrentUser()['id']; ?>">
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="supplier_code" class="form-label">Mã nhà cung cấp</label>
            <input type="text" id="supplier_code" name="supplier_code" class="form-control" readonly
                   style="background-color: #e9ecef;">
        </div>
        
        <div class="col-md-8 mb-3">
            <label for="supplier_name" class="form-label">Tên nhà cung cấp</label>
            <input type="text" id="supplier_name" name="supplier_name" class="form-control" readonly
                   style="background-color: #e9ecef;">
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
                            <p>Nhập mã vật tư để xem trước</p>
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
// Auto-complete và load dữ liệu từ item_mmb
let searchTimeout;
document.getElementById('item_code').addEventListener('input', function() {
    const itemCode = this.value.trim().toUpperCase();
    this.value = itemCode;
    
    clearTimeout(searchTimeout);
    
    if (itemCode.length < 2) {
        document.getElementById('item_code_suggestions').style.display = 'none';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        searchItemMMB(itemCode);
    }, 300);
});

function searchItemMMB(itemCode) {
    CMMS.ajax({
        url: 'api/spare_parts.php?action=search_item_mmb&item_code=' + encodeURIComponent(itemCode),
        method: 'GET',
        success: (data) => {
            if (data.success && data.data && data.data.length > 0) {
                displaySuggestions(data.data);
            } else {
                document.getElementById('item_code_suggestions').style.display = 'none';
            }
        }
    });
}

function displaySuggestions(items) {
    const suggestionsDiv = document.getElementById('item_code_suggestions');
    suggestionsDiv.innerHTML = '';
    
    items.forEach(item => {
        const div = document.createElement('a');
        div.href = '#';
        div.className = 'list-group-item list-group-item-action';
        div.innerHTML = `
            <strong>${item.ITEM_CODE}</strong><br>
            <small>${item.ITEM_NAME}</small>
        `;
        div.onclick = (e) => {
            e.preventDefault();
            selectItem(item);
        };
        suggestionsDiv.appendChild(div);
    });
    
    suggestionsDiv.style.display = 'block';
}

function selectItem(item) {
    // Fill form fields
    document.getElementById('item_code').value = item.ITEM_CODE;
    document.getElementById('item_name').value = item.ITEM_NAME;
    document.getElementById('unit').value = item.UOM || 'Cái';
    document.getElementById('standard_cost').value = item.UNIT_PRICE || 0;
    document.getElementById('supplier_code').value = item.VENDOR_ID || '';
    document.getElementById('supplier_name').value = item.VENDOR_NAME || '';
    
    // Auto detect category
    autoDetectAndDisplayCategory(item.ITEM_NAME);
    
    // Check stock
    checkStock(item.ITEM_CODE);
    
    // Hide suggestions
    document.getElementById('item_code_suggestions').style.display = 'none';
    
    // Update preview
    updatePreview();
    
    // Focus on min_stock
    document.getElementById('min_stock').focus();
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#item_code') && !e.target.closest('#item_code_suggestions')) {
        document.getElementById('item_code_suggestions').style.display = 'none';
    }
});

// Auto-categorization khi nhập tên vật tư
function autoDetectAndDisplayCategory(itemName) {
    if (!itemName || itemName.length < 3) {
        document.getElementById('auto_category_display').innerHTML = '<span class="text-muted">Nhập tên vật tư để tự động phân loại</span>';
        return;
    }
    
    document.getElementById('auto_category_display').innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Đang phân loại...</span>';
    
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
            displayDefaultCategory();
        }
    });
}

function displayDetectedCategory(categoryData) {
    const category = categoryData.category;
    const confidence = categoryData.confidence;
    
    document.getElementById('category').value = category;
    
    let confidenceClass = confidence >= 70 ? 'success' : confidence >= 40 ? 'warning' : 'danger';
    
    document.getElementById('auto_category_display').innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-${confidenceClass} fs-6">${category}</span>
            <small class="text-${confidenceClass}">${confidence}% tin cậy</small>
        </div>
    `;
    
    document.getElementById('category_confidence').innerHTML = 
        `<i class="fas fa-info-circle me-1"></i>Độ tin cậy: <strong>${confidence}%</strong>`;
}

function displayDefaultCategory() {
    document.getElementById('auto_category_display').innerHTML = '<span class="badge bg-secondary">Vật tư khác</span>';
    document.getElementById('category').value = 'Vật tư khác';
}

// Update preview
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
                <p>Nhập mã vật tư để xem trước</p>
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
    if (!itemCode) return;
    
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

// Auto-fill reorder point
document.getElementById('min_stock').addEventListener('input', function() {
    const reorderPoint = document.getElementById('reorder_point');
    if (!reorderPoint.value) {
        reorderPoint.value = this.value;
    }
    updatePreview();
});

// Event listeners
document.addEventListener('input', updatePreview);

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

// Focus on first input
document.getElementById('item_code').focus();
</script>


<?php require_once '../../includes/footer.php'; ?>