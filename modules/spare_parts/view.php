<?php
/**
 * Spare Parts View Page
 * /modules/spare_parts/view.php
 */

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

// Check permission
requirePermission('spare_parts', 'view');

// Get spare part details
$sql = "SELECT sp.*, 
               COALESCE(oh.Onhand, 0) as current_stock,
               COALESCE(oh.UOM, sp.unit) as stock_unit,
               COALESCE(oh.OH_Value, 0) as stock_value,
               COALESCE(oh.Price, sp.standard_cost) as current_price,
               u1.full_name as manager_name,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN 'Reorder'
                   WHEN COALESCE(oh.Onhand, 0) < sp.min_stock THEN 'Low'
                   WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                   ELSE 'OK'
               END as stock_status,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock)
                   ELSE 0
               END as suggested_order_qty
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        LEFT JOIN users u1 ON sp.manager_user_id = u1.id
        WHERE sp.id = ? AND sp.is_active = 1";

$part = $db->fetch($sql, [$id]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chi tiết: ' . htmlspecialchars($part['item_name']);
$currentModule = 'spare_parts';
$moduleCSS = 'bom';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => htmlspecialchars($part['item_name']), 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('spare_parts', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $id . '" class="btn btn-warning">
        <i class="fas fa-edit me-2"></i>Chỉnh sửa
    </a> ';
}

if (hasPermission('spare_parts', 'delete')) {
    $pageActions .= '<button onclick="deleteSparePart(' . $id . ', \'' . htmlspecialchars($part['item_code']) . '\')" class="btn btn-danger">
        <i class="fas fa-trash me-2"></i>Xóa
    </button> ';
}

$pageActions .= '<div class="btn-group">
    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-download me-2"></i>Xuất dữ liệu
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="exportSparePart(' . $id . ', \'pdf\')">
            <i class="fas fa-file-pdf me-2"></i>PDF
        </a></li>
    </ul>
</div>';

require_once '../../includes/header.php';

// Get recent transactions
$recentTransactions = $db->fetchAll(
    "SELECT * FROM transaction 
     WHERE ItemCode = ? 
     ORDER BY TransactionDate DESC 
     LIMIT 10",
    [$part['item_code']]
);

// Get purchase requests
$purchaseRequests = $db->fetchAll(
    "SELECT * FROM purchase_requests 
     WHERE item_code = ? 
     ORDER BY created_at DESC 
     LIMIT 5",
    [$part['item_code']]
);
?>

<!-- Part Header -->
<div class="bom-summary">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <h3><?php echo htmlspecialchars($part['item_name']); ?></h3>
                    <?php if ($part['is_critical']): ?>
                    <span class="badge bg-warning">CRITICAL</span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div>
                        <i class="fas fa-barcode me-1"></i>
                        <strong>Mã vật tư:</strong> 
                        <span class="part-code"><?php echo htmlspecialchars($part['item_code']); ?></span>
                    </div>
                    <?php if ($part['category']): ?>
                    <div>
                        <i class="fas fa-tag me-1"></i>
                        <strong>Danh mục:</strong> 
                        <?php echo htmlspecialchars($part['category']); ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <i class="fas fa-balance-scale me-1"></i>
                        <strong>Đơn vị:</strong> 
                        <?php echo htmlspecialchars($part['unit']); ?>
                    </div>
                    <?php if ($part['storage_location']): ?>
                    <div>
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <strong>Vị trí:</strong> 
                        <?php echo htmlspecialchars($part['storage_location']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($part['description']): ?>
                <p class="mb-2 opacity-90">
                    <strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($part['description'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($part['specifications']): ?>
                <p class="mb-2 opacity-90">
                    <strong>Thông số kỹ thuật:</strong> <?php echo nl2br(htmlspecialchars($part['specifications'])); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="d-flex justify-content-end gap-2">
                    <?php echo $pageActions; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row mt-4">
    <div class="col-lg-8">
        <!-- Stock Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-warehouse me-2"></i>
                    Thông tin tồn kho
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 h-100 text-center">
                            <small class="text-muted">Tồn kho hiện tại</small>
                            <h4 class="mb-1"><?php echo number_format($part['current_stock'], 2); ?></h4>
                            <small><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 h-100 text-center">
                            <small class="text-muted">Trạng thái</small>
                            <div class="mb-2">
                                <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?> fs-6">
                                    <?php echo getStockStatusText($part['stock_status']); ?>
                                </span>
                            </div>
                            <small>Min: <?php echo number_format($part['min_stock'], 0); ?></small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 h-100 text-center">
                            <small class="text-muted">Giá trị tồn kho</small>
                            <h4 class="mb-1"><?php echo formatVND($part['stock_value']); ?></h4>
                            <small>Đơn giá: <?php echo formatVND($part['current_price']); ?></small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 h-100 text-center">
                            <small class="text-muted">Đề xuất đặt hàng</small>
                            <?php if ($part['suggested_order_qty'] > 0): ?>
                                <h4 class="mb-1 text-warning"><?php echo number_format($part['suggested_order_qty'], 0); ?></h4>
                                <small><?php echo htmlspecialchars($part['unit']); ?></small>
                            <?php else: ?>
                                <h4 class="mb-1 text-muted">-</h4>
                                <small>Không cần</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Stock Level Progress -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Mức tồn kho</small>
                        <small><?php echo number_format($part['current_stock'], 2); ?> / <?php echo number_format($part['max_stock'], 0); ?></small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <?php 
                        $percentage = $part['max_stock'] > 0 ? ($part['current_stock'] / $part['max_stock']) * 100 : 0;
                        $progressClass = 'bg-success';
                        if ($part['current_stock'] <= $part['reorder_point']) $progressClass = 'bg-danger';
                        elseif ($part['current_stock'] < $part['min_stock']) $progressClass = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo min(100, $percentage); ?>%"></div>
                        <!-- Markers for min and reorder points -->
                        <div class="position-absolute" style="left: <?php echo $part['max_stock'] > 0 ? ($part['min_stock'] / $part['max_stock']) * 100 : 0; ?>%; top: 0; bottom: 0; width: 2px; background-color: #dc3545;"></div>
                        <div class="position-absolute" style="left: <?php echo $part['max_stock'] > 0 ? ($part['reorder_point'] / $part['max_stock']) * 100 : 0; ?>%; top: 0; bottom: 0; width: 2px; background-color: #ffc107;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">0</small>
                        <small class="text-danger">Min: <?php echo number_format($part['min_stock'], 0); ?></small>
                        <small class="text-warning">Reorder: <?php echo number_format($part['reorder_point'], 0); ?></small>
                        <small class="text-muted">Max: <?php echo number_format($part['max_stock'], 0); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>
                    Thông tin quản lý & Nhà cung cấp
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
             <div class="col-md-6">
    <h6>Người quản lý</h6>
    <?php if ($part['manager_name']): ?>
        <p><strong><?php echo htmlspecialchars($part['manager_name']); ?></strong></p>
    <?php else: ?>
        <p class="text-muted">Chưa được phân công</p>
    <?php endif; ?>
</div>
                    
                    <div class="col-md-6">
                        <h6>Nhà cung cấp chính</h6>
                        <?php if ($part['supplier_name']): ?>
                            <p><strong><?php echo htmlspecialchars($part['supplier_name']); ?></strong></p>
                            <p><small>Mã: <?php echo htmlspecialchars($part['supplier_code']); ?></small></p>
                            <p><small>Lead time: <?php echo $part['lead_time_days']; ?> ngày</small></p>
                        <?php else: ?>
                            <p class="text-muted">Chưa có thông tin nhà cung cấp</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Lịch sử giao dịch gần đây
                </h5>
                <a href="/modules/inventory/transactions.php?item_code=<?php echo urlencode($part['item_code']); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Xem tất cả
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Loại</th>
                                <th>Số lượng</th>
                                <th>Giá trị</th>
                                <th>Lý do</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">
                                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                    Không có giao dịch gần đây
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentTransactions as $trans): ?>
                            <tr>
                                <td><?php echo formatDateTime($trans['TransactionDate']); ?></td>
                                <td>
                                    <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($trans['TransactionType'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="<?php echo ($trans['TransactedQty'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($trans['TransactedQty'] ?? 0, 2); ?> <?php echo htmlspecialchars($trans['UOM'] ?? ''); ?>
                                </td>
                                <td><?php echo formatVND($trans['TotalAmount'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($trans['Reason'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Purchase Requests -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Lịch sử đề xuất mua hàng
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mã đề xuất</th>
                                <th>Số lượng</th>
                                <th>Ngày tạo</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchaseRequests)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">
                                    Chưa có đề xuất mua hàng nào
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($purchaseRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_code']); ?></td>
                                <td><?php echo number_format($request['requested_qty'], 2); ?> <?php echo htmlspecialchars($request['unit']); ?></td>
                                <td><?php echo formatDate($request['created_at']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPurchaseStatusClass($request['status']); ?>">
                                        <?php echo getPurchaseStatusText($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="purchase_request.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
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
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác nhanh
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('spare_parts', 'edit')): ?>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-edit me-2"></i>Chỉnh sửa
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($part['suggested_order_qty'] > 0): ?>
                    <a href="purchase_request.php?spare_part_id=<?php echo $id; ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-shopping-cart me-2"></i>Tạo đề xuất mua hàng
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="copyItemCode()" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-copy me-2"></i>Copy mã vật tư
                    </button>
                    
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-print me-2"></i>In thông tin
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Technical Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin kỹ thuật
                </h6>
            </div>
            <div class="card-body">
                <?php if ($part['specifications']): ?>
                    <p><strong>Thông số kỹ thuật:</strong></p>
                    <p class="small"><?php echo nl2br(htmlspecialchars($part['specifications'])); ?></p>
                <?php endif; ?>
                
                <?php if ($part['notes']): ?>
                    <p><strong>Ghi chú:</strong></p>
                    <p class="small"><?php echo nl2br(htmlspecialchars($part['notes'])); ?></p>
                <?php endif; ?>
                
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <small class="text-muted">Tạo lúc</small>
                        <div class="small"><?php echo formatDateTime($part['created_at']); ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Cập nhật</small>
                        <div class="small"><?php echo formatDateTime($part['updated_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportSparePart(id, format) {
    const params = new URLSearchParams({
        action: 'export_spare_part',
        format: format,
        id: id
    });
    
    window.open('api/export.php?' + params, '_blank');
}

function copyItemCode() {
    const itemCode = '<?php echo $part['item_code']; ?>';
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(itemCode).then(() => {
            CMMS.showToast('Đã copy mã vật tư: ' + itemCode, 'success');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = itemCode;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        CMMS.showToast('Đã copy mã vật tư: ' + itemCode, 'success');
    }
}

// Helper functions for transaction types
function getTransactionTypeClass(type) {
    const classes = {
        'IN': 'bg-success',
        'OUT': 'bg-danger',
        'ADJUST': 'bg-warning',
        'TRANSFER': 'bg-info'
    };
    return classes[type] || 'bg-secondary';
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
</script>

<?php require_once '../../includes/footer.php'; ?>