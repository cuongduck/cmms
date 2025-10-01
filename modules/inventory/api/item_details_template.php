<?php
// Helper functions
function safeFormat($value, $decimals = 0) {
    return number_format($value ?? 0, $decimals);
}

function getStockQuantityClass($item) {
    $onhand = $item['Onhand'] ?? 0;
    if ($onhand <= 0) return 'text-danger';
    if (!empty($item['min_stock']) && $onhand < $item['min_stock']) return 'text-warning';
    return 'text-success';
}

function getStockStatusClass($status) {
    switch ($status) {
        case 'Hết hàng': return 'bg-danger';
        case 'Thiếu hàng': return 'bg-warning';
        case 'Dư thừa': return 'bg-info';
        default: return 'bg-success';
    }
}

function getPriorityClass($priority) {
    switch ($priority) {
        case 'Critical': return 'bg-danger';
        case 'High': return 'bg-warning';
        case 'Medium': return 'bg-info';
        case 'Low': return 'bg-secondary';
        default: return 'bg-primary';
    }
}

function getTransactionTypeClass($type) {
    $classes = [
        'Receipt' => 'bg-success',
        'Issue' => 'bg-danger', 
        'Transfer' => 'bg-info',
        'Adjustment' => 'bg-warning',
        'Return' => 'bg-secondary'
    ];
    return $classes[$type] ?? 'bg-primary';
}
?>

<div class="row">
    <!-- Basic Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="40%"><strong>Mã vật tư:</strong></td>
                        <td><code class="text-primary"><?php echo htmlspecialchars($item['ItemCode']); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Tên vật tư:</strong></td>
                        <td><?php echo htmlspecialchars($item['Itemname']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Vị trí kho:</strong></td>
                        <td><?php echo htmlspecialchars($item['Locator'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Số Lot:</strong></td>
                        <td><?php echo htmlspecialchars($item['Lotnumber'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Đơn vị tính:</strong></td>
                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['UOM']); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Loại vật tư:</strong></td>
                        <td>
                            <span class="badge <?php echo $item['bom_status'] === 'Trong BOM' ? 'bg-primary' : 'bg-secondary'; ?>">
                                <?php echo $item['bom_status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($item['category']): ?>
                    <tr>
                        <td><strong>Phân loại:</strong></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Stock Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-warehouse me-2"></i>Thông tin tồn kho
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="h4 mb-1 <?php echo getStockQuantityClass($item); ?>">
                                <?php echo safeFormat($item['Onhand'], 2); ?>
                            </div>
                            <small class="text-muted">Tồn kho hiện tại</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="h4 mb-1">
                                <?php echo safeFormat($item['min_stock'] ?? 0, 0); ?>
                            </div>
                            <small class="text-muted">Tồn kho tối thiểu</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="h4 mb-1">
                                <?php echo safeFormat($item['max_stock'] ?? 0, 0); ?>
                            </div>
                            <small class="text-muted">Tồn kho tối đa</small>
                        </div>
                    </div>
                </div>
                
                <table class="table table-borderless table-sm">
                    <tr>
                        <td width="40%"><strong>Trạng thái:</strong></td>
                        <td>
                            <span class="badge <?php echo getStockStatusClass($item['stock_status']); ?>">
                                <?php echo $item['stock_status']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Đơn giá:</strong></td>
                        <td class="fw-bold"><?php echo safeFormat($item['Price'] ?? 0, 0); ?> đ</td>
                    </tr>
                    <tr>
                        <td><strong>Tổng giá trị:</strong></td>
                        <td class="fw-bold text-primary"><?php echo safeFormat($item['OH_Value'] ?? 0, 0); ?> đ</td>
                    </tr>
                    <?php if ($item['lead_time']): ?>
                    <tr>
                        <td><strong>Lead time:</strong></td>
                        <td><?php echo $item['lead_time']; ?> ngày</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Summary -->
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>Tổng quan giao dịch
        </h6>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-2">
                <div class="p-2">
                    <div class="h5 mb-1 text-info"><?php echo safeFormat($summary['total_transactions']); ?></div>
                    <small class="text-muted">Tổng giao dịch</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="p-2">
                    <div class="h5 mb-1 text-success"><?php echo safeFormat($summary['total_in'], 2); ?></div>
                    <small class="text-muted">Tổng nhập</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="p-2">
                    <div class="h5 mb-1 text-danger"><?php echo safeFormat($summary['total_out'], 2); ?></div>
                    <small class="text-muted">Tổng xuất</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2">
                    <div class="h5 mb-1 text-success"><?php echo safeFormat($summary['total_value_in'], 0); ?> đ</div>
                    <small class="text-muted">Giá trị nhập</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-2">
                    <div class="h5 mb-1 text-danger"><?php echo safeFormat($summary['total_value_out'], 0); ?> đ</div>
                    <small class="text-muted">Giá trị xuất</small>
                </div>
            </div>
        </div>
        
        <?php if ($summary['last_transaction_date']): ?>
        <div class="text-center mt-2">
            <small class="text-muted">
                Giao dịch cuối: <?php echo date('d/m/Y H:i', strtotime($summary['last_transaction_date'])); ?>
            </small>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Transactions -->
<?php if (!empty($recentTransactions)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-history me-2"></i>Giao dịch gần đây
        </h6>
        <button type="button" class="btn btn-sm btn-outline-primary" 
                onclick="showTransactionHistory('<?php echo $item['ItemCode']; ?>')">
            Xem tất cả
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ngày</th>
                        <th>Loại</th>
                        <th class="text-end">Số lượng</th>
                        <th class="text-end">Giá trị</th>
                        <th>Lý do</th>
                        <th>Người yêu cầu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $trans): ?>
                    <tr>
                        <td>
                            <small><?php echo date('d/m/Y H:i', strtotime($trans['TransactionDate'])); ?></small>
                        </td>
                        <td>
                            <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType']); ?>">
                                <?php echo htmlspecialchars($trans['TransactionType'] ?? '-'); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="<?php echo ($trans['TransactedQty'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo (($trans['TransactedQty'] ?? 0) > 0 ? '+' : '') . safeFormat($trans['TransactedQty'], 2); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="<?php echo ($trans['TransactedQty'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo safeFormat($trans['TotalAmount'] ?? 0, 0); ?> đ
                            </span>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($trans['Reason'] ?? '-'); ?></small>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($trans['Requester'] ?? '-'); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function showTransactionHistory(itemCode) {
    // Redirect to transactions module with search
    window.open(`/modules/transactions/?search=${encodeURIComponent(itemCode)}&search_all=1`, '_blank');
}
</script>