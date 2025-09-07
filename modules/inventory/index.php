<?php
/**
 * Inventory Management Main Page
 * /modules/inventory/index.php
 */

$pageTitle = 'Quản lý tồn kho';
$currentModule = 'inventory';
$moduleCSS = 'inventory';
$moduleJS = 'inventory';

require_once '../../includes/header.php';
requirePermission('inventory', 'view');

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$bom_status = $_GET['bom_status'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build SQL query with filters
$sql = "SELECT 
    o.ID,
    o.ItemCode,
    o.Itemname,
    o.Locator,
    o.Lotnumber,
    o.Onhand,
    o.UOM,
    o.Price,
    o.OH_Value,
    p.id as part_id,
    p.part_code,
    p.part_name,
    p.category,
    p.min_stock,
    p.max_stock,
    p.supplier_name,
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
WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (o.ItemCode LIKE ? OR o.Itemname LIKE ? OR p.part_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($status)) {
    switch ($status) {
        case 'out_of_stock':
            $sql .= " AND o.Onhand <= 0";
            break;
        case 'low_stock':
            $sql .= " AND p.min_stock > 0 AND o.Onhand < p.min_stock AND o.Onhand > 0";
            break;
        case 'excess_stock':
            $sql .= " AND p.max_stock > 0 AND o.Onhand > p.max_stock";
            break;
        case 'normal':
            $sql .= " AND o.Onhand > 0 AND (p.min_stock <= 0 OR o.Onhand >= p.min_stock) AND (p.max_stock <= 0 OR o.Onhand <= p.max_stock)";
            break;
    }
}

if (!empty($bom_status)) {
    if ($bom_status === 'in_bom') {
        $sql .= " AND p.id IS NOT NULL";
    } else {
        $sql .= " AND p.id IS NULL";
    }
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as counted";
$totalResult = $db->fetch($countSql, $params);
$total = $totalResult['total'];

// Add order by and limit
$sql .= " ORDER BY o.Onhand ASC, o.Itemname ASC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$inventory = $db->fetchAll($sql, $params);

// Get categories for filter
$categories = $db->fetchAll("SELECT DISTINCT category FROM parts WHERE category IS NOT NULL ORDER BY category");

// Get statistics
$stats = [
    'total_items' => $db->fetch("SELECT COUNT(*) as count FROM onhand")['count'],
    'in_bom_items' => $db->fetch("SELECT COUNT(DISTINCT o.ItemCode) as count FROM onhand o JOIN parts p ON o.ItemCode = p.part_code")['count'],
    'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand WHERE Onhand <= 0")['count'],
    'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand o LEFT JOIN parts p ON o.ItemCode = p.part_code WHERE p.min_stock > 0 AND o.Onhand < p.min_stock AND o.Onhand > 0")['count'],
    'total_value' => $db->fetch("SELECT SUM(OH_Value) as total FROM onhand")['total'] ?? 0
];

$pagination = paginate($total, $page, $per_page, 'index.php');
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_items']); ?></div>
                        <div class="stat-label">Tổng mặt hàng</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['in_bom_items']); ?></div>
                        <div class="stat-label">Vật tư BOM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['low_stock'] + $stats['out_of_stock']); ?></div>
                        <div class="stat-label">Cảnh báo tồn kho</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo formatCurrency($stats['total_value']); ?></div>
                        <div class="stat-label">Tổng giá trị</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Bộ lọc
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Mã, tên vật tư...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Phân loại</label>
                <select class="form-select" name="category">
                    <option value="">Tất cả</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Trạng thái tồn</label>
                <select class="form-select" name="status">
                    <option value="">Tất cả</option>
                    <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Hết hàng</option>
                    <option value="low_stock" <?php echo $status === 'low_stock' ? 'selected' : ''; ?>>Thiếu hàng</option>
                    <option value="excess_stock" <?php echo $status === 'excess_stock' ? 'selected' : ''; ?>>Dư thừa</option>
                    <option value="normal" <?php echo $status === 'normal' ? 'selected' : ''; ?>>Bình thường</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Loại vật tư</label>
                <select class="form-select" name="bom_status">
                    <option value="">Tất cả</option>
                    <option value="in_bom" <?php echo $bom_status === 'in_bom' ? 'selected' : ''; ?>>Trong BOM</option>
                    <option value="out_bom" <?php echo $bom_status === 'out_bom' ? 'selected' : ''; ?>>Ngoài BOM</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Lọc
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-warehouse me-2"></i>Tồn kho hiện tại
            <span class="badge bg-secondary ms-2"><?php echo number_format($total); ?> mặt hàng</span>
        </h5>
        
        <div class="btn-group">
            <?php if (hasPermission('inventory', 'export')): ?>
            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportInventory()">
                <i class="fas fa-file-excel me-1"></i>Xuất Excel
            </button>
            <?php endif; ?>
            
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-eye me-1"></i>Hiển thị
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Lịch sử giao dịch</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="showTransactionHistory('in')">
                        <i class="fas fa-plus text-success me-2"></i>Lịch sử nhập kho
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="showTransactionHistory('out')">
                        <i class="fas fa-minus text-danger me-2"></i>Lịch sử xuất kho
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="showTransactionHistory('all')">
                        <i class="fas fa-history text-info me-2"></i>Tất cả giao dịch
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Mã vật tư</th>
                        <th>Tên vật tư</th>
                        <th style="width: 80px;">Vị trí</th>
                        <th style="width: 80px;">Lot</th>
                        <th style="width: 100px;" class="text-end">Tồn kho</th>
                        <th style="width: 60px;">ĐVT</th>
                        <th style="width: 100px;" class="text-end">Đơn giá</th>
                        <th style="width: 120px;" class="text-end">Tổng giá trị</th>
                        <th style="width: 100px;">Loại vật tư</th>
                        <th style="width: 100px;">Trạng thái</th>
                        <th style="width: 100px;" class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            Không có dữ liệu tồn kho
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td>
                            <code class="text-primary"><?php echo htmlspecialchars($item['ItemCode']); ?></code>
                        </td>
                        <td>
                            <div class="fw-medium"><?php echo htmlspecialchars($item['Itemname']); ?></div>
                            <?php if ($item['part_name']): ?>
                                <small class="text-muted">BOM: <?php echo htmlspecialchars($item['part_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($item['Locator'] ?? '-'); ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($item['Lotnumber'] ?? '-'); ?></small>
                        </td>
                        <td class="text-end">
                            <span class="fw-bold <?php echo getStockQuantityClass($item); ?>">
                                <?php echo number_format($item['Onhand'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['UOM']); ?></span>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($item['Price'] ?? 0, 0); ?> đ
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo number_format($item['OH_Value'] ?? 0, 0); ?> đ
                        </td>
                        <td>
                            <span class="badge <?php echo $item['bom_status'] === 'Trong BOM' ? 'bg-primary' : 'bg-secondary'; ?>">
                                <?php echo $item['bom_status']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo getStockStatusClass($item['stock_status']); ?>">
                                <?php echo $item['stock_status']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="viewItemDetails('<?php echo $item['ItemCode']; ?>')"
                                        title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <button type="button" class="btn btn-outline-secondary" 
                                        onclick="showTransactions('<?php echo $item['ItemCode']; ?>')"
                                        title="Lịch sử giao dịch">
                                    <i class="fas fa-history"></i>
                                </button>
                                
                                <?php if ($item['part_id']): ?>
                                <a href="../bom/parts/view.php?id=<?php echo $item['part_id']; ?>" 
                                   class="btn btn-outline-primary" title="Xem thông tin BOM">
                                    <i class="fas fa-list"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?php echo buildPaginationHtml($pagination, 'index.php?' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY))); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Item Details Modal -->
<div class="modal fade" id="itemDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết vật tư</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="itemDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Transaction History Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch sử giao dịch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php
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
?>

<script>
// Export inventory to Excel
function exportInventory() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'api/export.php?' + params.toString();
}

// View item details
function viewItemDetails(itemCode) {
    CMMS.ajax({
        url: 'api/item_details.php',
        method: 'GET',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'item_code=' + encodeURIComponent(itemCode),
        success: function(data) {
            document.getElementById('itemDetailsContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('itemDetailsModal')).show();
        }
    });
}

// Show transactions for specific item
function showTransactions(itemCode) {
    loadTransactionHistory('item', itemCode);
}

// Show transaction history by type
function showTransactionHistory(type, itemCode = null) {
    loadTransactionHistory(type, itemCode);
}

// Load transaction history
function loadTransactionHistory(type, itemCode = null) {
    let url = 'api/transactions.php?type=' + type;
    if (itemCode) {
        url += '&item_code=' + encodeURIComponent(itemCode);
    }
    
    CMMS.ajax({
        url: url,
        method: 'GET',
        success: function(data) {
            document.getElementById('transactionContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('transactionModal')).show();
        }
    });
}

// Auto-refresh every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        window.location.reload();
    }
}, 300000); // 5 minutes

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(function(element) {
        new bootstrap.Tooltip(element);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>