<?php
/**
 * Inventory Management Main Page - WITH CATEGORY COLUMN
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
    return formatQuantityTy($number ?? 0);
}

function safeCurrencyFormat($amount) {
    return formatCurrencyTy($amount ?? 0);
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$nganh_hang = $_GET['nganh_hang'] ?? ''; 
$category = $_GET['category'] ?? ''; // THÊM MỚI
$status = $_GET['status'] ?? '';
$bom_status = $_GET['bom_status'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build base filter conditions for reuse
$baseFilterSql = "";
$baseFilterParams = [];

if (!empty($search)) {
    $baseFilterSql .= " AND (oh.ItemCode LIKE ? OR oh.Itemname LIKE ? OR p.part_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $baseFilterParams = array_merge($baseFilterParams, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($nganh_hang)) {
    $baseFilterSql .= " AND (
        CASE 
            WHEN oh.Locator LIKE 'A%' OR oh.Locator LIKE 'B%' OR oh.Locator = 'FP09' OR oh.Locator = 'MA01' THEN 'Mắm'
            WHEN oh.Locator LIKE 'G%' OR oh.Locator = 'FE17' THEN 'CSD'
            WHEN oh.Locator IN ('H300_TE0', 'HC01_TE0', 'HC02_TE0', 'HR02_TE0') THEN 'Khác'
            WHEN oh.Locator LIKE 'C%' OR oh.Locator LIKE 'H%' OR oh.Locator LIKE 'E%' OR oh.Locator LIKE 'F%' OR oh.Locator = 'FP02' OR oh.Locator = 'FP05' OR oh.Locator = 'M501' THEN 'CF'
            WHEN oh.Locator LIKE 'D%' OR oh.Locator = 'FE01' THEN 'Chung'
            ELSE 'Khác'
        END
    ) = ?";
    $baseFilterParams[] = $nganh_hang;
}

// THÊM FILTER THEO DANH MỤC
if (!empty($category)) {
    // Lấy keywords của category này
    $keywords = $db->fetchAll(
        "SELECT keyword FROM category_keywords WHERE category = ?",
        [$category]
    );
    
    if (!empty($keywords)) {
        $keywordConditions = [];
        foreach ($keywords as $kw) {
            $keywordConditions[] = "oh.Itemname LIKE ?";
            $baseFilterParams[] = '%' . $kw['keyword'] . '%';
        }
        $baseFilterSql .= " AND (" . implode(" OR ", $keywordConditions) . ")";
    }
}

if (!empty($status)) {
    switch ($status) {
        case 'out_of_stock':
            $baseFilterSql .= " AND COALESCE(oh.Onhand, 0) <= 0";
            break;
        case 'low_stock':
            $baseFilterSql .= " AND p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock AND COALESCE(oh.Onhand, 0) > 0";
            break;
        case 'excess_stock':
            $baseFilterSql .= " AND p.max_stock > 0 AND COALESCE(oh.Onhand, 0) > p.max_stock";
            break;
        case 'normal':
            $baseFilterSql .= " AND COALESCE(oh.Onhand, 0) > 0 AND (p.min_stock <= 0 OR COALESCE(oh.Onhand, 0) >= p.min_stock) AND (p.max_stock <= 0 OR COALESCE(oh.Onhand, 0) <= p.max_stock)";
            break;
    }
}

if (!empty($bom_status)) {
    if ($bom_status === 'in_bom') {
        $baseFilterSql .= " AND bi.part_id IS NOT NULL";
    } else {
        $baseFilterSql .= " AND bi.part_id IS NULL";
    }
}

// Build main SQL query - THÊM DANH MỤC TỰ ĐỘNG
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
    (
        SELECT ck.category 
        FROM category_keywords ck 
        WHERE oh.Itemname LIKE CONCAT('%', ck.keyword, '%')
        ORDER BY LENGTH(ck.keyword) DESC 
        LIMIT 1
    ) as auto_category,
    CASE 
        WHEN oh.Lotnumber IS NOT NULL AND LENGTH(oh.Lotnumber) >= 6 AND SUBSTRING(oh.Lotnumber, 1, 6) REGEXP '^[0-9]{6}$'
        THEN STR_TO_DATE(CONCAT(
            '20', SUBSTRING(oh.Lotnumber, 5, 2), '-',
            SUBSTRING(oh.Lotnumber, 3, 2), '-',
            SUBSTRING(oh.Lotnumber, 1, 2)
        ), '%Y-%m-%d')
        ELSE NULL
    END as ngay_nhap_kho
FROM onhand oh
LEFT JOIN parts p ON oh.ItemCode = p.part_code
LEFT JOIN bom_items bi ON p.id = bi.part_id
WHERE 1=1" . $baseFilterSql;

// Get total count with same filters
$countSql = "SELECT COUNT(DISTINCT oh.ItemCode) as total 
             FROM onhand oh
             LEFT JOIN parts p ON oh.ItemCode = p.part_code
             LEFT JOIN bom_items bi ON p.id = bi.part_id
             WHERE 1=1" . $baseFilterSql;

try {
    $totalResult = $db->fetch($countSql, $baseFilterParams);
    $total = $totalResult['total'] ?? 0;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Lỗi count query: " . $e->getMessage() . "</div>";
    $total = 0;
}

$sql .= " GROUP BY oh.ItemCode 
          ORDER BY ngay_nhap_kho DESC, oh.Itemname ASC 
          LIMIT ? OFFSET ?";
$params = array_merge($baseFilterParams, [$per_page, $offset]);

try {
    $inventory = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Lỗi main query: " . $e->getMessage() . "</div>";
    $inventory = [];
}

// Get categories for filter - LẤY TỪ BẢNG category_keywords
try {
    $categories = $db->fetchAll("
        SELECT DISTINCT category 
        FROM category_keywords 
        ORDER BY category ASC
    ");
} catch (Exception $e) {
    $categories = [];
}

// Get nganh_hangs for filter
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

// Get statistics với filter áp dụng
try {
    $statsBaseSql = "FROM onhand oh
                     LEFT JOIN parts p ON oh.ItemCode = p.part_code
                     LEFT JOIN bom_items bi ON p.id = bi.part_id
                     WHERE 1=1" . $baseFilterSql;

    $stats = [
        'total_items' => $db->fetch("SELECT COUNT(DISTINCT oh.ItemCode) as count " . $statsBaseSql, $baseFilterParams)['count'] ?? 0,
        'in_bom_items' => $db->fetch("SELECT COUNT(DISTINCT oh.ItemCode) as count " . $statsBaseSql . " AND bi.part_id IS NOT NULL", $baseFilterParams)['count'] ?? 0,
        'out_of_stock' => $db->fetch("SELECT COUNT(DISTINCT oh.ItemCode) as count " . $statsBaseSql . " AND COALESCE(oh.Onhand, 0) <= 0", $baseFilterParams)['count'] ?? 0,
        'low_stock' => $db->fetch("SELECT COUNT(DISTINCT oh.ItemCode) as count " . $statsBaseSql . " AND p.min_stock > 0 AND COALESCE(oh.Onhand, 0) < p.min_stock AND COALESCE(oh.Onhand, 0) > 0", $baseFilterParams)['count'] ?? 0,
        'total_value' => $db->fetch("SELECT SUM(COALESCE(oh.OH_Value, 0)) as total " . $statsBaseSql, $baseFilterParams)['total'] ?? 0,
        'excess_stock' => $db->fetch("SELECT COUNT(DISTINCT oh.ItemCode) as count " . $statsBaseSql . " AND p.max_stock > 0 AND COALESCE(oh.Onhand, 0) > p.max_stock", $baseFilterParams)['count'] ?? 0
    ];
    
} catch (Exception $e) {
    echo "<div class='alert alert-warning'>Không thể tải thống kê: " . $e->getMessage() . "</div>";
    $stats = ['total_items' => 0, 'in_bom_items' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'total_value' => 0, 'excess_stock' => 0];
}

$pagination = paginate($total, $page, $per_page, 'index.php');
?>

<!-- Statistics Cards -->
<!--<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo safeNumberFormat($stats['total_items']); ?></div>
                        <div class="stat-label">Item</div>
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
                        <small class="opacity-75">
                            <?php echo safeNumberFormat($stats['out_of_stock']); ?> hết | <?php echo safeNumberFormat($stats['low_stock']); ?> thiếu
                        </small>
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
                        <div class="stat-number"><?php echo safeCurrencyFormat($stats['total_value']); ?></div>
                        <div class="stat-label">VND</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>-->

<!-- Thông báo khi có filter -->
<?php if (!empty($search) || !empty($nganh_hang) || !empty($category) || !empty($status) || !empty($bom_status)): ?>
<div class="alert alert-info alert-dismissible fade show">
    <i class="fas fa-filter me-2"></i>
    <strong>Bộ lọc đang áp dụng:</strong>
    <?php
    $filters = [];
    if (!empty($search)) $filters[] = "Tìm kiếm: \"$search\"";
    if (!empty($nganh_hang)) $filters[] = "Ngành hàng: $nganh_hang";
    if (!empty($category)) $filters[] = "Danh mục: $category"; // THÊM MỚI
    if (!empty($status)) {
        $statusLabels = [
            'out_of_stock' => 'Hết hàng',
            'low_stock' => 'Thiếu hàng', 
            'excess_stock' => 'Dư thừa',
            'normal' => 'Bình thường'
        ];
        $filters[] = "Trạng thái: " . ($statusLabels[$status] ?? $status);
    }
    if (!empty($bom_status)) $filters[] = "Loại: " . ($bom_status === 'in_bom' ? 'Trong BOM' : 'Ngoài BOM');
    echo implode(', ', $filters);
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

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

<!-- Filters Card - THÊM SELECT DANH MỤC -->
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
            
            <!-- THÊM SELECT DANH MỤC -->
            <div class="col-md-2">
                <label class="form-label">Danh mục</label>
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

<!-- Inventory Table - THÊM CỘT DANH MỤC -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-warehouse me-2"></i>Tồn kho hiện tại
            <span class="badge bg-primary ms-2"><?php echo safeNumberFormat($total); ?> item</span>
                        <span class="badge bg-warning ms-2"><?php echo safeCurrencyFormat($stats['total_value']); ?> VND</span>

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
                        <th style="width: 120px;">Danh mục</th> <!-- THÊM CỘT MỚI -->
                        <th style="width: 80px;">Vị trí</th>
                        <th style="width: 80px;">Lot</th>
                        <th style="width: 90px;">Ngày nhập</th>
                        <th style="width: 100px;" class="text-end">Tồn kho</th>
                        <th style="width: 60px;">ĐVT</th>
                        <th style="width: 100px;" class="text-end">Đơn giá</th>
                        <th style="width: 120px;" class="text-end">Tổng giá trị</th>
                        <th style="width: 100px;">Loại vật tư</th>
                        <th style="width: 80px;">Ngành hàng</th> 
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
                        <!-- CỘT DANH MỤC MỚI -->
                        <td>
                            <?php if (!empty($item['auto_category'])): ?>
                                <span class="badge <?php echo getCategoryBadgeClass($item['auto_category']); ?>">
                                    <?php echo htmlspecialchars($item['auto_category']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Chưa phân loại</span>
                            <?php endif; ?>
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
                                <?php echo number_format($item['Onhand'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['UOM'] ?? ''); ?></span>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($item['Price'] ?? 0, 0); ?> đ
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

// THÊM FUNCTION MỚI: Màu badge cho danh mục
function getCategoryBadgeClass($category) {
    $categoryClasses = [
        'Biến tần' => 'bg-primary',
        'Servo' => 'bg-info',
        'Vật tư điện' => 'bg-warning text-dark',
        'PLC' => 'bg-success',
        'Van điện từ' => 'bg-danger',
        'Băng tải' => 'bg-secondary',
        'Dao lược' => 'bg-dark',
        'Điện trở' => 'bg-light text-dark',
        'Dây belt' => 'bg-primary',
        'Bạc đạn' => 'bg-info',
        'Ống' => 'bg-warning text-dark',
        'Xilanh' => 'bg-success',
        'Cảm biến' => 'bg-danger',
        'Motor' => 'bg-secondary',
        'Dao thớt' => 'bg-dark',
        'Nhông xích' => 'bg-primary',
        'Đồng hồ' => 'bg-info',
        'Vật tư khác' => 'bg-secondary'
    ];
    
    return $categoryClasses[$category] ?? 'bg-secondary';
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
    
    if (!checkdate($month, $day, $year)) {
        return null;
    }
    
    return $year . '-' . $month . '-' . $day;
}

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
    const loadingHtml = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
            <p class="mt-2">Đang tải chi tiết vật tư...</p>
        </div>
    `;
    
    document.getElementById('itemDetailsContent').innerHTML = loadingHtml;
    const modal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
    modal.show();
    
    fetch(`api/item_details.php?item_code=${encodeURIComponent(itemCode)}`)
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server trả về dữ liệu không phải JSON');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('itemDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('itemDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('itemDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Có lỗi xảy ra: ${error.message}
                </div>
            `;
        });
}

function showItemTransactions(itemCode) {
    const url = `/modules/transactions/?search=${encodeURIComponent(itemCode)}&search_all=1`;
    window.open(url, '_blank');
}

function showAllTransactions() {
    window.open('/modules/transactions/', '_blank');
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

let searchTimeout;
document.addEventListener('DOMContentLoaded', function() {
    const quickSearch = document.getElementById('quickSearch');
    const suggestions = document.getElementById('searchSuggestions');
    
    if (quickSearch) {
        quickSearch.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 300);
        });

        quickSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                suggestions.style.display = 'none';
                performQuickSearch();
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#quickSearch') && !e.target.closest('#searchSuggestions')) {
                suggestions.style.display = 'none';
            }
        });
    }
});

async function fetchSearchSuggestions(query) {
    try {
        const response = await fetch(`api/search_suggestions.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        const suggestions = document.getElementById('searchSuggestions');
        if (!data.suggestions || data.suggestions.length === 0) {
            suggestions.style.display = 'none';
            return;
        }

        let html = '';
        data.suggestions.forEach(item => {
            html += `
                <a href="#" class="dropdown-item suggestion-item" data-value="${item.value}">
                    <div class="fw-medium">${highlightMatch(item.label, query)}</div>
                    <small class="text-muted">${item.type}</small>
                </a>
            `;
        });

        suggestions.innerHTML = html;
        suggestions.style.display = 'block';

        suggestions.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('quickSearch').value = this.dataset.value;
                suggestions.style.display = 'none';
                performQuickSearch();
            });
        });

    } catch (error) {
        console.error('Error fetching suggestions:', error);
    }
}

function highlightMatch(text, query) {
    if (!query) return text;
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function animateValue(element, targetText, duration) {
    const originalText = targetText;
    let startValue = 0;
    
    let endValue = 0;
    if (targetText.includes('tỷ')) {
        endValue = parseFloat(targetText.replace(/[^\d.,]/g, '').replace(',', '.')) * 1000000000;
    } else if (targetText.includes('triệu')) {
        endValue = parseFloat(targetText.replace(/[^\d.,]/g, '').replace(',', '.')) * 1000000;
    } else if (targetText.includes('nghìn')) {
        endValue = parseFloat(targetText.replace(/[^\d.,]/g, '').replace(',', '.')) * 1000;
    } else {
        endValue = parseFloat(targetText.replace(/[^\d]/g, '')) || 0;
    }
    
    if (endValue === 0) {
        element.textContent = originalText;
        return;
    }
    
    const startTime = Date.now();
    const step = () => {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const currentValue = startValue + ((endValue - startValue) * progress);
        
        let displayText;
        if (currentValue >= 1000000000) {
            const value = currentValue / 1000000000;
            displayText = value.toFixed(2).replace('.0', '') + ' tỷ';
        } else if (currentValue >= 1000000) {
            const value = currentValue / 1000000;
            displayText = value.toFixed(2).replace('.0', '') + ' triệu';
        } else if (currentValue >= 1000) {
            const value = currentValue / 1000;
            displayText = value.toFixed(2).replace('.0', '') + ' nghìn';
        } else {
            displayText = Math.floor(currentValue).toLocaleString('vi-VN');
        }
        
        element.textContent = displayText;
        
        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            element.textContent = originalText;
        }
    };
    requestAnimationFrame(step);
}

window.addEventListener('load', function() {
    document.querySelectorAll('.stat-number').forEach(element => {
        const originalText = element.textContent;
        
        if (originalText && originalText !== '0') {
            element.textContent = '0';
            setTimeout(() => {
                animateValue(element, originalText, 500);
            }, 200);
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>