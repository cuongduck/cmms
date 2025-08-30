<?php
/**
 * BOM Module - Edit Page
 * /modules/bom/edit.php
 * Trang chỉnh sửa BOM
 */
require_once 'config.php';

// Check permission
requirePermission('bom', 'edit');

$bomId = intval($_GET['id'] ?? 0);
if (!$bomId) {
    header('Location: index.php');
    exit;
}

require_once '../../includes/header.php';

// Get BOM details
$bom = getBOMDetails($bomId);
if (!$bom) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chỉnh sửa BOM: ' . $bom['bom_name'];
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => 'index.php'],
    ['title' => $bom['bom_name'], 'url' => 'view.php?id=' . $bomId],
    ['title' => 'Chỉnh sửa', 'url' => '']
];

// Get machine types
$machineTypes = $db->fetchAll(
    "SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name"
);

// Get all parts
$parts = $db->fetchAll(
    "SELECT id, part_code, part_name, unit, unit_price, category 
     FROM parts ORDER BY part_code"
);

$categories = array_unique(array_column($parts, 'category'));
$units = $bomConfig['units'];
$priorities = $bomConfig['priorities'];
?>

<form id="bomForm" class="needs-validation" novalidate>
    <input type="hidden" name="bom_id" value="<?php echo $bomId; ?>">
    
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
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-code="<?php echo $type['code']; ?>"
                                            <?php echo ($type['id'] == $bom['machine_type_id']) ? 'selected' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($bom['bom_code']); ?>"
                                   placeholder="Tự động tạo theo dòng máy">
                            <div class="form-text">
                                Có thể để trống để tạo mã tự động
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="bom_name" class="form-label">
                                Tên BOM <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="bom_name" name="bom_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($bom['bom_name']); ?>"
                                   placeholder="Nhập tên BOM...">
                            <div class="invalid-feedback">
                                Vui lòng nhập tên BOM
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="version" class="form-label">Phiên bản</label>
                            <input type="text" id="version" name="version" class="form-control" 
                                   value="<?php echo htmlspecialchars($bom['version']); ?>"
                                   placeholder="1.0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="effective_date" class="form-label">Ngày hiệu lực</label>
                            <input type="date" id="effective_date" name="effective_date" class="form-control" 
                                   value="<?php echo $bom['effective_date'] ? date('Y-m-d', strtotime($bom['effective_date'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Nhập mô tả về BOM này..."><?php echo htmlspecialchars($bom['description']); ?></textarea>
                    </div>
                </div>

                <!-- BOM Items -->
                <div class="form-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="form-section-title mb-0">
                            <i class="fas fa-list"></i>
                            Danh sách linh kiện (<?php echo count($bom['items']); ?> items)
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
                                    <th width="80">Tồn kho</th>
                                    <th width="50">Xóa</th>
                                </tr>
                            </thead>
                            <tbody id="bomItemsBody">
                                <?php foreach ($bom['items'] as $index => $item): ?>
                                <tr class="bom-item-row priority-<?php echo $item['priority']; ?>">
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td>
                                        <select name="items[<?php echo $index; ?>][part_id]" class="form-select" required>
                                            <option value="">-- Chọn linh kiện --</option>
                                            <?php foreach ($parts as $part): ?>
                                                <option value="<?php echo $part['id']; ?>"
                                                        data-price="<?php echo $part['unit_price']; ?>"
                                                        data-unit="<?php echo $part['unit']; ?>"
                                                        <?php echo ($part['id'] == $item['part_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                               class="form-control" value="<?php echo $item['quantity']; ?>" 
                                               min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <select name="items[<?php echo $index; ?>][unit]" class="form-select">
                                            <?php foreach ($units as $unit): ?>
                                                <option value="<?php echo $unit; ?>" 
                                                        <?php echo ($unit === $item['unit']) ? 'selected' : ''; ?>>
                                                    <?php echo $unit; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="items[<?php echo $index; ?>][position]" 
                                               class="form-control" value="<?php echo htmlspecialchars($item['position']); ?>" 
                                               placeholder="Vị trí lắp đặt">
                                    </td>
                                    <td>
                                        <select name="items[<?php echo $index; ?>][priority]" class="form-select">
                                            <?php foreach ($priorities as $key => $priority): ?>
                                                <option value="<?php echo $key; ?>" 
                                                        <?php echo ($key === $item['priority']) ? 'selected' : ''; ?>>
                                                    <?php echo $priority['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="items[<?php echo $index; ?>][maintenance_interval]" 
                                               class="form-control" value="<?php echo $item['maintenance_interval']; ?>" 
                                               placeholder="Giờ">
                                    </td>
                                    <td class="text-center">
                                        <div class="stock-indicator">
                                            <span class="stock-dot <?php echo strtolower($item['stock_status']); ?>"></span>
                                            <small><?php echo number_format($item['stock_quantity'], 1); ?></small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-remove-item" onclick="CMMS.BOM.removeBOMItem(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($bom['items'])): ?>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            BOM này chưa có linh kiện nào. Nhấn "Thêm linh kiện" để bắt đầu.
                        </small>
                    </div>
                    <?php endif; ?>
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
                        <strong id="totalItems"><?php echo count($bom['items']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Tổng chi phí:</span>
                        <div class="cost-total">
                            <span id="totalCost"><?php echo formatVND(calculateBOMCost($bomId)); ?></span>
                        </div>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Cập nhật BOM
                        </button>
                        <a href="view.php?id=<?php echo $bomId; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-eye me-2"></i>Xem BOM
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>Danh sách
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Change Log -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Lịch sử thay đổi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php
                        // Get BOM history
                        $history = $db->fetchAll(
                            "SELECT bh.*, u.full_name as user_name 
                             FROM bom_history bh 
                             LEFT JOIN users u ON bh.user_id = u.id 
                             WHERE bh.bom_id = ? 
                             ORDER BY bh.created_at DESC 
                             LIMIT 10", 
                            [$bomId]
                        );
                        
                        if (empty($history)):
                        ?>
                            <div class="text-center text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Chưa có lịch sử thay đổi
                            </div>
                        <?php else: ?>
                            <?php foreach ($history as $entry): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <strong><?php echo ucfirst(str_replace('_', ' ', $entry['action'])); ?></strong>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stock Status Summary -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-warehouse me-2"></i>
                        Tình trạng tồn kho
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $stockSummary = [
                        'ok' => 0,
                        'low' => 0, 
                        'out' => 0
                    ];
                    
                    foreach ($bom['items'] as $item) {
                        $status = strtolower($item['stock_status']);
                        if (isset($stockSummary[$status])) {
                            $stockSummary[$status]++;
                        }
                    }
                    ?>
                    
                    <div class="row text-center g-0">
                        <div class="col-4">
                            <div class="p-2 bg-success bg-opacity-10 rounded">
                                <div class="h5 mb-1 text-success"><?php echo $stockSummary['ok']; ?></div>
                                <small>Đủ hàng</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-warning bg-opacity-10 rounded">
                                <div class="h5 mb-1 text-warning"><?php echo $stockSummary['low']; ?></div>
                                <small>Sắp hết</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-danger bg-opacity-10 rounded">
                                <div class="h5 mb-1 text-danger"><?php echo $stockSummary['out']; ?></div>
                                <small>Hết hàng</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($stockSummary['low'] > 0 || $stockSummary['out'] > 0): ?>
                    <div class="mt-2">
                        <a href="reports/shortage_report.php?bom_id=<?php echo $bomId; ?>" 
                           class="btn btn-sm btn-outline-warning w-100">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Xem báo cáo thiếu hàng
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Add Part -->
            <div class="card">
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
        </div>
    </div>
</form>

<!-- Hidden data for JavaScript -->
<script>
// Parts data for JavaScript
window.bomPartsData = <?php echo json_encode($parts, JSON_UNESCAPED_UNICODE); ?>;

// Existing BOM data
window.currentBOM = {
    id: <?php echo $bomId; ?>,
    items: <?php echo json_encode($bom['items'], JSON_UNESCAPED_UNICODE); ?>
};

// Categories and priorities
window.bomCategories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
window.bomPriorities = <?php echo json_encode($priorities, JSON_UNESCAPED_UNICODE); ?>;
window.bomUnits = <?php echo json_encode($units, JSON_UNESCAPED_UNICODE); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Bind existing item events
    const existingRows = document.querySelectorAll('#bomItemsBody tr');
    existingRows.forEach(row => {
        CMMS.BOM.bindRowEvents(row);
    });
    
    // Update initial totals
    updateTotals();
    
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
    
    // Track changes for confirmation
    let hasChanges = false;
    const form = document.getElementById('bomForm');
    const originalData = new FormData(form);
    
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
});

// Add part to BOM from quick search
function addPartToBOM(partId) {
    // Check if part already exists in BOM
    const existingSelects = document.querySelectorAll('select[name$="[part_id]"]');
    for (let select of existingSelects) {
        if (select.value == partId) {
            select.closest('tr').style.backgroundColor = '#fef3c7';
            setTimeout(() => {
                select.closest('tr').style.backgroundColor = '';
            }, 2000);
            
            CMMS.showToast('Linh kiện này đã có trong BOM', 'warning');
            return;
        }
    }
    
    // Add new row
    CMMS.BOM.addBOMItem();
    
    // Set the part in the new row
    const tbody = document.getElementById('bomItemsBody');
    const newRow = tbody.lastElementChild;
    const partSelect = newRow.querySelector('select[name$="[part_id]"]');
    
    if (partSelect) {
        partSelect.value = partId;
        partSelect.dispatchEvent(new Event('change'));
    }
    
    // Clear search
    document.getElementById('quickSearch').value = '';
    document.getElementById('quickSearchResults').innerHTML = '';
    
    // Focus on quantity
    const quantityInput = newRow.querySelector('input[name$="[quantity]"]');
    if (quantityInput) {
        quantityInput.focus();
        quantityInput.select();
    }
    
    updateTotals();
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
            
            // Update row priority class
            const prioritySelect = row.querySelector('select[name$="[priority]"]');
            if (prioritySelect) {
                row.className = row.className.replace(/priority-\w+/g, '');
                row.classList.add('bom-item-row', `priority-${prioritySelect.value}`);
            }
        }
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalCost').textContent = CMMS.BOM.formatCurrency(totalCost);
}

// Override the saveBOM function to handle edit mode
CMMS.BOM.saveBOM = function() {
    const form = document.getElementById('bomForm');
    const formData = new FormData(form);
    
    // Validate form
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Add action for update
    formData.append('action', 'update');
    
    CMMS.ajax({
        url: '/modules/bom/api/bom.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                hasChanges = false; // Clear change flag
                
                // Optionally redirect to view page
                setTimeout(() => {
                    window.location.href = 'view.php?id=' + <?php echo $bomId; ?>;
                }, 1500);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
};
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

/* Highlight changed rows */
.bom-item-changed {
    background-color: #fef3c7 !important;
    animation: highlightFade 3s ease-out;
}

@keyframes highlightFade {
    0% { background-color: #fef3c7; }
    100% { background-color: transparent; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>