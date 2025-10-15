<?php
/**
 * Spare Parts Add Page - FIXED VERSION v2
 * /modules/spare_parts/add.php
 * 
 * FIXES:
 * 1. Thêm kiểm tra trùng item_code
 * 2. Sửa lỗi category auto-detection
 * 3. Cải thiện error handling
 * 4. Thêm validation phía server
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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Hướng dẫn:</strong> Nhập mã vật tư để tìm kiếm trong hệ thống. 
                    Nếu tìm thấy, thông tin sẽ tự động điền. Nếu không, bạn có thể nhập thủ công.
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="item_code" class="form-label">
                            Mã vật tư <span class="text-danger">*</span>
                        </label>
                        <div class="position-relative">
                            <input type="text" id="item_code" name="item_code" class="form-control" required
                                   placeholder="VD: SP001" style="text-transform: uppercase;" autocomplete="off">
                            <div class="invalid-feedback">
                                Vui lòng nhập mã vật tư
                            </div>
                            <div id="item_code_suggestions" class="list-group position-absolute" 
                                 style="z-index: 1000; max-height: 200px; overflow-y: auto; display: none; width: 100%;"></div>
                        </div>
                        <small class="text-muted">Nhập ít nhất 2 ký tự để tìm kiếm</small>
                        <div id="item_code_check" class="mt-1"></div>
                    </div>
                    
                    <div class="col-md-8 mb-3">
                        <label for="item_name" class="form-label">
                            Tên vật tư <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="item_name" name="item_name" class="form-control" required
                               placeholder="Nhập tên vật tư hoặc chọn từ gợi ý">
                        <div class="invalid-feedback">
                            Vui lòng nhập tên vật tư
                        </div>
                        <small class="text-muted" id="item_name_hint">
                            Tên sẽ tự động điền khi chọn mã từ gợi ý
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="unit" class="form-label">Đơn vị tính <span class="text-danger">*</span></label>
                        <select id="unit" name="unit" class="form-select" required>
                            <option value="">-- Chọn đơn vị --</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit; ?>" <?php echo ($unit === 'Cái') ? 'selected' : ''; ?>>
                                    <?php echo $unit; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Vui lòng chọn đơn vị</div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="standard_cost" class="form-label">Giá (VNĐ)</label>
                        <input type="number" id="standard_cost" name="standard_cost" class="form-control" 
                               min="0" step="1" value="0">
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
                        <label for="estimated_annual_usage" class="form-label">
                            Dự kiến sử dụng/năm
                            <i class="fas fa-info-circle text-muted" 
                               data-bs-toggle="tooltip" 
                               title="Số lượng dự kiến sử dụng trong 1 năm"></i>
                        </label>
                        <input type="number" id="estimated_annual_usage" name="estimated_annual_usage" 
                               class="form-control" min="0" step="1" placeholder="0">
                        <small class="text-muted">Giúp tính toán nhu cầu mua hàng</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="reorder_point" class="form-label">Điểm đặt hàng lại</label>
                        <input type="number" id="reorder_point" name="reorder_point" class="form-control" 
                               min="0" step="1" placeholder="Tự động = min_stock">
                        <div class="form-text">Để trống = tự động bằng min_stock</div>
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
                        <small class="text-muted">Mặc định là người tạo</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="supplier_code" class="form-label">Mã nhà cung cấp</label>
                        <input type="text" id="supplier_code" name="supplier_code" class="form-control">
                    </div>
                    
                    <div class="col-md-8 mb-3">
                        <label for="supplier_name" class="form-label">Tên nhà cung cấp</label>
                        <input type="text" id="supplier_name" name="supplier_name" class="form-control">
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
                        <p>Nhập mã vật tư để kiểm tra</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CMMS === 'undefined') {
        console.error('CMMS library not loaded!');
        alert('Lỗi: Thư viện CMMS chưa được load. Vui lòng refresh lại trang.');
        return;
    }

    let searchTimeout;
    let isItemSelected = false;
    let itemCodeCheckTimeout;

    // Auto-complete item code
    const itemCodeInput = document.getElementById('item_code');
    if (itemCodeInput) {
        itemCodeInput.addEventListener('input', function() {
            const itemCode = this.value.trim().toUpperCase();
            this.value = itemCode;
            isItemSelected = false;
            
            clearTimeout(searchTimeout);
            clearTimeout(itemCodeCheckTimeout);
            
            // Kiểm tra trùng item_code
            if (itemCode.length >= 3) {
                itemCodeCheckTimeout = setTimeout(() => {
                    checkItemCodeExists(itemCode);
                }, 500);
            } else {
                document.getElementById('item_code_check').innerHTML = '';
            }
            
            if (itemCode.length < 2) {
                document.getElementById('item_code_suggestions').style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchItemMMB(itemCode);
            }, 300);
        });
    }

    // Kiểm tra mã vật tư đã tồn tại chưa
    function checkItemCodeExists(itemCode) {
        CMMS.ajax({
            url: 'api/spare_parts.php?action=check_item_exists&item_code=' + encodeURIComponent(itemCode),
            method: 'GET',
            success: (data) => {
                const checkDiv = document.getElementById('item_code_check');
                if (data.exists) {
                    checkDiv.innerHTML = `
                        <div class="alert alert-danger alert-sm py-1 px-2 mb-0">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Mã này đã tồn tại: <strong>${data.item.item_name}</strong>
                            <a href="view.php?id=${data.item.id}" target="_blank" class="alert-link">Xem</a>
                        </div>
                    `;
                    itemCodeInput.classList.add('is-invalid');
                } else {
                    checkDiv.innerHTML = `
                        <small class="text-success">
                            <i class="fas fa-check-circle me-1"></i>Mã có thể sử dụng
                        </small>
                    `;
                    itemCodeInput.classList.remove('is-invalid');
                }
            },
            error: () => {
                document.getElementById('item_code_check').innerHTML = '';
            }
        });
    }

    function searchItemMMB(itemCode) {
        CMMS.ajax({
            url: 'api/spare_parts.php?action=search_item_mmb&item_code=' + encodeURIComponent(itemCode),
            method: 'GET',
            success: (data) => {
                if (data.success && data.data && data.data.length > 0) {
                    displaySuggestions(data.data);
                } else {
                    hideSuggestions();
                    document.getElementById('item_name_hint').innerHTML = 
                        '<span class="text-info"><i class="fas fa-info-circle me-1"></i>Không tìm thấy trong hệ thống. Bạn có thể nhập thủ công.</span>';
                }
            },
            error: (error) => {
                console.error('Search error:', error);
                hideSuggestions();
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
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${item.ITEM_CODE}</strong><br>
                        <small>${item.ITEM_NAME}</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">${item.UOM || ''}</small><br>
                        <small class="text-success">${formatVND(item.UNIT_PRICE || 0)}</small>
                    </div>
                </div>
            `;
            div.onclick = (e) => {
                e.preventDefault();
                selectItem(item);
            };
            suggestionsDiv.appendChild(div);
        });
        
        suggestionsDiv.style.display = 'block';
    }

    function hideSuggestions() {
        const suggestionsDiv = document.getElementById('item_code_suggestions');
        if (suggestionsDiv) {
            suggestionsDiv.style.display = 'none';
        }
    }

    function selectItem(item) {
        isItemSelected = true;
        
        document.getElementById('item_code').value = item.ITEM_CODE;
        document.getElementById('item_name').value = item.ITEM_NAME;
        
        const unitSelect = document.getElementById('unit');
        if (item.UOM && item.UOM.trim() !== '') {
            const uom = item.UOM.trim();
            
            let foundOption = false;
            for (let option of unitSelect.options) {
                if (option.value === uom) {
                    unitSelect.value = uom;
                    foundOption = true;
                    break;
                }
            }
            
            if (!foundOption) {
                const newOption = new Option(uom, uom, true, true);
                unitSelect.add(newOption);
            }
        } else {
            unitSelect.value = 'Cái';
        }
        
        document.getElementById('standard_cost').value = item.UNIT_PRICE || 0;
        document.getElementById('supplier_code').value = item.VENDOR_ID || '';
        document.getElementById('supplier_name').value = item.VENDOR_NAME || '';
        
        document.getElementById('item_name_hint').innerHTML = 
            '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Đã load từ hệ thống</span>';
        
        checkStock(item.ITEM_CODE);
        hideSuggestions();
        updatePreview();
        
        document.getElementById('min_stock').focus();
    }

    // Click outside to hide suggestions
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#item_code') && !e.target.closest('#item_code_suggestions')) {
            hideSuggestions();
        }
    });

    // Update preview
    const itemNameInput = document.getElementById('item_name');
    if (itemNameInput) {
        itemNameInput.addEventListener('input', debounce(function() {
            if (!isItemSelected && this.value.length >= 3) {
                document.getElementById('item_name_hint').innerHTML = 
                    '<span class="text-muted">Nhập thủ công</span>';
            }
            updatePreview();
        }, 500));
    }

    function updatePreview() {
        const itemCode = document.getElementById('item_code').value;
        const itemName = document.getElementById('item_name').value;
        const minStock = document.getElementById('min_stock').value;
        const maxStock = document.getElementById('max_stock').value;
        const isCritical = document.getElementById('is_critical').checked;
        
        const preview = document.getElementById('sparePartPreview');
        if (!preview) return;
        
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
                if (!result) return;
                
                if (data.success && data.data && data.data.Onhand !== undefined) {
                    const stock = data.data;
                    result.innerHTML = `
                        <div class="alert alert-info mb-0">
                            <h6 class="mb-2">
                                <i class="fas fa-check-circle me-2"></i>
                                Tồn kho hiện tại
                            </h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong class="fs-5">${parseFloat(stock.Onhand || 0).toFixed(2)}</strong>
                                    <small class="d-block text-muted">${stock.UOM || 'Cái'}</small>
                                </div>
                                <div class="col-6">
                                    <strong class="fs-5">${formatVND(stock.OH_Value || 0)}</strong>
                                    <small class="d-block text-muted">Giá trị</small>
                                </div>
                            </div>
                            ${parseFloat(stock.Onhand || 0) === 0 ? 
                                '<small class="text-warning d-block mt-2"><i class="fas fa-exclamation-triangle me-1"></i>Tồn kho = 0</small>' 
                                : ''}
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <h6 class="mb-2">
                                <i class="fas fa-info-circle me-2"></i>
                                Vật tư mới
                            </h6>
                            <p class="mb-0">
                                Không tìm thấy trong kho. Tồn kho mặc định = <strong>0</strong>
                            </p>
                            <small class="text-muted d-block mt-2">
                                Bạn có thể thêm vật tư này vào hệ thống
                            </small>
                        </div>
                    `;
                }
            },
            error: (error) => {
                const result = document.getElementById('stockCheckResult');
                if (result) {
                    result.innerHTML = `
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Không thể kiểm tra tồn kho. Vẫn có thể thêm vật tư mới.
                        </div>
                    `;
                }
            }
        });
    }

    // Auto-fill reorder point
    const minStockInput = document.getElementById('min_stock');
    if (minStockInput) {
        minStockInput.addEventListener('input', function() {
            const reorderPoint = document.getElementById('reorder_point');
            if (reorderPoint && !reorderPoint.value) {
                reorderPoint.value = this.value;
            }
            updatePreview();
        });
    }

    // Update preview on changes
    ['item_code', 'item_name', 'min_stock', 'max_stock', 'is_critical'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', updatePreview);
            if (element.type !== 'checkbox') {
                element.addEventListener('input', debounce(updatePreview, 300));
            }
        }
    });

    // Form submission
    const form = document.getElementById('sparePartsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                CMMS.showToast('Vui lòng điền đầy đủ thông tin bắt buộc', 'error');
                return;
            }
            
            // Kiểm tra mã đã tồn tại chưa (final check)
            const itemCode = document.getElementById('item_code').value;
            CMMS.ajax({
                url: 'api/spare_parts.php?action=check_item_exists&item_code=' + encodeURIComponent(itemCode),
                method: 'GET',
                success: (checkData) => {
                    if (checkData.exists) {
                        CMMS.showToast('Mã vật tư đã tồn tại: ' + checkData.item.item_name, 'error');
                        document.getElementById('item_code').focus();
                        return;
                    }
                    
                    // Proceed with save
                    submitForm();
                }
            });
        });
    }

    function submitForm() {
        const formData = new FormData(form);
        formData.append('action', 'save');
        
        CMMS.showLoading();
        
        CMMS.ajax({
            url: 'api/spare_parts.php',
            method: 'POST',
            body: formData,
            success: (data) => {
                CMMS.hideLoading();
                
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    
                    if (formData.has('save_and_new')) {
                        form.reset();
                        form.classList.remove('was-validated');
                        isItemSelected = false;
                        updatePreview();
                        document.getElementById('item_code').focus();
                    } else {
                        setTimeout(() => {
                            window.location.href = 'view.php?id=' + data.data.id;
                        }, 1500);
                    }
                } else {
                    CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: (error, response) => {
                CMMS.hideLoading();
                console.error('Submit error:', error);
                
                if (response && response.error_type === 'duplicate') {
                    CMMS.showToast('Mã vật tư đã tồn tại', 'error');
                    document.getElementById('item_code').focus();
                } else {
                    CMMS.showToast('Lỗi kết nối server', 'error');
                }
            }
        });
    }

    // Helper functions
    function formatVND(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(amount);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Global functions for buttons
    window.saveAndAddNew = function() {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'save_and_new';
        input.value = '1';
        form.appendChild(input);
        
        form.dispatchEvent(new Event('submit', { cancelable: true }));
    };

    // Focus first input
    if (itemCodeInput) {
        itemCodeInput.focus();
    }
});
</script>
<style>
.alert-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>
<?php require_once '../../includes/footer.php'; ?>