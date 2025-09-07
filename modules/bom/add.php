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
    "SELECT p.id, p.part_code, p.part_name, p.unit, p.unit_price, p.category, 
            COALESCE(oh.Onhand, 0) as stock_quantity
     FROM parts p
     LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
     ORDER BY p.part_code"
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
                            <label for="version" class="form-label">
                                Phiên bản
                            </label>
                            <input type="text" id="version" name="version" class="form-control" 
                                   placeholder="1.0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Mô tả
                        </label>
                        <textarea id="description" name="description" class="form-control" 
                                  rows="3" placeholder="Nhập mô tả BOM..."></textarea>
                    </div>
                </div>
                
                <!-- BOM Items -->
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-cubes"></i>
                        Danh sách linh kiện
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Tìm kiếm nhanh linh kiện</label>
                        <div class="position-relative">
                            <input type="text" id="quickSearch" class="form-control" 
                                   placeholder="Nhập mã hoặc tên linh kiện...">
                            <div id="quickSearchResults" class="list-group position-absolute w-100 mt-1" 
                                 style="z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th width="35%">Linh kiện</th>
                                    <th width="15%">Số lượng</th>
                                    <th width="15%">Đơn vị</th>
                                    <th width="20%">Đơn giá</th>
                                    <th width="10%">Ưu tiên</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="bomItemsBody">
                                <!-- Items will be added dynamically -->
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary btn-sm" 
                            onclick="CMMS.BOM.addBOMItem()">
                        <i class="fas fa-plus me-1"></i>Thêm dòng
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Summary -->
            <div class="bom-form-container sticky-top pt-3">
                <div class="form-section">
                    <h5 class="form-section-title">
                        <i class="fas fa-calculator"></i>
                        Tổng quan
                    </h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Tổng số linh kiện</label>
                        <div class="form-control-plaintext">
                            <span id="totalItems">0</span> loại
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tổng chi phí</label>
                        <div class="form-control-plaintext">
                            <span id="totalCost">0</span> VNĐ
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú</label>
                        <textarea id="notes" name="notes" class="form-control" 
                                  rows="4" placeholder="Nhập ghi chú..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Lưu BOM
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Initialize parts data for quick search
window.bomPartsData = <?php echo json_encode($parts); ?>;

// Form validation
(function() {
    'use strict';
    
    const form = document.getElementById('bomForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
        
        // Additional validation for BOM items
        const rows = document.querySelectorAll('#bomItemsBody tr');
        let hasValidItem = false;
        
        rows.forEach(row => {
            const partSelect = row.querySelector('select[name$="[part_id]"]');
            const quantityInput = row.querySelector('input[name$="[quantity]"]');
            
            if (partSelect?.value && quantityInput?.value > 0) {
                hasValidItem = true;
            }
        });
        
        if (!hasValidItem) {
            event.preventDefault();
            CMMS.showToast('Vui lòng thêm ít nhất một linh kiện với số lượng hợp lệ', 'warning');
            return;
        }
        
        // Submit form via AJAX
        event.preventDefault();
        CMMS.BOM.submitBOM(form);
    }, false);
})();

// Generate BOM code when machine type changes
document.getElementById('machine_type_id').addEventListener('change', function() {
    const selectedOption = this.selectedOptions[0];
    const machineCode = selectedOption ? selectedOption.dataset.code : '';
    const bomCodeInput = document.getElementById('bom_code');
    
    if (machineCode) {
        bomCodeInput.value = `${machineCode}-BOM${Date.now().toString().slice(-6)}`;
    } else {
        bomCodeInput.value = '';
    }
});

// Quick search functionality
document.getElementById('quickSearch').addEventListener('input', CMMS.debounce(function(e) {
    const searchTerm = e.target.value.trim().toLowerCase();
    const searchResults = document.getElementById('quickSearchResults');
    
    if (!searchTerm) {
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
                    <span class="small">${part.part_name}</span><br>
                    <small class="text-muted">Tồn kho: ${part.stock_quantity}</small>
                </div>
                <small class="text-success">${CMMS.BOM.formatCurrency(part.unit_price)}</small>
            </div>
        </div>`
    ).join('');
}, 300));

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