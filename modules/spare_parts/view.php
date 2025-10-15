<?php
/**
 * Spare Parts View Page - FIXED VERSION
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

// Get spare part details with annual usage calculation
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
                   WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point 
                   THEN GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock)
                   ELSE 0
               END as suggested_order_qty,
               CASE 
                   WHEN sp.estimated_annual_usage > 0 AND COALESCE(oh.Onhand, 0) > 0 
                   THEN ROUND((COALESCE(oh.Onhand, 0) / sp.estimated_annual_usage) * 12, 1)
                   ELSE NULL
               END as months_remaining
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        LEFT JOIN users u1 ON sp.manager_user_id = u1.id
        WHERE sp.id = ?";

$part = $db->fetch($sql, [$id]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chi tiết: ' . htmlspecialchars($part['item_name']);
$currentModule = 'spare_parts';
$moduleCSS = 'bom';
$moduleJS = 'spare-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => htmlspecialchars($part['item_name']), 'url' => '']
];

// ===== FIX: Phân quyền đúng với user_role =====
$pageActions = '';
$userRole = $_SESSION['user_role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$canEdit = false;
$canDelete = false;

if ($userRole === 'Admin') {
    $canEdit = true;
    $canDelete = true;
} elseif ($part['manager_user_id'] == $userId) {
    $canEdit = true;
    $canDelete = true;
}

// Hiển thị nút
if ($canEdit) {
    $pageActions .= '<a href="edit.php?id=' . $id . '" class="btn btn-warning">
        <i class="fas fa-edit me-2"></i>Chỉnh sửa
    </a> ';
}

if ($canDelete) {
    $pageActions .= '<button onclick="deleteSparePart(' . $id . ', \'' . htmlspecialchars($part['item_code']) . '\')" class="btn btn-danger">
        <i class="fas fa-trash me-2"></i>Xóa
    </button>';
}

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

// Calculate monthly usage
$monthlyUsage = $part['estimated_annual_usage'] > 0 ? $part['estimated_annual_usage'] / 12 : 0;
$weeklyUsage = $part['estimated_annual_usage'] > 0 ? $part['estimated_annual_usage'] / 52 : 0;
?>

<style>
.info-card {
    border-left: 4px solid #0d6efd;
    transition: transform 0.2s;
}
.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.stat-box {
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}
.stat-box h4 {
    margin: 0.5rem 0;
    color: #0d6efd;
}
.progress-marker {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
}
</style>

<!-- Part Header -->
<div class="bom-summary mb-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <h3 class="mb-0"><?php echo htmlspecialchars($part['item_name']); ?></h3>
                    <?php if ($part['is_critical']): ?>
                    <span class="badge bg-warning text-dark">CRITICAL</span>
                    <?php endif; ?>
                    <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?>">
                        <?php echo getStockStatusText($part['stock_status']); ?>
                    </span>
                </div>
                
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div>
                        <i class="fas fa-barcode me-1"></i>
                        <strong>Mã:</strong> 
                        <span class="part-code"><?php echo htmlspecialchars($part['item_code']); ?></span>
                    </div>
                    <div>
                        <i class="fas fa-tag me-1"></i>
                        <strong>Danh mục:</strong> 
                        <?php echo htmlspecialchars($part['category'] ?? 'Chưa phân loại'); ?>
                    </div>
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
                <p class="mb-2">
                    <strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($part['description'])); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <?php echo $pageActions; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <div class="col-lg-8">
        <!-- Stock Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <small class="text-muted">Tồn kho hiện tại</small>
                    <h4><?php echo number_format($part['current_stock'], 2); ?></h4>
                    <small><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <small class="text-muted">Giá trị tồn</small>
                    <h4><?php echo formatVND($part['stock_value']); ?></h4>
                    <small>Đơn giá: <?php echo formatVND($part['current_price']); ?></small>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-box <?php echo $part['suggested_order_qty'] > 0 ? 'border-warning' : ''; ?>">
                    <small class="text-muted">Đề xuất đặt hàng</small>
                    <?php if ($part['suggested_order_qty'] > 0): ?>
                        <h4 class="text-warning"><?php echo number_format($part['suggested_order_qty'], 0); ?></h4>
                        <small><?php echo htmlspecialchars($part['unit']); ?></small>
                    <?php else: ?>
                        <h4 class="text-success">-</h4>
                        <small>Đủ hàng</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <small class="text-muted">Thời gian còn lại</small>
                    <?php if ($part['months_remaining']): ?>
                        <h4 class="<?php echo $part['months_remaining'] < 3 ? 'text-danger' : ($part['months_remaining'] < 6 ? 'text-warning' : 'text-success'); ?>">
                            <?php echo number_format($part['months_remaining'], 1); ?>
                        </h4>
                        <small>tháng</small>
                    <?php else: ?>
                        <h4 class="text-muted">-</h4>
                        <small>N/A</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stock Level Progress -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Mức tồn kho
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Hiện tại: <strong><?php echo number_format($part['current_stock'], 2); ?></strong></span>
                    <span>Tối đa: <strong><?php echo number_format($part['max_stock'], 0); ?></strong></span>
                </div>
                <div class="position-relative">
                    <div class="progress" style="height: 25px;">
                        <?php 
                        $percentage = $part['max_stock'] > 0 ? ($part['current_stock'] / $part['max_stock']) * 100 : 0;
                        $progressClass = 'bg-success';
                        if ($part['current_stock'] <= $part['reorder_point']) $progressClass = 'bg-danger';
                        elseif ($part['current_stock'] < $part['min_stock']) $progressClass = 'bg-warning';
                        ?>
                        <div class="progress-bar <?php echo $progressClass; ?>" 
                             style="width: <?php echo min(100, $percentage); ?>%">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </div>
                    <!-- Markers -->
                    <?php if ($part['max_stock'] > 0): ?>
                    <div class="progress-marker bg-danger" 
                         style="left: <?php echo ($part['min_stock'] / $part['max_stock']) * 100; ?>%;" 
                         title="Min: <?php echo number_format($part['min_stock'], 0); ?>"></div>
                    <div class="progress-marker bg-warning" 
                         style="left: <?php echo ($part['reorder_point'] / $part['max_stock']) * 100; ?>%;" 
                         title="Reorder: <?php echo number_format($part['reorder_point'], 0); ?>"></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-danger">
                        <i class="fas fa-flag"></i> Min: <?php echo number_format($part['min_stock'], 0); ?>
                    </small>
                    <small class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i> Reorder: <?php echo number_format($part['reorder_point'], 0); ?>
                    </small>
                    <small class="text-success">
                        <i class="fas fa-check"></i> Max: <?php echo number_format($part['max_stock'], 0); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Usage Forecast -->
        <?php if ($part['estimated_annual_usage'] > 0): ?>
        <div class="card mb-4 info-card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Dự báo sử dụng
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-2">
                            <i class="fas fa-calendar-year fa-2x text-primary mb-2"></i>
                            <h5><?php echo number_format($part['estimated_annual_usage'], 0); ?></h5>
                            <small class="text-muted"><?php echo $part['unit']; ?>/năm</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2">
                            <i class="fas fa-calendar fa-2x text-info mb-2"></i>
                            <h5><?php echo number_format($monthlyUsage, 2); ?></h5>
                            <small class="text-muted"><?php echo $part['unit']; ?>/tháng</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2">
                            <i class="fas fa-calendar-week fa-2x text-success mb-2"></i>
                            <h5><?php echo number_format($weeklyUsage, 2); ?></h5>
                            <small class="text-muted"><?php echo $part['unit']; ?>/tuần</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <?php if ($part['months_remaining']): ?>
                                <h5><?php echo number_format($part['months_remaining'], 1); ?></h5>
                                <small class="text-muted">tháng còn lại</small>
                            <?php else: ?>
                                <h5>-</h5>
                                <small class="text-muted">Không xác định</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Management & Supplier Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Quản lý & Nhà cung cấp
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Người quản lý</h6>
                        <?php if ($part['manager_name']): ?>
                            <p><i class="fas fa-user-tie me-2"></i><strong><?php echo htmlspecialchars($part['manager_name']); ?></strong></p>
                        <?php else: ?>
                            <p class="text-muted"><i class="fas fa-user-slash me-2"></i>Chưa phân công</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Nhà cung cấp</h6>
                        <?php if ($part['supplier_name']): ?>
                            <p><i class="fas fa-industry me-2"></i><strong><?php echo htmlspecialchars($part['supplier_name']); ?></strong></p>
                            <p><small>Mã: <?php echo htmlspecialchars($part['supplier_code']); ?></small></p>
                            <p><small>Lead time: <?php echo $part['lead_time_days']; ?> ngày</small></p>
                        <?php else: ?>
                            <p class="text-muted"><i class="fas fa-question-circle me-2"></i>Chưa có thông tin</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Lịch sử giao dịch
                </h6>
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
                                    Không có giao dịch
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentTransactions as $trans): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($trans['TransactionDate'])); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($trans['TransactionType'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="<?php echo ($trans['TransactedQty'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($trans['TransactedQty'] ?? 0, 2); ?> <?php echo htmlspecialchars($trans['UOM'] ?? ''); ?>
                                </td>
                                <td><?php echo formatVND($trans['TotalAmount'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($trans['Reason'] ?? '-'); ?></td>
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
                    <?php if ($canEdit): ?>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-warning">
                        <i class="fas fa-edit me-2"></i>Chỉnh sửa
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($part['suggested_order_qty'] > 0): ?>
                    <a href="purchase_request.php?spare_part_id=<?php echo $id; ?>" class="btn btn-outline-success">
                        <i class="fas fa-shopping-cart me-2"></i>Tạo đề xuất mua hàng
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="copyItemCode()" class="btn btn-outline-info">
                        <i class="fas fa-copy me-2"></i>Copy mã vật tư
                    </button>
                    
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="fas fa-print me-2"></i>In thông tin
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Technical Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Thông tin kỹ thuật
                </h6>
            </div>
            <div class="card-body">
                <?php if ($part['specifications']): ?>
                    <p><strong>Thông số kỹ thuật:</strong></p>
                    <p class="small"><?php echo nl2br(htmlspecialchars($part['specifications'])); ?></p>
                    <hr>
                <?php endif; ?>
                
                <?php if ($part['notes']): ?>
                    <p><strong>Ghi chú:</strong></p>
                    <p class="small"><?php echo nl2br(htmlspecialchars($part['notes'])); ?></p>
                    <hr>
                <?php endif; ?>
                
                <div class="row text-center small">
                    <div class="col-6">
                        <div class="text-muted">Tạo lúc</div>
                        <div><?php echo date('d/m/Y H:i', strtotime($part['created_at'])); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Cập nhật</div>
                        <div><?php echo date('d/m/Y H:i', strtotime($part['updated_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Alert -->
        <?php if ($part['stock_status'] === 'Reorder' || $part['stock_status'] === 'Low' || $part['stock_status'] === 'Out of Stock'): ?>
        <div class="alert alert-<?php echo $part['stock_status'] === 'Out of Stock' ? 'danger' : 'warning'; ?>">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Cảnh báo tồn kho</h6>
            <p class="mb-2"><?php echo getStockStatusText($part['stock_status']); ?></p>
            <p class="mb-0 small">Đề xuất đặt hàng: <strong><?php echo number_format($part['suggested_order_qty'], 0); ?> <?php echo $part['unit']; ?></strong></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
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

function deleteSparePart(id, itemCode) {
    if (!confirm(`⚠️ CẢNH BÁO: Bạn có CHẮC CHẮN muốn XÓA VĨNH VIỄN spare part "${itemCode}"?\n\n❌ Dữ liệu sẽ bị XÓA HOÀN TOÀN và KHÔNG THỂ KHÔI PHỤC!\n\nNhấn OK để xác nhận xóa vĩnh viễn.`)) {
        return;
    }
    
    if (!confirm(`Xác nhận lần cuối: Xóa vĩnh viễn "${itemCode}"?`)) {
        return;
    }
    
    CMMS.ajax({
        url: 'api/spare_parts.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'delete',
            id: id
        }),
        success: function(data) {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(error, response) {
            console.error('Delete error:', error);
            if (response && response.message) {
                CMMS.showToast(response.message, 'error');
            } else {
                CMMS.showToast('Không thể xóa spare part', 'error');
            }
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>