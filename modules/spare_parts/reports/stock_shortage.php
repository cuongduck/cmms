<?php
/**
 * Stock Shortage Report
 * /modules/spare_parts/reports/stock_shortage.php
 */

require_once '../config.php';

// Check permission
requirePermission('spare_parts', 'view');

$pageTitle = 'Báo cáo thiếu hàng';
$currentModule = 'spare_parts';
$moduleCSS = 'bom';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => '../index.php'],
    ['title' => 'Báo cáo thiếu hàng', 'url' => '']
];

require_once '../../../includes/header.php';

// Get shortage data
$shortageData = $db->fetchAll("
    SELECT sp.*, 
           COALESCE(oh.Onhand, 0) as current_stock,
           COALESCE(oh.OH_Value, 0) as stock_value,
           u1.full_name as manager_name,
           GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock) as suggested_qty,
           (GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock) * sp.standard_cost) as estimated_cost,
           CASE 
               WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN 'Reorder'
               WHEN COALESCE(oh.Onhand, 0) < sp.min_stock THEN 'Low'
               WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
               ELSE 'OK'
           END as stock_status
    FROM spare_parts sp
    LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
    LEFT JOIN users u1 ON sp.manager_user_id = u1.id
    WHERE sp.is_active = 1 
    AND COALESCE(oh.Onhand, 0) <= sp.reorder_point
    ORDER BY sp.is_critical DESC, 
             CASE WHEN COALESCE(oh.Onhand, 0) = 0 THEN 1 ELSE 2 END,
             sp.item_code
");

$totalEstimatedCost = array_sum(array_column($shortageData, 'estimated_cost'));
$criticalCount = count(array_filter($shortageData, function($item) {
    return $item['is_critical'] == 1;
}));
$outOfStockCount = count(array_filter($shortageData, function($item) {
    return $item['current_stock'] == 0;
}));
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <h5>Tổng vật tư thiếu</h5>
                <h2><?php echo count($shortageData); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #dc3545, #c82333);">
            <div class="card-body">
                <h5>Vật tư Critical</h5>
                <h2><?php echo $criticalCount; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #fd7e14, #e8540e);">
            <div class="card-body">
                <h5>Hết hàng</h5>
                <h2><?php echo $outOfStockCount; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
            <div class="card-body">
                <h5>Chi phí ước tính</h5>
                <h2><?php echo formatVND($totalEstimatedCost); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Thao tác nhanh</h5>
            <div class="btn-group">
                <button onclick="exportReport('excel')" class="btn btn-success">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button onclick="exportReport('pdf')" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-2"></i>Xuất PDF
                </button>
                <a href="../purchase_request.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart me-2"></i>Tạo đề xuất mua hàng
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Shortage Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
            Danh sách vật tư thiếu hàng
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>Mã vật tư</th>
                        <th>Tên vật tư</th>
                        <th>Tồn kho</th>
                        <th>Min/Reorder</th>
                        <th>Trạng thái</th>
                        <th>Đề xuất mua</th>
                        <th>Chi phí</th>
                        <th>Người quản lý</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shortageData)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="text-success">
                                <i class="fas fa-check-circle fa-3x mb-2"></i>
                                <h5>Tuyệt vời! Tất cả vật tư đều đủ số lượng</h5>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($shortageData as $item): ?>
                    <tr class="<?php echo $item['is_critical'] ? 'table-danger' : ($item['current_stock'] == 0 ? 'table-warning' : ''); ?>">
                        <td>
                            <span class="part-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
                            <?php if ($item['is_critical']): ?>
                            <span class="badge bg-danger ms-1">Critical</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            <?php if ($item['description']): ?>
                            <small class="d-block text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <strong class="<?php echo $item['current_stock'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                <?php echo number_format($item['current_stock'], 2); ?>
                            </strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                        </td>
                        <td class="text-center">
                            <small>Min: <?php echo number_format($item['min_stock'], 0); ?></small>
                            <small class="d-block">Reorder: <?php echo number_format($item['reorder_point'], 0); ?></small>
                        </td>
                        <td>
                            <span class="badge <?php echo getStockStatusClass($item['stock_status']); ?>">
                                <?php echo getStockStatusText($item['stock_status']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <strong class="text-primary"><?php echo number_format($item['suggested_qty'], 0); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                        </td>
                        <td class="text-end">
                            <strong class="text-success"><?php echo formatVND($item['estimated_cost']); ?></strong>
                        </td>
                        <td>
                            <?php if ($item['manager_name']): ?>
                            <small><?php echo htmlspecialchars($item['manager_name']); ?></small>
                            <?php else: ?>
                            <small class="text-muted">Chưa phân công</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="../view.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../purchase_request.php?spare_part_id=<?php echo $item['id']; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-shopping-cart"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="6" class="text-end">Tổng chi phí ước tính:</th>
                        <th class="text-end"><?php echo formatVND($totalEstimatedCost); ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
function exportReport(format) {
    const params = new URLSearchParams({
        action: 'export_shortage_report',
        format: format
    });
    
    window.open('../api/export.php?' + params, '_blank');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>