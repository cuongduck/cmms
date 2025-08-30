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
                        <span class="part-code"><?php echo htmlspecialchars($bom['bom_code']); ?></span>
                    </div>
                    <div>
                        <i class="fas fa-cog me-1"></i>
                        <strong>Dòng máy:</strong> 
                        <?php echo htmlspecialchars($bom['machine_type_name']); ?>
                    </div>
                    <div>
                        <i class="fas fa-tag me-1"></i>
                        <strong>Version:</strong> 
                        <?php echo htmlspecialchars($bom['version']); ?>
                    </div>
                    <?php if ($bom['effective_date']): ?>
                    <div>
                        <i class="fas fa-calendar me-1"></i>
                        <strong>Hiệu lực:</strong> 
                        <?php echo formatDate($bom['effective_date']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($bom['description']): ?>
                <p class="mb-0 opacity-90">
                    <?php echo nl2br(htmlspecialchars($bom['description'])); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="summary-stats">
                    <div class="summary-stat">
                        <span class="summary-stat-number"><?php echo $totalItems; ?></span>
                        <span class="summary-stat-label">Linh kiện</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-stat-number"><?php echo formatVND($totalCost); ?></span>
                        <span class="summary-stat-label">Tổng chi phí</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-9">
        <!-- BOM Items -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Danh sách linh kiện (<?php echo $totalItems; ?> items)
                    </h5>
                    
                    <div class="d-flex gap-2">
                        <!-- Priority filter -->
                        <select id="priorityFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">Tất cả độ ưu tiên</option>
                            <option value="Critical">Nghiêm trọng</option>
                            <option value="High">Cao</option>
                            <option value="Medium">Trung bình</option>
                            <option value="Low">Thấp</option>
                        </select>
                        
                        <!-- Stock filter -->
                        <select id="stockFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">Tất cả trạng thái</option>
                            <option value="ok">Đủ hàng</option>
                            <option value="low">Sắp hết</option>
                            <option value="out">Hết hàng</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table bom-table mb-0" id="bomItemsTable">
                        <thead>
                            <tr>
                                <th width="60">STT</th>
                                <th>Linh kiện</th>
                                <th width="100" class="text-center">Số lượng</th>
                                <th width="80">Đơn vị</th>
                                <th width="120" class="text-end">Đơn giá</th>
                                <th width="120" class="text-end">Thành tiền</th>
                                <th width="100">Ưu tiên</th>
                                <th width="100" class="text-center">Tồn kho</th>
                                <th width="120" class="hide-mobile">Vị trí</th>
                                <th width="80" class="hide-mobile text-center">Chu kỳ BT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bom['items'])): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="bom-empty">
                                        <div class="bom-empty-icon">
                                            <i class="fas fa-list"></i>
                                        </div>
                                        <div class="bom-empty-text">BOM này chưa có linh kiện nào</div>
                                        <?php if (hasPermission('bom', 'edit')): ?>
                                        <a href="edit.php?id=<?php echo $bomId; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Thêm linh kiện
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($bom['items'] as $index => $item): ?>
                                <tr class="bom-item-row priority-<?php echo $item['priority']; ?> fade-in-up" 
                                    data-priority="<?php echo $item['priority']; ?>"
                                    data-stock="<?php echo strtolower($item['stock_status']); ?>">
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="part-code"><?php echo htmlspecialchars($item['part_code']); ?></span>
                                            <strong><?php echo htmlspecialchars($item['part_name']); ?></strong>
                                            <?php if ($item['part_description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['part_description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center">
                                        <strong><?php echo number_format($item['quantity'], 2); ?></strong>
                                    </td>
                                    
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    
                                    <td class="text-end">
                                        <span class="cost-display"><?php echo formatVND($item['unit_price']); ?></span>
                                    </td>
                                    
                                    <td class="text-end">
                                        <span class="cost-display fw-bold"><?php echo formatVND($item['total_cost']); ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="priority-badge priority-<?php echo $item['priority']; ?>">
                                            <?php echo $bomConfig['priorities'][$item['priority']]['name'] ?? $item['priority']; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center">
                                        <div class="stock-indicator">
                                            <span class="stock-dot <?php echo strtolower($item['stock_status']); ?>"></span>
                                            <div>
                                                <strong><?php echo number_format($item['stock_quantity'], 1); ?></strong>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['stock_unit']); ?></small>
                                                <span class="badge <?php echo getStockStatusClass($item['stock_status']); ?>">
                                                    <?php echo getStockStatusText($item['stock_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="hide-mobile">
                                        <small><?php echo htmlspecialchars($item['position']); ?></small>
                                    </td>
                                    
                                    <td class="hide-mobile text-center">
                                        <?php if ($item['maintenance_interval']): ?>
                                            <small><?php echo number_format($item['maintenance_interval']); ?>h</small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        
                        <?php if (!empty($bom['items'])): ?>
                        <tfoot>
                            <tr class="table-secondary fw-bold">
                                <td colspan="5" class="text-end">Tổng cộng:</td>
                                <td class="text-end">
                                    <span class="cost-display"><?php echo formatVND($totalCost); ?></span>
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <!-- Stock Analysis -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Phân tích tồn kho
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center g-0 mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-success bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-success"><?php echo $stockAnalysis['ok']; ?></div>
                            <small>Đủ hàng</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-warning bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-warning"><?php echo $stockAnalysis['low']; ?></div>
                            <small>Sắp hết</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-danger bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-danger"><?php echo $stockAnalysis['out']; ?></div>
                            <small>Hết hàng</small>
                        </div>
                    </div>
                </div>
                
                <!-- Progress bar -->
                <div class="progress mb-3" style="height: 15px;">
                    <?php 
                    $okPercent = $totalItems > 0 ? round(($stockAnalysis['ok'] / $totalItems) * 100, 1) : 0;
                    $lowPercent = $totalItems > 0 ? round(($stockAnalysis['low'] / $totalItems) * 100, 1) : 0;
                    $outPercent = $totalItems > 0 ? round(($stockAnalysis['out'] / $totalItems) * 100, 1) : 0;
                    ?>
                    <div class="progress-bar bg-success" style="width: <?php echo $okPercent; ?>%" 
                         title="Đủ hàng: <?php echo $okPercent; ?>%"></div>
                    <div class="progress-bar bg-warning" style="width: <?php echo $lowPercent; ?>%" 
                         title="Sắp hết: <?php echo $lowPercent; ?>%"></div>
                    <div class="progress-bar bg-danger" style="width: <?php echo $outPercent; ?>%" 
                         title="Hết hàng: <?php echo $outPercent; ?>%"></div>
                </div>
                
                <?php if ($stockAnalysis['total_shortage'] > 0): ?>
                <div class="alert alert-warning">
                    <strong>Chi phí thiếu hàng:</strong><br>
                    <span class="cost-display"><?php echo formatVND($stockAnalysis['total_shortage']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <a href="reports/stock_report.php?bom_id=<?php echo $bomId; ?>" 
                       class="btn btn-outline-info btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>Báo cáo chi tiết
                    </a>
                    
                    <?php if ($stockAnalysis['low'] > 0 || $stockAnalysis['out'] > 0): ?>
                    <a href="reports/shortage_report.php?bom_id=<?php echo $bomId; ?>" 
                       class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i>Báo cáo thiếu hàng
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- BOM Info -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin BOM
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td><strong>Mã BOM:</strong></td>
                        <td class="part-code"><?php echo htmlspecialchars($bom['bom_code']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Dòng máy:</strong></td>
                        <td><?php echo htmlspecialchars($bom['machine_type_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phiên bản:</strong></td>
                        <td><?php echo htmlspecialchars($bom['version']); ?></td>
                    </tr>
                    <?php if ($bom['effective_date']): ?>
                    <tr>
                        <td><strong>Ngày hiệu lực:</strong></td>
                        <td><?php echo formatDate($bom['effective_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Ngày tạo:</strong></td>
                        <td><?php echo formatDateTime($bom['created_at']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Người tạo:</strong></td>
                        <td><?php echo htmlspecialchars($bom['created_by_name']); ?></td>
                    </tr>
                </table>
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