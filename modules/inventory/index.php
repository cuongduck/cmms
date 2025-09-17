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

// Helper function để format số an toàn
function safeNumberFormat($number, $decimals = 0) {
    return number_format($number ?? 0, $decimals);
}

function safeCurrencyFormat($amount) {
    return number_format($amount ?? 0, 0) . ' đ';
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$nganh_hang = $_GET['nganh_hang'] ?? ''; 
$status = $_GET['status'] ?? '';
$bom_status = $_GET['bom_status'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build SQL query
// Build SQL query - Thêm cột ngành hàng
$sql = "SELECT 
    oh.ID,
    oh.ItemCode,
    oh.Itemname,
    oh.Locator,
    oh.Lotnumber,
    oh.Onhand,
    oh.UOM,
    oh.Price,
    oh.OH_Value,
    p.id as part_id,
    p.part_code,
    p.part_name,
    p.category,
    p.min_stock,
    p.max_stock,
    p.supplier_name,
    CASE 
        WHEN bi.part_id IS NOT NULL THEN 'Trong BOM'
        ELSE 'Ngoài BOM'
    END as bom_status,
    CASE
        WHEN COALESCE(oh.Onhand, 0) <= 0 THEN 'Hết hàng'
        WHEN p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock THEN 'Thiếu hàng'
        WHEN p.max_stock > 0 AND COALESCE(oh.Onhand, 0) > p.max_stock THEN 'Dư thừa'
        ELSE 'Bình thường'
    END as stock_status,
    CASE 
        WHEN oh.Locator LIKE 'A%' OR oh.Locator LIKE 'B%' OR oh.Locator = 'FP09' OR oh.Locator = 'MA01' THEN 'Mắm'
        WHEN oh.Locator LIKE 'G%' OR oh.Locator = 'FE17' THEN 'CSD'
        WHEN oh.Locator IN ('H300_TE0', 'HC01_TE0', 'HC02_TE0', 'HR02_TE0') THEN 'Khác'
        WHEN oh.Locator LIKE 'C%' OR oh.Locator LIKE 'H%' OR oh.Locator LIKE 'E%' OR oh.Locator LIKE 'F%' OR oh.Locator = 'FP02' OR oh.Locator = 'FP05' OR oh.Locator = 'M501' THEN 'CF'
        WHEN oh.Locator LIKE 'D%' OR oh.Locator = 'FE01' THEN 'Chung'
        ELSE 'Khác'
    END as nganh_hang,
    CASE 
        WHEN oh.Lotnumber IS NOT NULL AND LENGTH(oh.Lotnumber) >= 6 AND SUBSTRING(oh.Lotnumber, 1, 6) REGEXP '^[0-9]{6}$'
        THEN STR_TO_DATE(CONCAT(
            '20', SUBSTRING(oh.Lotnumber, 5, 2), '-',  -- năm
            SUBSTRING(oh.Lotnumber, 3, 2), '-',        -- tháng  
            SUBSTRING(oh.Lotnumber, 1, 2)              -- ngày
        ), '%Y-%m-%d')
        ELSE NULL
    END as ngay_nhap_kho
FROM onhand oh
LEFT JOIN parts p ON oh.ItemCode = p.part_code
LEFT JOIN bom_items bi ON p.id = bi.part_id
WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (oh.ItemCode LIKE ? OR oh.Itemname LIKE ? OR p.part_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($nganh_hang)) {
    $sql .= " AND (
        CASE 
            WHEN oh.Locator LIKE 'A%' OR oh.Locator LIKE 'B%' OR oh.Locator = 'FP09' OR oh.Locator = 'MA01' THEN 'Mắm'
            WHEN oh.Locator LIKE 'G%' OR oh.Locator = 'FE17' THEN 'CSD'
            WHEN oh.Locator IN ('H300_TE0', 'HC01_TE0', 'HC02_TE0', 'HR02_TE0') THEN 'Khác'
            WHEN oh.Locator LIKE 'C%' OR oh.Locator LIKE 'H%' OR oh.Locator LIKE 'E%' OR oh.Locator LIKE 'F%' OR oh.Locator = 'FP02' OR oh.Locator = 'FP05' OR oh.Locator = 'M501' THEN 'CF'
            WHEN oh.Locator LIKE 'D%' OR oh.Locator = 'FE01' THEN 'Chung'
            ELSE 'Khác'
        END
    ) = ?";
    $params[] = $nganh_hang;
}

if (!empty($status)) {
    switch ($status) {
        case 'out_of_stock':
            $sql .= " AND COALESCE(oh.Onhand, 0) <= 0";
            break;
        case 'low_stock':
            $sql .= " AND p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock AND COALESCE(oh.Onhand, 0) > 0";
            break;
        case 'excess_stock':
            $sql .= " AND p.max_stock > 0 AND COALESCE(oh.Onhand, 0) > p.max_stock";
            break;
        case 'normal':
            $sql .= " AND COALESCE(oh.Onhand, 0) > 0 AND (p.min_stock <= 0 OR COALESCE(oh.Onhand, 0) >= p.min_stock) AND (p.max_stock <= 0 OR COALESCE(oh.Onhand, 0) <= p.max_stock)";
            break;
    }
}

if (!empty($bom_status)) {
    if ($bom_status === 'in_bom') {
        $sql .= " AND bi.part_id IS NOT NULL";
    } else {
        $sql .= " AND bi.part_id IS NULL";
    }
}

// Get total count
$countSql = "SELECT COUNT(DISTINCT oh.ItemCode) as total 
             FROM onhand oh
             LEFT JOIN parts p ON oh.ItemCode = p.part_code
             LEFT JOIN bom_items bi ON p.id = bi.part_id
             WHERE 1=1";

$countParams = [];
if (!empty($search)) {
    $countSql .= " AND (oh.ItemCode LIKE ? OR oh.Itemname LIKE ? OR p.part_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($nganh_hang)) {
    $countSql .= " AND (
        CASE 
            WHEN oh.Locator LIKE 'A%' OR oh.Locator LIKE 'B%' OR oh.Locator = 'FP09' OR oh.Locator = 'MA01' THEN 'Mắm'
            WHEN oh.Locator LIKE 'G%' OR oh.Locator = 'FE17' THEN 'CSD'
            WHEN oh.Locator IN ('H300_TE0', 'HC01_TE0', 'HC02_TE0', 'HR02_TE0') THEN 'Khác'
            WHEN oh.Locator LIKE 'C%' OR oh.Locator LIKE 'H%' OR oh.Locator LIKE 'E%' OR oh.Locator LIKE 'F%' OR oh.Locator = 'FP02' OR oh.Locator = 'FP05' OR oh.Locator = 'M501' THEN 'CF'
            WHEN oh.Locator LIKE 'D%' OR oh.Locator = 'FE01' THEN 'Chung'
            ELSE 'Khác'
        END
    ) = ?";
    $countParams[] = $nganh_hang;
}

if (!empty($status)) {
    switch ($status) {
        case 'out_of_stock':
            $countSql .= " AND COALESCE(oh.Onhand, 0) <= 0";
            break;
        case 'low_stock':
            $countSql .= " AND p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock AND COALESCE(oh.Onhand, 0) > 0";
            break;
        case 'excess_stock':
            $countSql .= " AND p.max_stock > 0 AND COALESCE(oh.Onhand, 0) > p.max_stock";
            break;
        case 'normal':
            $countSql .= " AND COALESCE(oh.Onhand, 0) > 0 AND (p.min_stock <= 0 OR COALESCE(oh.Onhand, 0) >= p.min_stock) AND (p.max_stock <= 0 OR COALESCE(oh.Onhand, 0) <= p.max_stock)";
            break;
    }
}

if (!empty($bom_status)) {
    if ($bom_status === 'in_bom') {
        $countSql .= " AND bi.part_id IS NOT NULL";
    } else {
        $countSql .= " AND bi.part_id IS NULL";
    }
}

try {
    $totalResult = $db->fetch($countSql, $countParams);
    $total = $totalResult['total'] ?? 0;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Lỗi count query: " . $e->getMessage() . "</div>";
    $total = 0;
}


$sql .= " GROUP BY oh.ItemCode 
          ORDER BY ngay_nhap_kho DESC, oh.Itemname ASC 
          LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
try {
    $inventory = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Lỗi main query: " . $e->getMessage() . "</div>";
    $inventory = [];
}

// Get categories for filter
try {
    $nganh_hangs = $db->fetchAll("
        SELECT DISTINCT 
            CASE 
                WHEN Locator LIKE 'A%' OR Locator LIKE 'B%' OR Locator = 'FP09' OR Locator = 'MA01' THEN 'Mắm'
                WHEN Locator LIKE 'G%' OR Locator = 'FE17' THEN 'CSD'
                WHEN Locator IN ('H300_TE0', 'HC01_TE0', 'HC02_TE0', 'HR02_TE0') THEN 'Khác'
                WHEN Locator LIKE 'C%' OR Locator LIKE 'H%' OR Locator LIKE 'E%' OR Locator LIKE 'F%' OR Locator = 'FP02' OR Locator = 'FP05' OR Locator = 'M501' THEN 'CF'
                WHEN Locator LIKE 'D%' OR Locator = 'FE01' THEN 'Chung'
                ELSE 'Khác'
            END as nganh_hang
        FROM onhand
        WHERE Locator IS NOT NULL
        ORDER BY nganh_hang
    ");
} catch (Exception $e) {
    $nganh_hangs = [];
}
// Get statistics với COALESCE
try {
    $stats = [
        'total_items' => $db->fetch("SELECT COUNT(*) as count FROM onhand")['count'] ?? 0,
        'in_bom_items' => $db->fetch("
            SELECT COUNT(DISTINCT oh.ItemCode) as count 
            FROM onhand oh 
            JOIN parts p ON oh.ItemCode = p.part_code 
            JOIN bom_items bi ON p.id = bi.part_id
        ")['count'] ?? 0,
        'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand WHERE COALESCE(Onhand, 0) <= 0")['count'] ?? 0,
        'low_stock' => $db->fetch("
            SELECT COUNT(*) as count 
            FROM onhand oh 
            LEFT JOIN parts p ON oh.ItemCode = p.part_code 
            WHERE p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock AND COALESCE(oh.Onhand, 0) > 0
        ")['count'] ?? 0,
        'total_value' => $db->fetch("SELECT SUM(COALESCE(OH_Value, 0)) as total FROM onhand")['total'] ?? 0
    ];
} catch (Exception $e) {
    echo "<div class='alert alert-warning'>Không thể tải thống kê: " . $e->getMessage() . "</div>";
    $stats = ['total_items' => 0, 'in_bom_items' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'total_value' => 0];
}

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
                        <div class="stat-number"><?php echo safeNumberFormat($stats['total_items']); ?></div>
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
                        <div class="stat-number"><?php echo safeNumberFormat($stats['in_bom_items']); ?></div>
                        <div class="stat-label">Vật tư trong BOM</div>
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
                        <div class="stat-number"><?php echo safeNumberFormat($stats['low_stock'] + $stats['out_of_stock']); ?></div>
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

<!-- Quick Search -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" id="quickSearch" 
                           placeholder="Tìm kiếm nhanh mã vật tư, tên vật tư..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="button" onclick="performQuickSearch()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div id="searchSuggestions" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
            <div class="col-md-6">
                <div class="btn-group w-100">
                    <button type="button" class="btn <?php echo empty($bom_status) ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="filterByBomStatus('all')">
                        Tất cả
                    </button>
                    <button type="button" class="btn <?php echo $bom_status === 'in_bom' ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="filterByBomStatus('in_bom')">
                        Trong BOM
                    </button>
                    <button type="button" class="btn <?php echo $bom_status === 'out_bom' ? 'btn-primary' : 'btn-outline-secondary'; ?>" onclick="filterByBomStatus('out_bom')">
                        Ngoài BOM
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Bộ lọc chi tiết
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Mã, tên vật tư...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Ngành hàng</label>
                <select class="form-select" name="nganh_hang">
                    <option value="">Tất cả</option>
                    <?php foreach ($nganh_hangs as $nh): ?>
                        <option value="<?php echo htmlspecialchars($nh['nganh_hang']); ?>" <?php echo $nganh_hang === $nh['nganh_hang'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nh['nganh_hang']); ?>
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
            <span class="badge bg-secondary ms-2"><?php echo safeNumberFormat($total); ?> mặt hàng</span>
        </h5>
        
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showAllTransactions()">
                <i class="fas fa-history me-1"></i>Lịch sử giao dịch
            </button>
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
                                <th style="width: 90px;">Ngày nhập</th>
                        <th style="width: 100px;" class="text-end">Tồn kho</th>
                        <th style="width: 60px;">ĐVT</th>
                        <th style="width: 100px;" class="text-end">Đơn giá</th>
                        <th style="width: 120px;" class="text-end">Tổng giá trị</th>
                        <th style="width: 100px;">Loại vật tư</th>
                        <th style="width: 80px;">Ngành hàng</th> 
                        <th style="width: 100px;">Trạng thái</th>
                        <th style="width: 100px;" class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="13" class="text-center py-4 text-muted">
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
 
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($item['Locator'] ?? '-'); ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($item['Lotnumber'] ?? '-'); ?></small>
                        </td>
                           <td>
        <?php 
        $ngayNhap = formatNgayNhapKho($item['ngay_nhap_kho']);
        $soNgay = tinhSoNgayTuNgayNhap($item['ngay_nhap_kho']);
        ?>
        <div class="text-center">
            <small class="fw-medium"><?php echo $ngayNhap; ?></small>
            <?php if ($soNgay !== null): ?>
                <br><small class="text-muted"><?php echo $soNgay; ?> ngày</small>
            <?php endif; ?>
        </div>
    </td>
                        <td class="text-end">
                            <span class="fw-bold <?php echo getStockQuantityClass($item); ?>">
                                <?php echo safeNumberFormat($item['Onhand'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['UOM'] ?? ''); ?></span>
                        </td>
                        <td class="text-end">
                            <?php echo safeCurrencyFormat($item['Price']); ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php echo safeCurrencyFormat($item['OH_Value']); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $item['bom_status'] === 'Trong BOM' ? 'bg-primary' : 'bg-secondary'; ?>">
                                <?php echo $item['bom_status']; ?>
                            </span>
                        </td>
                        <td>
        <span class="badge <?php echo getNganhHangClass($item['nganh_hang']); ?>">
            <?php echo htmlspecialchars($item['nganh_hang']); ?>
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
                                        onclick="showItemTransactions('<?php echo $item['ItemCode']; ?>')"
                                        title="Lịch sử giao dịch">
                                    <i class="fas fa-history"></i>
                                </button>
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

<!-- Modals -->
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
    $onhand = $item['Onhand'] ?? 0;
    if ($onhand <= 0) return 'text-danger';
    if (!empty($item['min_stock']) && $onhand < $item['min_stock']) return 'text-warning';
    return 'text-success';
}
// Helper function cho màu ngành hàng
function getNganhHangClass($nganh_hang) {
    switch ($nganh_hang) {
        case 'Mắm': return 'bg-primary';
        case 'CSD': return 'bg-success';
        case 'CF': return 'bg-warning';
        case 'Chung': return 'bg-info';
        default: return 'bg-secondary';
    }
}
function getStockStatusClass($status) {
    switch ($status) {
        case 'Hết hàng': return 'bg-danger';
        case 'Thiếu hàng': return 'bg-warning';
        case 'Dư thừa': return 'bg-info';
        default: return 'bg-success';
    }
}
// Helper function để parse ngày từ Lotnumber
function parseNgayNhapKho($lotnumber) {
    if (empty($lotnumber) || strlen($lotnumber) < 6) {
        return null;
    }
    
    $dateStr = substr($lotnumber, 0, 6);
    if (!preg_match('/^\d{6}$/', $dateStr)) {
        return null;
    }
    
    $day = substr($dateStr, 0, 2);
    $month = substr($dateStr, 2, 2);
    $year = '20' . substr($dateStr, 4, 2);
    
    // Kiểm tra tính hợp lệ của ngày
    if (!checkdate($month, $day, $year)) {
        return null;
    }
    
    return $year . '-' . $month . '-' . $day;
}

// Helper function để format ngày hiển thị
function formatNgayNhapKho($ngayNhapKho) {
    if (empty($ngayNhapKho)) {
        return '-';
    }
    
    try {
        $date = new DateTime($ngayNhapKho);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

// Helper function để tính số ngày từ ngày nhập
function tinhSoNgayTuNgayNhap($ngayNhapKho) {
    if (empty($ngayNhapKho)) {
        return null;
    }
    
    try {
        $ngayNhap = new DateTime($ngayNhapKho);
        $ngayHienTai = new DateTime();
        $diff = $ngayHienTai->diff($ngayNhap);
        return $diff->days;
    } catch (Exception $e) {
        return null;
    }
}
?>

<script>
function viewItemDetails(itemCode) {
    fetch(`api/item_details.php?item_code=${encodeURIComponent(itemCode)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('itemDetailsContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('itemDetailsModal')).show();
            } else {
                alert('Không thể tải chi tiết: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra');
        });
}


function showItemTransactions(itemCode) {
    fetch(`api/transactions.php?type=item&item_code=${encodeURIComponent(itemCode)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('transactionContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('transactionModal')).show();
            } else {
                alert('Không thể tải giao dịch: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra');
        });
}

function showAllTransactions() {
    fetch('api/transactions.php?type=all')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('transactionContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('transactionModal')).show();
            } else {
                alert('Không thể tải giao dịch: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra');
        });
}

function filterByBomStatus(status) {
    const url = new URL(window.location);
    if (status === 'all') {
        url.searchParams.delete('bom_status');
    } else {
        url.searchParams.set('bom_status', status);
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function performQuickSearch() {
    const query = document.getElementById('quickSearch').value.trim();
    if (query) {
        window.location.href = `index.php?search=${encodeURIComponent(query)}`;
    }
}

// Enter key support
document.getElementById('quickSearch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        performQuickSearch();
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>