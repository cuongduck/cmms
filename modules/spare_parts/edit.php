<?php
/**
 * Spare Parts Edit Page - FIXED VERSION
 * /modules/spare_parts/edit.php
 */

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

// Check permission
requirePermission('spare_parts', 'edit');

// Get spare part details
$sql = "SELECT sp.*, 
               COALESCE(oh.Onhand, 0) as current_stock,
               COALESCE(oh.UOM, sp.unit) as stock_unit,
               COALESCE(oh.OH_Value, 0) as stock_value
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE sp.id = ? AND sp.is_active = 1";
$part = $db->fetch($sql, [$id]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chỉnh sửa: ' . htmlspecialchars($part['item_name']);
$currentModule = 'spare_parts';
$moduleCSS = 'bom';
$moduleJS = 'spare-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => htmlspecialchars($part['item_name']), 'url' => 'view.php?id=' . $id],
    ['title' => 'Chỉnh sửa', 'url' => '']
];

require_once '../../includes/header.php';

// Get data for dropdowns
$categories = $sparePartsConfig['categories'];
$units = $sparePartsConfig['units'];
$users = $db->fetchAll("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
?>

<form id="sparePartsEditForm" class="needs-validation" novalidate>
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <div class="row">
        <div class="col-lg-8">
            <!-- Current Stock Alert -->
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Tồn kho hiện tại:</strong>
                        <span class="fs-5 ms-2"><?php echo number_format($part['current_stock'], 2); ?> <?php echo htmlspecialchars($part['stock_unit']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Giá trị:</strong>
                        <span class="fs-5 ms-2"><?php echo formatVND($part['stock_value']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Trạng thái:</strong>
                        <?php
                        $stockStatus = 'OK';
                        if ($part['current_stock'] <= $part['reorder_point']) $stockStatus = 'Reorder';
                        elseif ($part['current_stock'] < $part['min_stock']) $stockStatus = 'Low';
                        elseif ($part['current_stock'] == 0) $stockStatus = 'Out of Stock';
                        ?>
                        <span class="badge <?php echo getStockStatusClass($stockStatus); ?> ms-2">
                            <?php echo getStockStatusText($stockStatus); ?>
                        </span>
                    </div>
                </div>
            </div>

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
                                   style="text-transform: uppercase;" value="<?php echo htmlspecialchars($part['item_code']); ?>">
                            <div class="invalid-feedback">
                                Vui lòng nhập mã vật tư
                            </div>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="item_name" class="form-label">
                                Tên vật tư <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="item_name" name="item_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($part['item_name']); ?>">
                            <div class="invalid-feedback">
                                Vui lòng nhập tên vật tư
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Đơn vị tính</label>
                            <select id="unit" name="unit" class="form-select">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo $unit; ?>" <?php echo ($part['unit'] === $unit) ? 'selected' : ''; ?>>
                                        <?php echo $unit; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="standard_cost" class="form-label">Giá chuẩn (VNĐ)</label>
                            <input type="number" id="standard_cost" name="standard_cost" class="form-control" 
                                   min="0" step="1" value="<?php echo $part['standard_cost']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($part['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Thông số kỹ thuật</label>
                        <textarea id="specifications" name="specifications" class="form-control" rows="2"><?php echo htmlspecialchars($part['specifications'] ?? ''); ?></textarea>
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
                                   min="0" step="1" required value="<?php echo $part['min_stock']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="max_stock" class="form-label">Mức tồn tối đa</label>
                            <input type="number" id="max_stock" name="max_stock" class="form-control" 
                                   min="0" step="1" value="<?php echo $part['max_stock']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="estimated_annual_usage" class="form-label">
                                Dự kiến sử dụng/năm
                                <i class="fas fa-info-circle text-muted" 
                                   data-bs-toggle="tooltip" 
                                   title="Số lượng dự kiến sử dụng trong 1 năm"></i>
                            </label>
                            <input type="number" id="estimated_annual_usage" name="estimated_annual_usage" 
                                   class="form-control" min="0" step="1" 
                                   value="<?php echo $part['estimated_annual_usage'] ?? 0; ?>" 
                                   placeholder="0">
                            <small class="text-muted">Giúp tính toán nhu cầu mua hàng</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="reorder_point" class="form-label">Điểm đặt hàng lại</label>
                            <input type="number" id="reorder_point" name="reorder_point" class="form-control" 
                                   min="0" step="1" value="<?php echo $part['reorder_point']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="storage_location" class="form-label">Vị trí lưu kho</label>
                            <input type="text" id="storage_location" name="storage_location" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['storage_location'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="lead_time_days" class="form-label">Lead time (ngày)</label>
                            <input type="number" id="lead_time_days" name="lead_time_days" class="form-control" 
                                   min="0" step="1" value="<?php echo $part['lead_time_days']; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_critical" name="is_critical" value="1" 
                                       <?php echo $part['is_critical'] ? 'checked' : ''; ?>>
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
                            <select id="manager_user_id" name="manager_user_id" class="form-select">
                                <option value="">-- Chọn người quản lý --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($part['manager_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="supplier_code" class="form-label">Mã nhà cung cấp</label>
                            <input type="text" id="supplier_code" name="supplier_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['supplier_code'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="supplier_name" class="form-label">Tên nhà cung cấp</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($part['supplier_name'] ?? ''); ?>">
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
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($part['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="bom-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                </button>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Hủy
                </a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Stock Status Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Trạng thái tồn kho
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3><?php echo number_format($part['current_stock'], 2); ?></h3>
                        <small class="text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                    </div>
                    
                    <div class="progress mb-2" style="height: 8px;">
                        <?php 
                        $percentage = $part['max_stock'] > 0 ? ($part['current_stock'] / $part['max_stock']) * 100 : 0;
                        $progressClass = 'bg-success';
                        if ($percentage <= 25) $progressClass = 'bg-danger';
                        elseif ($percentage <= 50) $progressClass = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo min(100, $percentage); ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between small text-muted">
                        <span>0</span>
                        <span><?php echo number_format($part['max_stock'], 0); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Thao tác nhanh
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-2"></i>Xem chi tiết
                        </a>
                        
                        <?php if ($part['current_stock'] <= $part['reorder_point']): ?>
                        <button onclick="createPurchaseRequest(<?php echo $id; ?>)" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-shopping-cart me-2"></i>Đề xuất mua hàng
                        </button>
                        <?php endif; ?>
                        
                        <button onclick="viewStockHistory()" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-history me-2"></i>Lịch sử xuất nhập
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('sparePartsEditForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'update');
    
    CMMS.ajax({
        url: 'api/spare_parts.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'view.php?id=<?php echo $id; ?>';
                }, 1500);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
});

function createPurchaseRequest(sparePartId) {
    window.location.href = 'purchase_request.php?spare_part_id=' + sparePartId;
}

function viewStockHistory() {
    window.location.href = '/modules/inventory/transactions.php?item_code=<?php echo urlencode($part['item_code']); ?>';
}

// Event listener cho tự động phân loại khi thay đổi tên
document.getElementById('item_name').addEventListener('input', CMMS.utils.debounce(function() {
    const originalName = '<?php echo addslashes($part['item_name']); ?>';
    const currentName = this.value;
    
    if (currentName !== originalName && currentName.length >= 3) {
        showReclassificationSuggestion(currentName);
    }
}, 800));

// Function hiển thị gợi ý phân loại lại
function showReclassificationSuggestion(itemName) {
    CMMS.ajax({
        url: 'api/spare_parts.php?action=detect_category&item_name=' + encodeURIComponent(itemName),
        method: 'GET',
        success: (data) => {
            if (data.success && data.data.category) {
                const currentCategory = document.getElementById('category').value;
                const newCategory = data.data.category;
                
                if (newCategory !== currentCategory) {
                    showCategoryChangeSuggestion(currentCategory, newCategory, data.data.confidence);
                }
            }
        }
    });
}

// Function hiển thị gợi ý thay đổi category
function showCategoryChangeSuggestion(oldCategory, newCategory, confidence) {
    const confidenceDiv = document.getElementById('category_confidence');
    
    confidenceDiv.innerHTML = `
        <div class="alert alert-info alert-sm py-2">
            <i class="fas fa-lightbulb me-1"></i>
            <strong>Gợi ý:</strong> Danh mục có thể thay đổi thành 
            <span class="badge bg-primary">${newCategory}</span> 
            (${confidence}% tin cậy)
            <button type="button" onclick="acceptReclassification('${newCategory}')" 
                    class="btn btn-sm btn-outline-primary ms-2">
                Áp dụng
            </button>
            <button type="button" onclick="dismissSuggestion()" 
                    class="btn btn-sm btn-outline-secondary ms-1">
                Bỏ qua
            </button>
        </div>
    `;
}

// Function chấp nhận phân loại lại
function acceptReclassification(newCategory) {
    document.getElementById('category').value = newCategory;
    
    const categoryDisplay = document.getElementById('auto_category_display');
    categoryDisplay.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-success fs-6">${newCategory}</span>
            <small class="text-success">Đã cập nhật</small>
        </div>
    `;
    
    dismissSuggestion();
    CMMS.showToast(`Đã thay đổi danh mục thành: ${newCategory}`, 'success');
}

// Function từ chối gợi ý
function dismissSuggestion() {
    const confidenceDiv = document.getElementById('category_confidence');
    const currentCategory = document.getElementById('category').value;
    confidenceDiv.innerHTML = `Danh mục hiện tại: ${currentCategory}`;
}

// CSS cho alert nhỏ
const style = document.createElement('style');
style.textContent = `
    .alert-sm {
        padding: 0.375rem 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .alert-sm .btn {
        padding: 0.125rem 0.5rem;
        font-size: 0.75rem;
    }
`;
document.head.appendChild(style);
</script>

<?php require_once '../../includes/footer.php'; ?>