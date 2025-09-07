<?php
/**
 * Item Details API
 * /modules/inventory/api/item_details.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('inventory', 'view');

$itemCode = $_GET['item_code'] ?? '';

if (empty($itemCode)) {
    jsonResponse(['success' => false, 'message' => 'Mã vật tư không hợp lệ'], 400);
}

try {
    // Get item details
    $sql = "SELECT 
        o.*,
        p.id as part_id,
        p.part_code,
        p.part_name,
        p.description as part_description,
        p.category,
        p.specifications,
        p.manufacturer,
        p.supplier_name,
        p.unit_price as part_unit_price,
        p.min_stock,
        p.max_stock,
        p.lead_time,
        p.notes as part_notes,
        CASE 
            WHEN p.id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status,
        CASE
            WHEN o.Onhand <= 0 THEN 'Hết hàng'
            WHEN p.min_stock > 0 AND o.Onhand < p.min_stock THEN 'Thiếu hàng'
            WHEN p.max_stock > 0 AND o.Onhand > p.max_stock THEN 'Dư thừa'
            ELSE 'Bình thường'
        END as stock_status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE o.ItemCode = ?";
    
    $item = $db->fetch($sql, [$itemCode]);
    
    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy vật tư'], 404);
    }
    
    // Get recent transactions
    $transactionSql = "SELECT * FROM transaction 
                      WHERE ItemCode = ? 
                      ORDER BY TransactionDate DESC 
                      LIMIT 10";
    $recentTransactions = $db->fetchAll($transactionSql, [$itemCode]);
    
    // Get transaction summary
    $summarySql = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN TransactedQty > 0 THEN TransactedQty ELSE 0 END) as total_in,
        SUM(CASE WHEN TransactedQty < 0 THEN ABS(TransactedQty) ELSE 0 END) as total_out,
        SUM(CASE WHEN TransactedQty > 0 THEN TotalAmount ELSE 0 END) as total_value_in,
        SUM(CASE WHEN TransactedQty < 0 THEN ABS(TotalAmount) ELSE 0 END) as total_value_out,
        MAX(TransactionDate) as last_transaction_date
    FROM transaction 
    WHERE ItemCode = ?";
    $summary = $db->fetch($summarySql, [$itemCode]);
    
    // Get BOM usage if applicable
    $bomUsage = [];
    if ($item['part_id']) {
        $bomSql = "SELECT 
            mb.bom_name,
            mb.bom_code,
            bi.quantity,
            bi.unit,
            bi.position,
            bi.priority,
            bi.maintenance_interval,
            mt.name as machine_type_name
        FROM bom_items bi
        JOIN machine_bom mb ON bi.bom_id = mb.id
        JOIN machine_types mt ON mb.machine_type_id = mt.id
        WHERE bi.part_id = ?
        ORDER BY mt.name, mb.bom_name";
        $bomUsage = $db->fetchAll($bomSql, [$item['part_id']]);
    }
    
    // Get suppliers if applicable
    $suppliers = [];
    if ($item['part_id']) {
        $supplierSql = "SELECT * FROM part_suppliers 
                       WHERE part_id = ? 
                       ORDER BY is_preferred DESC, unit_price ASC";
        $suppliers = $db->fetchAll($supplierSql, [$item['part_id']]);
    }
    
    ob_start();
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
                                    <?php echo number_format($item['Onhand'], 2); ?>
                                </div>
                                <small class="text-muted">Tồn kho hiện tại</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-1">
                                    <?php echo number_format($item['min_stock'] ?? 0, 0); ?>
                                </div>
                                <small class="text-muted">Tồn kho tối thiểu</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <div class="h4 mb-1">
                                    <?php echo number_format($item['max_stock'] ?? 0, 0); ?>
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
                            <td class="fw-bold"><?php echo number_format($item['Price'] ?? 0, 0); ?> đ</td>
                        </tr>
                        <tr>
                            <td><strong>Tổng giá trị:</strong></td>
                            <td class="fw-bold text-primary"><?php echo number_format($item['OH_Value'] ?? 0, 0); ?> đ</td>
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
                        <div class="h5 mb-1 text-info"><?php echo number_format($summary['total_transactions']); ?></div>
                        <small class="text-muted">Tổng giao dịch</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-2">
                        <div class="h5 mb-1 text-success"><?php echo number_format($summary['total_in'], 2); ?></div>
                        <small class="text-muted">Tổng nhập</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-2">
                        <div class="h5 mb-1 text-danger"><?php echo number_format($summary['total_out'], 2); ?></div>
                        <small class="text-muted">Tổng xuất</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="h5 mb-1 text-success"><?php echo formatCurrency($summary['total_value_in']); ?></div>
                        <small class="text-muted">Giá trị nhập</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2">
                        <div class="h5 mb-1 text-danger"><?php echo formatCurrency($summary['total_value_out']); ?></div>
                        <small class="text-muted">Giá trị xuất</small>
                    </div>
                </div>
            </div>
            
            <?php if ($summary['last_transaction_date']): ?>
            <div class="text-center mt-2">
                <small class="text-muted">
                    Giao dịch cuối: <?php echo formatDateTime($summary['last_transaction_date']); ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- BOM Usage -->
    <?php if (!empty($bomUsage)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-list me-2"></i>Sử dụng trong BOM
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Loại máy</th>
                            <th>Tên BOM</th>
                            <th>Mã BOM</th>
                            <th>Số lượng</th>
                            <th>ĐVT</th>
                            <th>Vị trí</th>
                            <th>Ưu tiên</th>
                            <th>Chu kỳ BT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bomUsage as $bom): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bom['machine_type_name']); ?></td>
                            <td><?php echo htmlspecialchars($bom['bom_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($bom['bom_code']); ?></code></td>
                            <td class="text-end fw-bold"><?php echo number_format($bom['quantity'], 2); ?></td>
                            <td><?php echo htmlspecialchars($bom['unit']); ?></td>
                            <td><?php echo htmlspecialchars($bom['position'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo getPriorityClass($bom['priority']); ?>">
                                    <?php echo $bom['priority']; ?>
                                </span>
                            </td>
                            <td><?php echo $bom['maintenance_interval'] ? $bom['maintenance_interval'] . ' giờ' : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Suppliers -->
    <?php if (!empty($suppliers)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-truck me-2"></i>Nhà cung cấp
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nhà cung cấp</th>
                            <th>Mã sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">MOQ</th>
                            <th>Lead time</th>
                            <th>Ưu tiên</th>
                            <th>Liên hệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                <?php if ($supplier['is_preferred']): ?>
                                    <i class="fas fa-star text-warning ms-1" title="Nhà cung cấp ưu tiên"></i>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($supplier['part_number'] ?? '-'); ?></code></td>
                            <td class="text-end fw-bold"><?php echo number_format($supplier['unit_price'], 0); ?> đ</td>
                            <td class="text-end"><?php echo number_format($supplier['min_order_qty'], 0); ?></td>
                            <td><?php echo $supplier['lead_time']; ?> ngày</td>
                            <td>
                                <?php if ($supplier['is_preferred']): ?>
                                    <span class="badge bg-warning">Ưu tiên</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Bình thường</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo htmlspecialchars($supplier['contact_info'] ?? '-'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Transactions -->
    <?php if (!empty($recentTransactions)): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="fas fa-history me-2"></i>Giao dịch gần đây
            </h6>
            <button type="button" class="btn btn-sm btn-outline-primary" 
                    onclick="showTransactions('<?php echo $item['ItemCode']; ?>')">
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
                                <small><?php echo formatDateTime($trans['TransactionDate']); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType']); ?>">
                                    <?php echo htmlspecialchars($trans['TransactionType'] ?? '-'); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="<?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($trans['TransactedQty'] > 0 ? '+' : '') . number_format($trans['TransactedQty'], 2); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="<?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($trans['TotalAmount'] ?? 0, 0); ?> đ
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
    
    <?php
    $html = ob_get_clean();
    
    jsonResponse([
        'success' => true,
        'html' => $html,
        'item' => $item,
        'summary' => $summary
    ]);
    
} catch (Exception $e) {
    error_log("Error in item details API: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi tải chi tiết vật tư'
    ], 500);
}

// Helper functions
function getStockQuantityClass($item) {
    if ($item['Onhand'] <= 0) return 'text-danger';
    if (!empty($item['min_stock']) && $item['Onhand'] < $item['min_stock']) return 'text-warning';
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