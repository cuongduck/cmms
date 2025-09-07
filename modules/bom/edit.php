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
    "SELECT p.id, p.part_code, p.part_name, p.unit, p.unit_price, p.category,
            COALESCE(oh.Onhand, 0) as stock_quantity
     FROM parts p
     LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
     ORDER BY p.part_code"
);

// Get units and priorities
$units = $bomConfig['units'];
$priorities = array_keys($bomConfig['priorities']); // ['Low', 'Medium', 'High', 'Critical']
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
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                                  placeholder="Nhập mô tả BOM..."><?php echo htmlspecialchars($bom['description']); ?></textarea>
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
                                <?php foreach ($bom['items'] as $index => $item): ?>
                                    <tr class="bom-item-row">
                                        <td>
                                            <select name="items[<?php echo $index; ?>][part_id]" class="form-select part-select" required onchange="CMMS.BOM.updatePartDetails(this)">
                                                <option value="">-- Chọn linh kiện --</option>
                                                <?php foreach ($parts as $part): ?>
                                                    <option value="<?php echo $part['id']; ?>" 
                                                            data-price="<?php echo $part['unit_price']; ?>" 
                                                            data-unit="<?php echo $part['unit']; ?>" 
                                                            data-name="<?php echo htmlspecialchars($part['part_name']); ?>" 
                                                            data-code="<?php echo htmlspecialchars($part['part_code']); ?>"
                                                            <?php echo ($part['id'] == $item['part_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="items[<?php echo $index; ?>][quantity]" class="form-control quantity" min="0.01" step="0.01" required value="<?php echo $item['quantity']; ?>" oninput="updateTotals()"></td>
                                        <td><input type="text" name="items[<?php echo $index; ?>][unit]" class="form-control" readonly value="<?php echo htmlspecialchars($item['unit']); ?>"></td>
                                        <td><input type="number" name="items[<?php echo $index; ?>][unit_price]" class="form-control" readonly value="<?php echo $item['unit_price']; ?>"></td>
                                        <td>
                                            <select name="items[<?php echo $index; ?>][priority]" class="form-select">
                                                <?php foreach ($priorities as $priority): ?>
                                                    <option value="<?php echo $priority; ?>" <?php echo ($priority == $item['priority']) ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals()">Xóa</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" id="addBOMItem" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>Thêm linh kiện
                        </button>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tổng số linh kiện</label>
                            <div class="form-control-plaintext">
                                <span id="totalItems"><?php echo count($bom['items']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tổng chi phí</label>
                            <div class="form-control-plaintext">
                                <span id="totalCost"><?php echo formatVND(calculateBOMCost($bomId)); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú</label>
                        <textarea id="notes" name="notes" class="form-control" 
                                  rows="4" placeholder="Nhập ghi chú..."><?php echo htmlspecialchars($bom['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Lưu thay đổi
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="/assets/js/main.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/bom.js?v=<?php echo time(); ?>"></script>
<script>
// Config từ PHP
window.bomConfig = <?php 
echo json_encode(['units' => $units, 'priorities' => $priorities]);
?>;
window.bomPartsData = <?php echo json_encode($parts); ?>;

// Track changes
let hasChanges = false;
const form = document.getElementById('bomForm');
const originalData = new FormData(form);
form.addEventListener('change', () => { hasChanges = true; });
form.addEventListener('input', () => { hasChanges = true; });
window.addEventListener('beforeunload', (e) => {
    if (hasChanges) {
        e.preventDefault();
        e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc muốn rời khỏi trang?';
    }
});
form.addEventListener('submit', () => { hasChanges = false; });

// Quick search functionality
document.getElementById('quickSearch').addEventListener('input', CMMS.utils.debounce(function(e) {
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
        `<div class="list-group-item list-group-item-action p-2 cursor-pointer" 
              onclick="CMMS.BOM.addPartToBOM(${part.id})">
            <div class="d-flex justify-content-between">
                <div>
                    <small class="part-code">${part.part_code}</small><br>
                    <span class="small">${part.part_name}</span><br>
                    <small class="text-muted">Tồn kho: ${part.stock_quantity}</small>
                </div>
                <small class="text-success">${CMMS.formatCurrency(part.unit_price)}</small>
            </div>
        </div>`
    ).join('');
}, 300));

// Update totals
function updateTotals() {
    let totalItems = 0;
    let totalCost = 0;
    const rows = document.querySelectorAll('#bomItemsBody tr');
    
    rows.forEach(row => {
        const partSelect = row.querySelector('select[name$="[part_id]"]');
        const quantityInput = row.querySelector('input[name$="[quantity]"]');
        
        if (partSelect?.value && quantityInput?.value > 0) {
            totalItems++;
            const unitPrice = parseFloat(partSelect.selectedOptions[0].dataset.price) || 0;
            const quantity = parseFloat(quantityInput.value) || 0;
            totalCost += unitPrice * quantity;
        }
    });
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('totalCost').textContent = CMMS.formatCurrency(totalCost);
}

// Initialize totals
updateTotals();
</script>

<style>
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