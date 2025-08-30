<?php
/**
 * Parts View Page
 * /modules/bom/parts/view.php
 * Trang xem chi tiết linh kiện
 */

$partId = intval($_GET['id'] ?? 0);
if (!$partId) {
    header('Location: index.php');
    exit;
}

require_once '../../../includes/header.php';
require_once '../config.php';

// Check permission
requirePermission('bom', 'view');

// Get part details with stock information
$sql = "SELECT p.*, 
               COALESCE(oh.Onhand, 0) as stock_quantity,
               COALESCE(oh.UOM, p.unit) as stock_unit,
               COALESCE(oh.OH_Value, 0) as stock_value,
               COALESCE(oh.Price, p.unit_price) as current_price,
               CASE 
                   WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                   WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                   ELSE 'OK'
               END as stock_status
        FROM parts p
        LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
        LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
        WHERE p.id = ?";

$part = $db->fetch($sql, [$partId]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chi tiết: ' . $part['part_name'];
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => 'index.php'],
    ['title' => $part['part_name'], 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('bom', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $partId . '" class="btn btn-warning">
        <i class="fas fa-edit me-2"></i>Chỉnh sửa
    </a> ';
}

if (hasPermission('bom', 'export')) {
    $pageActions .= '<div class="btn-group">
        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-download me-2"></i>Xuất dữ liệu
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="exportPart(' . $partId . ', \'excel\')">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a></li>
            <li><a class="dropdown-item" href="#" onclick="exportPart(' . $partId . ', \'pdf\')">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </a></li>
        </ul>
    </div> ';
}

if (hasPermission('bom', 'delete')) {
    $pageActions .= '<button onclick="CMMS.Parts.deletePart(' . $partId . ')" class="btn btn-danger">
        <i class="fas fa-trash me-2"></i>Xóa
    </button>';
}

// Get suppliers for this part
$suppliers = $db->fetchAll(
    "SELECT * FROM part_suppliers WHERE part_id = ? ORDER BY is_preferred DESC, supplier_name",
    [$partId]
);

// Get BOM usage
$bomUsage = $db->fetchAll(
    "SELECT mb.id as bom_id, mb.bom_name, mb.bom_code, bi.quantity, bi.unit, bi.priority,
            mt.name as machine_type_name, mt.code as machine_type_code
     FROM bom_items bi
     JOIN machine_bom mb ON bi.bom_id = mb.id
     JOIN machine_types mt ON mb.machine_type_id = mt.id
     WHERE bi.part_id = ?
     ORDER BY mb.bom_name",
    [$partId]
);

// Get recent transactions
$recentTransactions = $db->fetchAll(
    "SELECT t.* FROM transaction t 
     JOIN part_inventory_mapping pim ON t.ItemCode = pim.item_code
     WHERE pim.part_id = ?
     ORDER BY t.TransactionDate DESC 
     LIMIT 10",
    [$partId]
);

// Calculate usage statistics
$totalUsage = array_sum(array_column($bomUsage, 'quantity'));
$criticalUsage = count(array_filter($bomUsage, function($usage) {
    return $usage['priority'] === 'Critical';
}));
$highUsage = count(array_filter($bomUsage, function($usage) {
    return $usage['priority'] === 'High';
}));
?>

<!-- Part Header -->
<div class="bom-summary">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <h3><?php echo htmlspecialchars($part['part_name']); ?></h3>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div>
                        <i class="fas fa-barcode me-1"></i>
                        <strong>Mã linh kiện:</strong> 
                        <span class="part-code"><?php echo htmlspecialchars($part['part_code']); ?></span>
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
                    <?php if ($part['manufacturer']): ?>
                    <div>
                        <i class="fas fa-industry me-1"></i>
                        <strong>NSX:</strong> 
                        <?php echo htmlspecialchars($part['manufacturer']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($part['description']): ?>
                <p class="mb-2 opacity-90">
                    <strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($part['description'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($part['specifications']): ?>
                <p class="mb-0 opacity-90">
                    <strong>Thông số:</strong> <?php echo nl2br(htmlspecialchars($part['specifications'])); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="summary-stats">
                    <div class="summary-stat">
                        <span class="summary-stat-number"><?php echo formatVND($part['unit_price']); ?></span>
                        <span class="summary-stat-label">Đơn giá</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-number"><?php echo number_format($part['stock_quantity'], 1); ?></span>
                        <span class="summary-stat-label">Tồn kho</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Stock Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-warehouse me-2"></i>
                    Thông tin tồn kho
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="h3 mb-1 
                                <?php echo ($part['stock_status'] === 'OK') ? 'text-success' : 
                                           (($part['stock_status'] === 'Low') ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo number_format($part['stock_quantity'], 2); ?>
                            </div>
                            <div class="text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?></div>
                            <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?>">
                                <?php echo getStockStatusText($part['stock_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="h5 mb-1"><?php echo number_format($part['min_stock'], 2); ?></div>
                            <div class="text-muted">Mức tối thiểu</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="h5 mb-1"><?php echo number_format($part['max_stock'], 2); ?></div>
                            <div class="text-muted">Mức tối đa</div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="h5 mb-1"><?php echo formatVND($part['stock_value']); ?></div>
                            <div class="text-muted">Giá trị tồn</div>
                        </div>
                    </div>
                </div>
                
                <?php if ($part['min_stock'] > 0): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Mức tồn kho</span>
                        <span class="text-muted">
                            <?php echo number_format(($part['stock_quantity'] / max($part['max_stock'], 1)) * 100, 1); ?>%
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <?php 
                        $stockPercent = min(100, ($part['stock_quantity'] / max($part['max_stock'], 1)) * 100);
                        $minPercent = ($part['min_stock'] / max($part['max_stock'], 1)) * 100;
                        $progressClass = $part['stock_quantity'] >= $part['min_stock'] ? 'bg-success' : 'bg-danger';
                        ?>
                        <div class="progress-bar <?php echo $progressClass; ?>" 
                             style="width: <?php echo $stockPercent; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">0</small>
                        <small class="text-warning" style="margin-left: <?php echo $minPercent; ?>%">
                            Min: <?php echo number_format($part['min_stock'], 1); ?>
                        </small>
                        <small class="text-muted"><?php echo number_format($part['max_stock'], 1); ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- BOM Usage -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Sử dụng trong BOM (<?php echo count($bomUsage); ?>)
                    </h5>
                    
                    <?php if (!empty($bomUsage)): ?>
                    <div class="d-flex gap-2">
                        <span class="badge bg-dark"><?php echo $criticalUsage; ?> nghiêm trọng</span>
                        <span class="badge bg-danger"><?php echo $highUsage; ?> cao</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($bomUsage)): ?>
                    <div class="text-center py-4">
                        <div class="bom-empty">
                            <div class="bom-empty-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="bom-empty-text">Linh kiện này chưa được sử dụng trong BOM nào</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table bom-table mb-0">
                            <thead>
                                <tr>
                                    <th>BOM</th>
                                    <th>Dòng máy</th>
                                    <th class="text-center">Số lượng cần</th>
                                    <th class="text-center">Ưu tiên</th>
                                    <th class="text-end">Giá trị</th>
                                    <th width="100">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bomUsage as $usage): ?>
                                <tr class="bom-item-row priority-<?php echo $usage['priority']; ?>">
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="part-code"><?php echo htmlspecialchars($usage['bom_code']); ?></span>
                                            <strong><?php echo htmlspecialchars($usage['bom_name']); ?></strong>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span><?php echo htmlspecialchars($usage['machine_type_name']); ?></span>
                                            <small class="text-muted part-code"><?php echo htmlspecialchars($usage['machine_type_code']); ?></small>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center">
                                        <strong><?php echo number_format($usage['quantity'], 2); ?></strong>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($usage['unit']); ?></small>
                                    </td>
                                    
                                    <td class="text-center">
                                        <span class="priority-badge priority-<?php echo $usage['priority']; ?>">
                                            <?php echo $bomConfig['priorities'][$usage['priority']]['name'] ?? $usage['priority']; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-end">
                                        <span class="cost-display">
                                            <?php echo formatVND($usage['quantity'] * $part['unit_price']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <a href="../view.php?id=<?php echo $usage['bom_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="Xem BOM">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            
                            <tfoot>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="2" class="text-end">Tổng cộng:</td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($totalUsage, 2); ?></strong>
                                    </td>
                                    <td colspan="2" class="text-end">
                                        <span class="cost-display"><?php echo formatVND($totalUsage * $part['unit_price']); ?></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <?php if (!empty($recentTransactions)): ?>
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Giao dịch gần đây (<?php echo count($recentTransactions); ?>)
                    </h5>
                    <a href="/modules/inventory/transactions.php?part_id=<?php echo $partId; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i>Xem tất cả
                    </a>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Loại</th>
                                <th class="text-center">Số lượng</th>
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
                                    <span class="badge <?php echo ($trans['TransactionType'] === 'Issue') ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $trans['TransactionType']; ?>
                                    </span>
                                </td>
                                
                                <td class="text-center">
                                    <strong class="<?php echo ($trans['TransactionType'] === 'Issue') ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo ($trans['TransactionType'] === 'Issue') ? '-' : '+'; ?>
                                        <?php echo number_format($trans['TransactedQty'], 2); ?>
                                    </strong>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($trans['UOM']); ?></small>
                                </td>
                                
                                <td class="text-end">
                                    <span class="cost-display">
                                        <?php echo formatVND($trans['TotalAmount']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <small><?php echo htmlspecialchars($trans['Reason']); ?></small>
                                </td>
                                
                                <td>
                                    <small><?php echo htmlspecialchars($trans['Requester']); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Part Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin linh kiện
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td><strong>Mã linh kiện:</strong></td>
                        <td class="part-code"><?php echo htmlspecialchars($part['part_code']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tên:</strong></td>
                        <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                    </tr>
                    <?php if ($part['category']): ?>
                    <tr>
                        <td><strong>Danh mục:</strong></td>
                        <td><?php echo htmlspecialchars($part['category']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Đơn vị:</strong></td>
                        <td><?php echo htmlspecialchars($part['unit']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Đơn giá:</strong></td>
                        <td><span class="cost-display"><?php echo formatVND($part['unit_price']); ?></span></td>
                    </tr>
                    <?php if ($part['lead_time'] > 0): ?>
                    <tr>
                        <td><strong>Lead time:</strong></td>
                        <td><?php echo $part['lead_time']; ?> ngày</td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Ngày tạo:</strong></td>
                        <td><?php echo formatDateTime($part['created_at']); ?></td>
                    </tr>
                    <?php if ($part['updated_at'] !== $part['created_at']): ?>
                    <tr>
                        <td><strong>Cập nhật:</strong></td>
                        <td><?php echo formatDateTime($part['updated_at']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Suppliers -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-truck me-2"></i>
                    Nhà cung cấp (<?php echo count($suppliers) + (empty($part['supplier_name']) ? 0 : 1); ?>)
                </h6>
            </div>
            <div class="card-body">
                <!-- Main supplier from parts table -->
                <?php if (!empty($part['supplier_name'])): ?>
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?php echo htmlspecialchars($part['supplier_name']); ?></strong>
                            <?php if ($part['supplier_code']): ?>
                                <small class="d-block text-muted part-code"><?php echo htmlspecialchars($part['supplier_code']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-primary">Chính</span>
                    </div>
                    <div class="text-end mt-1">
                        <span class="cost-display"><?php echo formatVND($part['unit_price']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Additional suppliers -->
                <?php if (empty($suppliers)): ?>
                    <?php if (empty($part['supplier_name'])): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Chưa có thông tin nhà cung cấp
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($suppliers as $supplier): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong>
                                <?php if ($supplier['supplier_code']): ?>
                                    <small class="d-block text-muted part-code"><?php echo htmlspecialchars($supplier['supplier_code']); ?></small>
                                <?php endif; ?>
                                <?php if ($supplier['part_number']): ?>
                                    <small class="d-block text-muted">P/N: <?php echo htmlspecialchars($supplier['part_number']); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if ($supplier['is_preferred']): ?>
                                <span class="badge bg-warning">Ưu tiên</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <div>
                                <?php if ($supplier['min_order_qty'] > 0): ?>
                                    <small class="text-muted">MOQ: <?php echo number_format($supplier['min_order_qty'], 2); ?></small>
                                <?php endif; ?>
                                <?php if ($supplier['lead_time'] > 0): ?>
                                    <small class="text-muted">LT: <?php echo $supplier['lead_time']; ?> ngày</small>
                                <?php endif; ?>
                            </div>
                            <span class="cost-display"><?php echo formatVND($supplier['unit_price']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stock Analysis -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Phân tích tồn kho
                </h6>
            </div>
            <div class="card-body">
                <?php
                // Calculate stock analysis
                $stockAnalysis = [
                    'current_stock' => $part['stock_quantity'],
                    'min_stock' => $part['min_stock'],
                    'max_stock' => $part['max_stock'],
                    'stock_value' => $part['stock_value'],
                    'total_required' => $totalUsage,
                    'coverage_days' => 0
                ];
                
                // Calculate coverage days if we have usage data
                if ($totalUsage > 0) {
                    // Estimate monthly usage from recent transactions
                    $monthlyUsage = 0;
                    foreach ($recentTransactions as $trans) {
                        if ($trans['TransactionType'] === 'Issue') {
                            $monthlyUsage += $trans['TransactedQty'];
                        }
                    }
                    
                    if ($monthlyUsage > 0) {
                        $dailyUsage = $monthlyUsage / 30;
                        $stockAnalysis['coverage_days'] = $part['stock_quantity'] / $dailyUsage;
                    }
                }
                ?>
                
                <div class="row text-center g-2 mb-3">
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Cần cho BOM</div>
                            <div class="fw-bold"><?php echo number_format($stockAnalysis['total_required'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Giá trị tồn</div>
                            <div class="fw-bold cost-display small"><?php echo formatVND($stockAnalysis['stock_value']); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($stockAnalysis['coverage_days'] > 0): ?>
                <div class="alert alert-info">
                    <strong>Phủ sóng tồn kho:</strong>
                    <?php echo number_format($stockAnalysis['coverage_days'], 1); ?> ngày
                    (dựa trên mức tiêu thụ gần đây)
                </div>
                <?php endif; ?>
                
                <!-- Recommendations -->
                <?php
                $recommendations = [];
                
                if ($part['stock_quantity'] < $part['min_stock'] && $part['min_stock'] > 0) {
                    $shortfall = $part['min_stock'] - $part['stock_quantity'];
                    $recommendations[] = [
                        'type' => 'danger',
                        'icon' => 'fas fa-exclamation-triangle',
                        'text' => "Cần bổ sung " . number_format($shortfall, 2) . " " . $part['unit'] . " để đạt mức tối thiểu"
                    ];
                }
                
                if ($part['stock_quantity'] == 0) {
                    $recommendations[] = [
                        'type' => 'danger',
                        'icon' => 'fas fa-times-circle',
                        'text' => "Hết hàng! Cần nhập khẩn cấp"
                    ];
                }
                
                if ($totalUsage > $part['stock_quantity'] && $totalUsage > 0) {
                    $shortage = $totalUsage - $part['stock_quantity'];
                    $recommendations[] = [
                        'type' => 'warning',
                        'icon' => 'fas fa-exclamation-circle',
                        'text' => "Thiếu " . number_format($shortage, 2) . " " . $part['unit'] . " để đáp ứng tất cả BOM"
                    ];
                }
                
                if (empty($recommendations)) {
                    $recommendations[] = [
                        'type' => 'success',
                        'icon' => 'fas fa-check-circle',
                        'text' => "Tình trạng tồn kho tốt"
                    ];
                }
                ?>
                
                <?php foreach ($recommendations as $rec): ?>
                <div class="alert alert-<?php echo $rec['type']; ?> p-2 mb-2">
                    <i class="<?php echo $rec['icon']; ?> me-2"></i>
                    <?php echo $rec['text']; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác nhanh
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('bom', 'edit')): ?>
                    <a href="edit.php?id=<?php echo $partId; ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-edit me-2"></i>Chỉnh sửa linh kiện
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-print me-2"></i>In thông tin
                    </button>
                    
                    <button onclick="copyPartCode()" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-copy me-2"></i>Copy mã linh kiện
                    </button>
                    
                    <?php if (!empty($bomUsage)): ?>
                    <a href="../reports/shortage_report.php?part_id=<?php echo $partId; ?>" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>Báo cáo thiếu hàng
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($part['stock_quantity'] < $part['min_stock']): ?>
                    <button onclick="createPurchaseRequest(<?php echo $partId; ?>)" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-shopping-cart me-2"></i>Đề xuất mua hàng
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Export functions
function exportPart(partId, format) {
    const params = new URLSearchParams({
        action: 'export_part',
        format: format,
        id: partId
    });
    
    window.open('/modules/bom/api/export.php?' + params, '_blank');
}

// Copy part code
function copyPartCode() {
    const partCode = '<?php echo $part['part_code']; ?>';
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(partCode).then(() => {
            CMMS.showToast('Đã copy mã linh kiện: ' + partCode, 'success');
        });
    } else {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = partCode;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        CMMS.showToast('Đã copy mã linh kiện: ' + partCode, 'success');
    }
}

// Create purchase request
function createPurchaseRequest(partId) {
    CMMS.ajax({
        url: '/modules/bom/api/purchase_request.php',
        method: 'POST',
        body: JSON.stringify({
            action: 'create_from_part',
            part_id: partId,
            quantity: <?php echo max($part['min_stock'] - $part['stock_quantity'], 1); ?>
        }),
        headers: {
            'Content-Type': 'application/json'
        },
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.href = '/modules/purchase/view.php?id=' + data.request_id;
                }, 1500);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

// Override delete function to redirect to index after delete
CMMS.Parts.deletePart = function(partId) {
    // Check if part is used in any BOM
    const usageCount = <?php echo count($bomUsage); ?>;
    
    if (usageCount > 0) {
        CMMS.showToast('Không thể xóa linh kiện đang được sử dụng trong ' + usageCount + ' BOM', 'error');
        return;
    }
    
    CMMS.confirm('Bạn có chắc chắn muốn xóa linh kiện này?', () => {
        CMMS.ajax({
            url: '/modules/bom/api/parts.php',
            method: 'POST',
            body: `action=delete&id=${partId}`,
            success: (data) => {
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            }
        });
    });
};

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize data tables
    const tables = document.querySelectorAll('table');
    tables.forEach((table, index) => {
        if (table.rows.length > 1) {
            CMMS.dataTable.init(table.id || 'table_' + index, {
                searching: false,
                pageSize: 10
            });
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>