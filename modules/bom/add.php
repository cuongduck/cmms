<?php
/**
 * BOM Module - Add Page
 * /modules/bom/add.php
 * Trang thêm BOM mới
 */

$pageTitle = 'Thêm BOM mới';
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

require_once 'config.php';

// Check permission
requirePermission('bom', 'create');

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => 'index.php'],
    ['title' => 'Thêm BOM mới', 'url' => '']
];

require_once '../../includes/header.php';

// Get machine types
$machineTypes = $db->fetchAll(
    "SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name"
);

// Get all parts for selection
$parts = $db->fetchAll(
    "SELECT id, part_code, part_name, unit, unit_price, category 
     FROM parts ORDER BY part_code"
);

// Get categories and units for dropdowns
$categories = array_unique(array_column($parts, 'category'));
$units = $bomConfig['units'];
$priorities = $bomConfig['priorities'];
?>

<form id="bomForm" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <!-- BOM Information -->
            <div class="bom-form-container">
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Thông tin BOM
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="machine_type_id" class="form-label">
                                Dòng máy <span class="text-danger">*</span>
                            </label>
                            <select id="machine_type_id" name="machine_type_id" class="form-select" required>
                                <option value="">-- Chọn dòng máy --</option>
                                <?php foreach ($machineTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" data-code="<?php echo $type['code']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Vui lòng chọn dòng máy
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bom_code" class="form-label">
                                Mã BOM
                            </label>
                            <input type="text" id="bom_code" name="bom_code" class="form-control" 
                                   placeholder="Tự động tạo theo dòng máy" readonly>
                            <div class="form-text">
                                Mã BOM sẽ được tạo tự động khi chọn dòng máy
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="bom_name" class="form-label">
                                Tên BOM <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="bom_name" name="bom_name" class="form-control" required
                                   placeholder="Nhập tên BOM...">
                            <div class="invalid-feedback">
                                Vui lòng nhập tên BOM
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="version" class="form-label">Phiên bản</label>
                            <input type="text" id="version" name="version" class="form-control" 
                                   value="1.0" placeholder="1.0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="effective_date" class="form-label">Ngày hiệu lực</label>
                            <input type="date" id="effective_date" name="effective_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Nhập mô tả về BOM này..."></textarea>
                    </div>
                </div>

                <!-- BOM Items -->
                <div class="form-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="form-section-title mb-0">
                            <i class="fas fa-list"></i>
                            Danh sách linh kiện
                        </h5>
                        <button type="button" id="addBOMItem" class="btn btn-add-item">
                            <i class="fas fa-plus"></i>Thêm linh kiện
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="bom-items-table">
                            <thead>
                                <tr>
                                    <th width="40">#</th>
                                    <th width="300">Linh kiện</th>
                                    <th width="100">Số lượng</th>
                                    <th width="80">Đơn vị</th>
                                    <th width="150">Vị trí</th>
                                    <th width="120">Ưu tiên</th>
                                    <th width="100">Chu kỳ BT</th>
                                    <th width="50">Xóa</th>
                                </tr>
                            </thead>
                            <tbody id="bomItemsBody">
                                <!-- BOM items will be added here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Nhấn "Thêm linh kiện" để thêm dòng mới vào BOM
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- BOM Summary -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        Tổng kết BOM
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Số lượng linh kiện:</span>
                        <strong id="totalItems">0</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Tổng chi phí:</span>
                        <div class="cost-total">
                            <span id="totalCost">0 ₫</span>
                        </div>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu BOM
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Hủy bỏ
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Add Part -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-plus me-2"></i>
                        Thêm nhanh linh kiện
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <input type="text" id="quickSearch" class="form-control form-control-sm" 
                               placeholder="Tìm linh kiện theo mã hoặc tên...">
                    </div>
                    <div id="quickSearchResults" class="list-group" style="max-height: 200px; overflow-y: auto;">
                        <!-- Search results will be shown here -->
                    </div>
                </div>
            </div>
            
            <!-- Help -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i>
                        Hướng dẫn
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Chọn dòng máy trước để tạo mã BOM tự động
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Thêm các linh kiện cần thiết vào BOM
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Điều chỉnh số lượng và độ ưu tiên
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            Chu kỳ BT tính bằng giờ (có thể bỏ trống)
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Hidden data for JavaScript -->
<script>
// Parts data for JavaScript
window.bomPartsData = <?php echo json_encode($parts, JSON_UNESCAPED_UNICODE); ?>;

// Categories and priorities
window.bomCategories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
window.bomPriorities = <?php echo json_encode($priorities, JSON_UNESCAPED_UNICODE); ?>;
window.bomUnits = <?php echo json_encode($units, JSON_UNESCAPED_UNICODE); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Add first empty row
    CMMS.BOM.addBOMItem();
    
    // Quick search functionality
    const quickSearch = document.getElementById('quickSearch');
    const searchResults = document.getElementById('quickSearchResults');
    
    quickSearch.addEventListener('input', CMMS.utils.debounce(function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        if (searchTerm.length < 2) {
            searchResults.innerHTML = '';
            return;
        }
        
        const filteredParts = window.bomPartsData.filter(part => 
            part.part_code.toLowerCase().includes(searchTerm) || 
            part.part_name.toLowerCase().includes(searchTerm)
        ).slice(0, 10);
        
        if (filteredParts.length === 0) {
            searchResults.innerHTML = '<div class="list-group-item text-muted small">Không tìm thấy linh kiện</div>';
            return;
        }
        
        searchResults.innerHTML = filteredParts.map(part => 
            `<div class="list-group-item list-group-item-action p-2" style="cursor: pointer;" 
                  onclick="addPartToBOM(${part.id})">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="part-code">${part.part_code}</small><br>
                        <span class="small">${part.part_name}</span>
                    </div>
                    <small class="text-success">${CMMS.BOM.formatCurrency(part.unit_price)}</small>
                </div>
            </div>`
        ).join('');
    }, 300));
});

// Add part to BOM from quick search
function addPartToBOM(partId) {
    // Add new row if needed
    const tbody = document.getElementById('bomItemsBody');
    if (tbody.children.length === 0) {
        CMMS.BOM.addBOMItem();
    }
    
    // Find the last empty row or add new one
    const rows = tbody.querySelectorAll('tr');
    let targetRow = null;
    
    for (let row of rows) {
        const partSelect = row.querySelector('select[name$="[part_id]"]');
        if (partSelect && !partSelect.value) {
            targetRow = row;
            break;
        }
    }
    
    if (!targetRow) {
        CMMS.BOM.addBOMItem();
        targetRow = tbody.lastElementChild;
    }
    
    // Set the part
    const partSelect = targetRow.querySelector('select[name$="[part_id]"]');
    if (partSelect) {
        partSelect.value = partId;
        partSelect.dispatchEvent(new Event('change'));
    }
    
    // Clear search
    document.getElementById('quickSearch').value = '';
    document.getElementById('quickSearchResults').innerHTML = '';
    
    // Focus on quantity
    const quantityInput = targetRow.querySelector('input[name$="[quantity]"]');
    if (quantityInput) {
        quantityInput.focus();
        quantityInput.select();
    }
}

// Update totals when items change
document.addEventListener('change', function(e) {
    if (e.target.closest('#bomItemsBody')) {
        updateTotals();
    }
});

document.addEventListener('input', function(e) {
    if (e.target.closest('#bomItemsBody')) {
        updateTotals();
    }
});

function updateTotals() {
    const rows = document.querySelectorAll('#bomItemsBody tr');
    let totalItems = 0;
    let totalCost = 0;
    
    rows.forEach(row => {
        const partSelect = row.querySelector('select[name$="[part_id]"]');
        const quantityInput = row.querySelector('input[name$="[quantity]"]');
        
        if (partSelect && quantityInput && partSelect.value && quantityInput.value) {
            totalItems++;
            
            const partOption = partSelect.selectedOptions[0];
            const unitPrice = parseFloat(partOption.dataset.price || 0);
            const quantity = parseFloat(quantityInput.value || 0);
            
            totalCost += unitPrice * quantity;
        }
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalCost').textContent = CMMS.BOM.formatCurrency(totalCost);
}
</script>

<?php require_once '../../includes/footer.php'; ?>