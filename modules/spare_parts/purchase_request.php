<?php
/**
 * Purchase Request Page
 * /modules/spare_parts/purchase_request.php
 */

require_once 'config.php';

// Check permission
requirePermission('spare_parts', 'purchase_request');

$pageTitle = 'Đề xuất mua hàng';
$currentModule = 'spare_parts';
$moduleCSS = 'bom';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => 'Đề xuất mua hàng', 'url' => '']
];

// Get spare part if specified
$sparePartId = intval($_GET['spare_part_id'] ?? 0);
$selectedPart = null;
if ($sparePartId) {
    $selectedPart = $db->fetch("
        SELECT sp.*, COALESCE(oh.Onhand, 0) as current_stock
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE sp.id = ? AND sp.is_active = 1
    ", [$sparePartId]);
}

require_once '../../includes/header.php';

// Get reorder list
$reorderList = getReorderList();
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Create Purchase Request Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Tạo đề xuất mua hàng
                </h5>
            </div>
            <div class="card-body">
                <form id="purchaseRequestForm" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="item_code" class="form-label">Chọn vật tư <span class="text-danger">*</span></label>
                            <select id="item_code" name="item_code" class="form-select" required>
                                <option value="">-- Chọn vật tư --</option>
                                <?php foreach ($reorderList as $item): ?>
                                    <option value="<?php echo htmlspecialchars($item['item_code']); ?>"
                                            data-current-stock="<?php echo $item['current_stock']; ?>"
                                            data-min-stock="<?php echo $item['min_stock']; ?>"
                                            data-suggested-qty="<?php echo $item['suggested_qty']; ?>"
                                            data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                            data-cost="<?php echo $item['standard_cost']; ?>"
                                            <?php echo ($selectedPart && $selectedPart['item_code'] === $item['item_code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Vui lòng chọn vật tư</div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="requested_qty" class="form-label">Số lượng đề xuất <span class="text-danger">*</span></label>
                            <input type="number" id="requested_qty" name="requested_qty" class="form-control" 
                                   min="1" step="0.01" required 
                                   value="<?php echo $selectedPart ? $selectedPart['suggested_qty'] ?? $selectedPart['min_stock'] : ''; ?>">
                            <div class="invalid-feedback">Vui lòng nhập số lượng</div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="priority" class="form-label">Độ ưu tiên</label>
                            <select id="priority" name="priority" class="form-select">
                                <option value="Medium">Trung bình</option>
                                <option value="High">Cao</option>
                                <option value="Critical">Nghiêm trọng</option>
                                <option value="Low">Thấp</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Lý do đề xuất</label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" 
                                  placeholder="Nhập lý do cần mua vật tư này..."></textarea>
                    </div>
                    
                    <div class="mb-3" id="stockInfo" style="display: none;">
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Tồn kho hiện tại:</strong>
                                    <span id="currentStock">0</span> <span id="stockUnit"></span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Mức tối thiểu:</strong>
                                    <span id="minStock">0</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Chi phí ước tính:</strong>
                                    <span id="estimatedCost" class="text-success">0 VNĐ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Gửi đề xuất
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Hủy
                    </a>
                </form>
            </div>
        </div>
        
        <!-- Recent Purchase Requests -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>
                    Đề xuất gần đây
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mã đề xuất</th>
                                <th>Vật tư</th>
                                <th>Số lượng</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentRequests = $db->fetchAll("
                                SELECT pr.*, sp.item_name 
                                FROM purchase_requests pr
                                JOIN spare_parts sp ON pr.item_code = sp.item_code
                                ORDER BY pr.created_at DESC 
                                LIMIT 10
                            ");
                            ?>
                            <?php if (empty($recentRequests)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">
                                    Chưa có đề xuất nào
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_code']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($request['item_code']); ?>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($request['item_name']); ?></small>
                                </td>
                                <td><?php echo number_format($request['requested_qty'], 2); ?> <?php echo htmlspecialchars($request['unit']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPurchaseStatusClass($request['status']); ?>">
                                        <?php echo getPurchaseStatusText($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($request['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Reorder List -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    Vật tư cần đặt hàng (<?php echo count($reorderList); ?>)
                </h6>
            </div>
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($reorderList)): ?>
                <div class="p-3 text-center text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <p>Tất cả vật tư đều đủ số lượng</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($reorderList as $item): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['item_code']); ?></h6>
                                <p class="mb-1 small"><?php echo htmlspecialchars($item['item_name']); ?></p>
                                <div class="row small text-muted">
                                    <div class="col-6">Tồn: <?php echo number_format($item['current_stock'], 1); ?></div>
                                    <div class="col-6">Min: <?php echo number_format($item['min_stock'], 0); ?></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($item['is_critical']): ?>
                                <span class="badge bg-danger mb-1">Critical</span>
                                <?php endif; ?>
                                <div class="small">
                                    Đề xuất: <strong><?php echo number_format($item['suggested_qty'], 0); ?></strong>
                                </div>
                                <div class="small text-success">
                                    <?php echo formatVND($item['estimated_cost']); ?>
                                </div>
                            </div>
                        </div>
                        <button onclick="selectItem('<?php echo htmlspecialchars($item['item_code']); ?>')" 
                                class="btn btn-outline-primary btn-sm mt-2">
                            <i class="fas fa-plus me-1"></i>Chọn
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="p-3 border-top">
                    <strong>Tổng chi phí ước tính: 
                        <span class="text-success">
                            <?php echo formatVND(array_sum(array_column($reorderList, 'estimated_cost'))); ?>
                        </span>
                    </strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Handle item selection
document.getElementById('item_code').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        document.getElementById('requested_qty').value = option.dataset.suggestedQty || option.dataset.minStock;
        updateStockInfo(option);
        document.getElementById('stockInfo').style.display = 'block';
    } else {
        document.getElementById('stockInfo').style.display = 'none';
    }
});

// Handle quantity change
document.getElementById('requested_qty').addEventListener('input', function() {
    const option = document.getElementById('item_code').options[document.getElementById('item_code').selectedIndex];
    if (option.value) {
        updateStockInfo(option);
    }
});

function updateStockInfo(option) {
    const currentStock = parseFloat(option.dataset.currentStock || 0);
    const minStock = parseFloat(option.dataset.minStock || 0);
    const unit = option.dataset.unit || '';
    const cost = parseFloat(option.dataset.cost || 0);
    const requestedQty = parseFloat(document.getElementById('requested_qty').value || 0);
    
    document.getElementById('currentStock').textContent = currentStock.toFixed(1);
    document.getElementById('minStock').textContent = minStock.toFixed(0);
    document.getElementById('stockUnit').textContent = unit;
    document.getElementById('estimatedCost').textContent = formatVND(cost * requestedQty);
}

function selectItem(itemCode) {
    document.getElementById('item_code').value = itemCode;
    document.getElementById('item_code').dispatchEvent(new Event('change'));
    document.getElementById('requested_qty').focus();
}

// Form submission
document.getElementById('purchaseRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'create');
    
    CMMS.ajax({
        url: 'api/purchase_request.php',
        method: 'POST',
        body: formData,
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
});

function formatVND(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        minimumFractionDigits: 0
    }).format(amount);
}

function getPurchaseStatusClass(status) {
    const classes = {
        'pending': 'bg-warning',
        'approved': 'bg-success',
        'rejected': 'bg-danger',
        'ordered': 'bg-info',
        'completed': 'bg-dark'
    };
    return classes[status] || 'bg-secondary';
}

function getPurchaseStatusText(status) {
    const texts = {
        'pending': 'Chờ duyệt',
        'approved': 'Đã duyệt',
        'rejected': 'Từ chối',
        'ordered': 'Đã đặt hàng',
        'completed': 'Hoàn thành'
    };
    return texts[status] || status;
}

// Auto-select item if specified in URL
<?php if ($selectedPart): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('item_code').dispatchEvent(new Event('change'));
});
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>