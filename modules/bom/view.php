<?php
/**
 * BOM Module - View Page
 * /modules/bom/view.php
 * Trang xem chi tiết BOM
 */
require_once 'config.php';

$bomId = intval($_GET['id'] ?? 0);
if (!$bomId) {
    header('Location: index.php');
    exit;
}

require_once '../../includes/header.php';

// Check permission
requirePermission('bom', 'view');

// Get BOM details
$bom = getBOMDetails($bomId);
if (!$bom) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Chi tiết BOM: ' . $bom['bom_name'];
$currentModule = 'bom';
$moduleCSS = 'bom';
$moduleJS = 'bom';

// Breadcrumb
$breadcrumb = [
    ['title' => 'BOM thiết bị', 'url' => 'index.php'],
    ['title' => $bom['bom_name'], 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('bom', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $bomId . '" class="btn btn-warning">
        <i class="fas fa-edit me-2"></i>Chỉnh sửa
    </a> ';
}

if (hasPermission('bom', 'export')) {
    $pageActions .= '<div class="btn-group">
        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-download me-2"></i>Xuất BOM
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="exportBOM(' . $bomId . ', \'excel\')">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a></li>
            <li><a class="dropdown-item" href="#" onclick="exportBOM(' . $bomId . ', \'pdf\')">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </a></li>
        </ul>
    </div> ';
}

if (hasPermission('bom', 'delete')) {
    $pageActions .= '<button onclick="CMMS.BOM.deleteBOM(' . $bomId . ')" class="btn btn-danger">
        <i class="fas fa-trash me-2"></i>Xóa BOM
    </button>';
}

// Calculate totals
$totalCost = calculateBOMCost($bomId);
$totalItems = count($bom['items']);

// Stock analysis
$stockAnalysis = [
    'ok' => 0,
    'low' => 0,
    'out' => 0,
    'total_shortage' => 0
];

foreach ($bom['items'] as $item) {
    $status = strtolower($item['stock_status']);
    if (isset($stockAnalysis[$status])) {
        $stockAnalysis[$status]++;
    }
    
    if ($status === 'low' || $status === 'out') {
        $shortage = max(0, $item['quantity'] - $item['stock_quantity']);
        $stockAnalysis['total_shortage'] += $shortage * $item['unit_price'];
    }
}
?>

<!-- BOM Header -->
<div class="bom-summary">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <h3><?php echo htmlspecialchars($bom['bom_name']); ?></h3>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div>
                        <i class="fas fa-barcode me-1"></i>
                        <strong>Mã BOM:</strong> 
                        <span class="bom-code"><?php echo htmlspecialchars($bom['bom_code']); ?></span>
                    </div>
                    <div>
                        <i class="fas fa-robot me-1"></i>
                        <strong>Dòng máy:</strong> 
                        <?php echo htmlspecialchars($bom['machine_type_name']); ?>
                    </div>
                    <div>
                        <i class="fas fa-tag me-1"></i>
                        <strong>Phiên bản:</strong> 
                        <?php echo htmlspecialchars($bom['version'] ?: '1.0'); ?>
                    </div>
                    <div>
                        <i class="fas fa-calendar me-1"></i>
                        <strong>Ngày tạo:</strong> 
                        <?php echo formatDateTime($bom['created_at']); ?>
                    </div>
                </div>
                
                <?php if ($bom['description']): ?>
                <p class="mb-3 opacity-90">
                    <strong>Mô tả:</strong> <?php echo nl2br(htmlspecialchars($bom['description'])); ?>
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
        <!-- BOM Items -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-cubes me-2"></i>
                    Danh sách linh kiện
                </h5>
                <div class="d-flex gap-2">
                    <select id="priorityFilter" class="form-select form-select-sm">
                        <option value="">Tất cả ưu tiên</option>
                        <option value="Critical">Critical</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                    <select id="stockFilter" class="form-select form-select-sm">
                        <option value="">Tất cả tồn kho</option>
                        <option value="OK">Đủ hàng</option>
                        <option value="Low">Sắp hết</option>
                        <option value="Out of Stock">Hết hàng</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="bomItemsTable" class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Mã linh kiện</th>
                                <th>Tên linh kiện</th>
                                <th>Số lượng</th>
                                <th>Đơn vị</th>
                                <th>Ưu tiên</th>
                                <th>Tồn kho</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bom['items'])): ?>
                            <tr>
                                <td colspan="7" class="text-center py-3 text-muted">
                                    <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                                    BOM chưa có linh kiện nào
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($bom['items'] as $item): ?>
                            <tr data-priority="<?php echo $item['priority']; ?>" data-stock="<?php echo $item['stock_status']; ?>">
                                <td><?php echo htmlspecialchars($item['part_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPriorityClass($item['priority']); ?>">
                                        <?php echo htmlspecialchars($item['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($item['stock_quantity'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo getStockStatusClass($item['stock_status']); ?>">
                                        <?php echo getStockStatusText($item['stock_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Cost Breakdown -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Phân tích chi phí
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <small class="text-muted">Tổng chi phí</small>
                            <h4 class="mb-0"><?php echo formatVND($totalCost); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <small class="text-muted">Số linh kiện</small>
                            <h4 class="mb-0"><?php echo $totalItems; ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="border rounded p-3">
                            <small class="text-muted">Chi phí thiếu hụt</small>
                            <h4 class="mb-0 text-danger"><?php echo formatVND($stockAnalysis['total_shortage']); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Stock Analysis -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-warehouse me-2"></i>
                    Phân tích tồn kho
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between">
                        <span>Đủ hàng</span>
                        <span class="badge bg-success"><?php echo $stockAnalysis['ok']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Sắp hết</span>
                        <span class="badge bg-warning"><?php echo $stockAnalysis['low']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Hết hàng</span>
                        <span class="badge bg-danger"><?php echo $stockAnalysis['out']; ?></span>
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
                    <?php if (hasPermission('bom', 'create')): ?>
                    <a href="add.php?template=<?php echo $bomId; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-copy me-2"></i>Sao chép BOM
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('bom', 'edit')): ?>
                    <a href="edit.php?id=<?php echo $bomId; ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-edit me-2"></i>Chỉnh sửa
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-print me-2"></i>In BOM
                    </button>
                    
                    <button onclick="shareBOM(<?php echo $bomId; ?>)" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-share me-2"></i>Chia sẻ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const priorityFilter = document.getElementById('priorityFilter');
    const stockFilter = document.getElementById('stockFilter');
    const tableRows = document.querySelectorAll('#bomItemsTable tbody tr[data-priority]');
    
    function applyFilters() {
        const priorityValue = priorityFilter.value;
        const stockValue = stockFilter.value;
        
        tableRows.forEach(row => {
            let showRow = true;
            
            if (priorityValue && row.dataset.priority !== priorityValue) {
                showRow = false;
            }
            
            if (stockValue && row.dataset.stock !== stockValue) {
                showRow = false;
            }
            
            row.style.display = showRow ? '' : 'none';
        });
        
        // Update visible count
        const visibleRows = document.querySelectorAll('#bomItemsTable tbody tr:not([style*="display: none"])');
        const totalVisible = visibleRows.length;
        
        // Could update a counter here if needed
    }
    
    priorityFilter.addEventListener('change', applyFilters);
    stockFilter.addEventListener('change', applyFilters);
});

// Share BOM function
function shareBOM(bomId) {
    const url = window.location.href;
    
    if (navigator.share) {
        navigator.share({
            title: 'BOM: <?php echo addslashes($bom['bom_name']); ?>',
            text: 'Xem chi tiết BOM thiết bị',
            url: url
        });
    } else {
        // Fallback - copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            CMMS.showToast('Đã copy link BOM vào clipboard', 'success');
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>