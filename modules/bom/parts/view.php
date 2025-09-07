<?php
/**
 * Parts View Page - Rewritten Version without Mapping
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
        LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
        WHERE p.id = ?";

$part = $db->fetch($sql, [$partId]);
if (!$part) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chi tiết: ' . htmlspecialchars($part['part_name']);
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom-parts';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý linh kiện', 'url' => 'index.php'],
    ['title' => htmlspecialchars($part['part_name']), 'url' => '']
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
     WHERE t.ItemCode = ?
     ORDER BY t.TransactionDate DESC 
     LIMIT 10",
    [$part['part_code']]
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
                <p class="mb-2 opacity-90">
                    <strong>Thông số kỹ thuật:</strong> <?php echo nl2br(htmlspecialchars($part['specifications'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($part['notes']): ?>
                <p class="mb-2 opacity-90">
                    <strong>Ghi chú:</strong> <?php echo nl2br(htmlspecialchars($part['notes'])); ?>
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
                    Tồn kho & Giá trị
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">Tồn kho hiện tại</small>
                            <h4 class="mb-1"><?php echo number_format($part['stock_quantity'], 2); ?></h4>
                            <small><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">Trạng thái</small>
                            <h4 class="mb-1">
                                <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?>">
                                    <?php echo getStockStatusText($part['stock_status']); ?>
                                </span>
                            </h4>
                            <small class="d-block">Min: <?php echo number_format($part['min_stock'], 2); ?></small>
                            <small>Max: <?php echo number_format($part['max_stock'], 2); ?></small>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">Giá trị tồn kho</small>
                            <h4 class="mb-1"><?php echo formatVND($part['stock_value']); ?></h4>
                            <small>Đơn giá: <?php echo formatVND($part['current_price']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suppliers -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-truck me-2"></i>
                    Nhà cung cấp
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Mã NCC</th>
                            <th>Tên NCC</th>
                            <th>Giá</th>
                            <th>Ưu tiên</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-3 text-muted">
                                <i class="fas fa-truck-loading fa-2x mb-2 d-block"></i>
                                Chưa có nhà cung cấp
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['supplier_code']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                            <td><?php echo formatVND($supplier['unit_price']); ?></td>
                            <td>
                                <?php if ($supplier['is_preferred']): ?>
                                <span class="badge bg-success">Ưu tiên</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BOM Usage -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-cubes me-2"></i>
                    Sử dụng trong BOM
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>BOM</th>
                            <th>Dòng máy</th>
                            <th>Số lượng</th>
                            <th>Ưu tiên</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bomUsage)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-3 text-muted">
                                <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                Chưa được sử dụng trong BOM nào
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($bomUsage as $usage): ?>
                        <tr>
                            <td>
                                <a href="../bom/view.php?id=<?php echo $usage['bom_id']; ?>">
                                    <?php echo htmlspecialchars($usage['bom_name']); ?>
                                </a>
                                <small class="d-block text-muted"><?php echo $usage['bom_code']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($usage['machine_type_name']); ?></td>
                            <td><?php echo number_format($usage['quantity'], 2); ?> <?php echo $usage['unit']; ?></td>
                            <td>
                                <span class="badge <?php echo getPriorityClass($usage['priority']); ?>">
                                    <?php echo htmlspecialchars($usage['priority']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Lịch sử giao dịch gần đây
                </h5>
                <a href="/modules/inventory/transactions.php?part_id=<?php echo $partId; ?>" class="btn btn-sm btn-outline-primary">
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
                                    <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType']); ?>">
                                        <?php echo htmlspecialchars($trans['TransactionType']); ?>
                                    </span>
                                </td>
                                <td class="<?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($trans['TransactedQty'], 2); ?> <?php echo htmlspecialchars($trans['UOM']); ?>
                                </td>
                                <td><?php echo formatVND($trans['TransValue'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($trans['Reason']); ?></td>
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
        <!-- Usage Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Thống kê sử dụng
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <small class="text-muted">Tổng sử dụng</small>
                            <h4 class="mb-0"><?php echo number_format($totalUsage, 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <small class="text-muted">Số BOM</small>
                            <h4 class="mb-0"><?php echo count($bomUsage); ?></h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <small class="text-muted">Critical</small>
                            <h4 class="mb-0"><?php echo $criticalUsage; ?></h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <small class="text-muted">High</small>
                            <h4 class="mb-0"><?php echo $highUsage; ?></h4>
                        </div>
                    </div>
                </div>
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