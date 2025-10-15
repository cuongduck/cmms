<?php
/**
 * Purchase Request - Đề xuất mua hàng - FINAL VERSION
 * /modules/spare_parts/purchase_request.php
 */

require_once 'config.php';
requirePermission('spare_parts', 'view');

$pageTitle = 'Đề xuất mua hàng';
$currentModule = 'spare_parts';
$moduleCSS = 'bom';
$moduleJS = 'spare-parts';

$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => 'Đề xuất mua hàng', 'url' => '']
];

require_once '../../includes/header.php';

// Lấy user hiện tại
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['username'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';

// Lấy danh sách vật tư cần đặt hàng
$sql = "SELECT sp.*, 
               COALESCE(oh.Onhand, 0) as current_stock,
               COALESCE(oh.UOM, sp.unit) as stock_unit,
               COALESCE(oh.Price, sp.standard_cost) as current_price,
               u1.full_name as manager_name,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point 
                   THEN GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock)
                   ELSE 0
               END as suggested_order_qty
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        LEFT JOIN users u1 ON sp.manager_user_id = u1.id
        WHERE sp.is_active = 1 
          AND COALESCE(oh.Onhand, 0) <= sp.reorder_point";

// Nếu không phải Admin, chỉ lấy vật tư của user quản lý
if ($userRole !== 'Admin') {
    $sql .= " AND sp.manager_user_id = ?";
    $reorderParts = $db->fetchAll($sql, [$userId]);
} else {
    $reorderParts = $db->fetchAll($sql);
}

// Sắp xếp theo mức độ ưu tiên
usort($reorderParts, function($a, $b) {
    if ($a['current_stock'] == 0 && $b['current_stock'] > 0) return -1;
    if ($a['current_stock'] > 0 && $b['current_stock'] == 0) return 1;
    if ($a['current_stock'] <= $a['reorder_point'] && $b['current_stock'] > $b['reorder_point']) return -1;
    if ($a['current_stock'] > $a['reorder_point'] && $b['current_stock'] <= $b['reorder_point']) return 1;
    return strcmp($a['item_code'], $b['item_code']);
});
?>

<style>
.purchase-table {
    font-size: 0.9rem;
}
.purchase-table input[type="number"] {
    width: 100px;
    text-align: right;
}
.purchase-table .form-check-input {
    cursor: pointer;
}
.stock-critical {
    background-color: #fff3cd !important;
}
.stock-out {
    background-color: #f8d7da !important;
}
.sticky-header {
    position: sticky;
    top: 0;
    background: white;
    z-index: 100;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle me-2"></i>Hướng dẫn</h5>
            <ol class="mb-0">
                <li>Chọn vật tư cần mua bằng checkbox</li>
                <li>Nhập số lượng đề xuất (mặc định là số lượng gợi ý)</li>
                <li>Nhấn "Xuất Excel" để tạo file đề xuất mua hàng</li>
            </ol>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h3 class="mb-2"><?php echo count($reorderParts); ?></h3>
                <p class="mb-0 text-muted">Vật tư cần đặt hàng</p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($reorderParts)): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Tuyệt vời!</strong> Hiện tại không có vật tư nào cần đặt hàng.
</div>
<?php else: ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-shopping-cart me-2"></i>
            Danh sách vật tư cần đặt hàng
        </h5>
        <div>
            <button type="button" class="btn btn-success" onclick="selectAll()">
                <i class="fas fa-check-square me-1"></i>Chọn tất cả
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                <i class="fas fa-square me-1"></i>Bỏ chọn tất cả
            </button>
            <button type="button" class="btn btn-primary" onclick="exportToExcel()" id="exportBtn">
                <i class="fas fa-file-excel me-1"></i>Xuất Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 purchase-table">
                <thead class="table-light sticky-header">
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onclick="toggleSelectAll()">
                        </th>
                        <th style="width: 40px;">#</th>
                        <th style="width: 120px;">Mã vật tư</th>
                        <th>Tên vật tư</th>
                        <th style="width: 80px;">Tồn hiện tại</th>
                        <th style="width: 80px;">Min/Max</th>
                        <th style="width: 100px;">Đề xuất</th>
                        <th style="width: 120px;">Số lượng mua <span class="text-danger">*</span></th>
                        <th style="width: 100px;">Đơn giá</th>
                        <th style="width: 120px;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rowNum = 1;
                    foreach ($reorderParts as $part): 
                        $isOutOfStock = $part['current_stock'] == 0;
                        $rowClass = $isOutOfStock ? 'stock-out' : ($part['current_stock'] <= $part['reorder_point'] ? 'stock-critical' : '');
                    ?>
                    <tr class="<?php echo $rowClass; ?>" data-part-id="<?php echo $part['id']; ?>">
                        <td>
                            <input type="checkbox" class="form-check-input part-checkbox" 
                                   data-item-code="<?php echo htmlspecialchars($part['item_code']); ?>"
                                   data-item-name="<?php echo htmlspecialchars($part['item_name']); ?>"
                                   data-unit="<?php echo htmlspecialchars($part['unit']); ?>"
                                   data-price="<?php echo $part['current_price']; ?>"
                                   data-suggested-qty="<?php echo $part['suggested_order_qty']; ?>">
                        </td>
                        <td><?php echo $rowNum++; ?></td>
                        <td>
                            <span class="part-code"><?php echo htmlspecialchars($part['item_code']); ?></span>
                            <?php if ($part['is_critical']): ?>
                            <span class="badge bg-warning text-dark ms-1" title="Vật tư quan trọng">!</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($part['item_name']); ?></strong>
                            <?php if ($part['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($part['description'], 0, 50)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <strong class="<?php echo $isOutOfStock ? 'text-danger' : 'text-warning'; ?>">
                                <?php echo number_format($part['current_stock'], 0); ?>
                            </strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                        </td>
                        <td class="text-center">
                            <small><?php echo number_format($part['min_stock'], 0); ?> / <?php echo number_format($part['max_stock'], 0); ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info"><?php echo number_format($part['suggested_order_qty'], 0); ?></span>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm qty-input" 
                                   min="1" step="1" 
                                   value="<?php echo $part['suggested_order_qty']; ?>"
                                   onchange="calculateAmount(this)"
                                   data-price="<?php echo $part['current_price']; ?>">
                        </td>
                        <td class="text-end">
                            <small><?php echo number_format($part['current_price'], 0); ?></small>
                        </td>
                        <td class="text-end amount-cell">
                            <strong><?php echo number_format($part['suggested_order_qty'] * $part['current_price'], 0); ?></strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="9" class="text-end"><strong>TỔNG CỘNG:</strong></td>
                        <td class="text-end"><strong id="totalAmount">0</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
// Toggle select all
function toggleSelectAll() {
    const mainCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.part-checkbox');
    checkboxes.forEach(cb => cb.checked = mainCheckbox.checked);
    updateTotal();
}

function selectAll() {
    document.querySelectorAll('.part-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateTotal();
}

function deselectAll() {
    document.querySelectorAll('.part-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateTotal();
}

// Calculate amount when quantity changes
function calculateAmount(input) {
    const row = input.closest('tr');
    const price = parseFloat(input.dataset.price || 0);
    const qty = parseFloat(input.value || 0);
    const amount = price * qty;
    
    row.querySelector('.amount-cell strong').textContent = amount.toLocaleString('vi-VN');
    updateTotal();
}

// Update total amount
function updateTotal() {
    let total = 0;
    document.querySelectorAll('.part-checkbox:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const qtyInput = row.querySelector('.qty-input');
        const price = parseFloat(qtyInput.dataset.price || 0);
        const qty = parseFloat(qtyInput.value || 0);
        total += price * qty;
    });
    
    document.getElementById('totalAmount').textContent = total.toLocaleString('vi-VN');
}

// Export to Excel
function exportToExcel() {
    const selectedParts = [];
    let lineNumber = 1;
    
    document.querySelectorAll('.part-checkbox:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const qtyInput = row.querySelector('.qty-input');
        const qty = parseFloat(qtyInput.value || 0);
        
        if (qty > 0) {
            selectedParts.push({
                line: lineNumber++,
                item_code: checkbox.dataset.itemCode,
                item_name: checkbox.dataset.itemName,
                quantity: qty,
                uom: checkbox.dataset.unit,
                price: parseFloat(checkbox.dataset.price || 0),
                amount: qty * parseFloat(checkbox.dataset.price || 0),
                requester: '<?php echo $userName; ?>'
            });
        }
    });
    
    if (selectedParts.length === 0) {
        CMMS.showToast('Vui lòng chọn ít nhất 1 vật tư', 'warning');
        return;
    }
    
    // Hiển thị loading
    const btn = document.getElementById('exportBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang tạo file...';
    
    // Tạo form ẩn để submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/purchase_request.php';
    form.target = '_blank';
    
    // Add data
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'data';
    input.value = JSON.stringify({
        action: 'export_excel',
        items: selectedParts
    });
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    // Reset button sau 2 giây
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        CMMS.showToast('Đã tạo file thành công!', 'success');
        
        // Hỏi reload
        setTimeout(() => {
            if (confirm('Đã xuất file thành công. Bạn có muốn làm mới danh sách?')) {
                window.location.reload();
            }
        }, 1000);
    }, 2000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Update total on checkbox change
    document.querySelectorAll('.part-checkbox').forEach(cb => {
        cb.addEventListener('change', updateTotal);
    });
    
    // Update total on quantity change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', function() {
            calculateAmount(this);
        });
    });
    
    updateTotal();
});
</script>

<?php require_once '../../includes/footer.php'; ?>