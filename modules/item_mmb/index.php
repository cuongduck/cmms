<?php
/**
 * Item MMB Management - Index Page
 * /modules/item_mmb/index.php
 */

$pageTitle = 'Quản lý Item MMB';
$currentModule = 'item_mmb';
$moduleJS = 'item-mmb';

require_once 'config.php';

// Check permission
requirePermission('item_mmb', 'view');

// Breadcrumb
$breadcrumb = [
    ['title' => 'Item MMB', 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('item_mmb', 'export')) {
    $pageActions .= '<a href="export.php?' . http_build_query($_GET) . '" class="btn btn-success me-2">
        <i class="fas fa-file-excel me-1"></i>Export Excel
    </a>';
}

if (hasPermission('item_mmb', 'create')) {
    $pageActions .= '<button type="button" class="btn btn-primary" onclick="ItemMMB.showAddForm()">
        <i class="fas fa-plus me-1"></i>Thêm mới
    </button>';
}

require_once '../../includes/header.php';

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'vendor' => $_GET['vendor'] ?? '',
    'sort' => $_GET['sort'] ?? 'TIME_UPDATE',
    'order' => $_GET['order'] ?? 'DESC',
    'page' => max(1, intval($_GET['page'] ?? 1)),
    'limit' => ITEM_MMB_PER_PAGE
];

// Get data
$items = getItemsMMB($filters);
$totalItems = getItemsMMBCount($filters);
$vendors = getVendorsMMB();

// Pagination
$pagination = paginate($totalItems, $filters['page'], $filters['limit']);
?>

<style>
.editable-cell {
    cursor: pointer;
    position: relative;
    padding: 8px !important;
    min-height: 40px;
}

.editable-cell:hover {
    background-color: #f0f9ff;
    outline: 2px solid #3b82f6;
}

.editable-cell.editing {
    padding: 0 !important;
    background-color: #fff;
}

.editable-cell .edit-input {
    width: 100%;
    border: 2px solid #3b82f6;
    padding: 6px;
    font-size: 14px;
    box-shadow: 0 0 5px rgba(59, 130, 246, 0.3);
}

.editable-cell .edit-input:focus {
    outline: none;
    border-color: #2563eb;
}

.edit-icons {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
}

.editable-cell:hover .edit-icons {
    display: inline-block;
}

.editable-cell.editing .edit-icons {
    display: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(59, 130, 246, 0.05);
}

.inline-form-row {
    background-color: #f0fdf4;
    border: 2px solid #10b981;
}

.inline-form-row td {
    padding: 10px 5px !important;
}

.inline-form-row input,
.inline-form-row select {
    padding: 6px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.inline-form-row input:focus,
.inline-form-row select:focus {
    border-color: #10b981;
    outline: none;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.stat-card {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border-radius: 10px;
    padding: 20px;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.show {
    display: flex;
}
</style>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Tổng số items</h5>
                    <h2 class="mb-0"><?php echo number_format($totalItems); ?></h2>
                </div>
                <i class="fas fa-boxes fa-3x opacity-50"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Nhà cung cấp</h5>
                    <h2 class="mb-0"><?php echo count($vendors); ?></h2>
                </div>
                <i class="fas fa-truck fa-3x opacity-50"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Tổng giá trị</h5>
                    <h2 class="mb-0">
                        <?php 
                        $totalValue = array_sum(array_column($items, 'UNIT_PRICE'));
                        echo formatCurrencyTy($totalValue);
                        ?>
                    </h2>
                </div>
                <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Cập nhật gần đây</h5>
                    <h2 class="mb-0">
                        <?php 
                        $recentCount = $db->fetch("SELECT COUNT(*) as count FROM item_mmb WHERE DATE(TIME_UPDATE) = CURDATE()")['count'];
                        echo number_format($recentCount);
                        ?>
                    </h2>
                </div>
                <i class="fas fa-clock fa-3x opacity-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Tìm theo mã, tên item, NCC..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
            </div>
            
            <div class="col-lg-3">
                <select name="vendor" class="form-select">
                    <option value="">-- Tất cả nhà cung cấp --</option>
                    <?php foreach ($vendors as $vendor): ?>
                    <option value="<?php echo htmlspecialchars($vendor['VENDOR_NAME']); ?>"
                            <?php echo ($filters['vendor'] === $vendor['VENDOR_NAME']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vendor['VENDOR_NAME']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2">
                <select name="sort" class="form-select">
                    <option value="TIME_UPDATE" <?php echo ($filters['sort'] === 'TIME_UPDATE') ? 'selected' : ''; ?>>Ngày cập nhật</option>
                    <option value="ID" <?php echo ($filters['sort'] === 'ID') ? 'selected' : ''; ?>>ID</option>
                    <option value="ITEM_CODE" <?php echo ($filters['sort'] === 'ITEM_CODE') ? 'selected' : ''; ?>>Mã Item</option>
                    <option value="ITEM_NAME" <?php echo ($filters['sort'] === 'ITEM_NAME') ? 'selected' : ''; ?>>Tên Item</option>
                    <option value="UNIT_PRICE" <?php echo ($filters['sort'] === 'UNIT_PRICE') ? 'selected' : ''; ?>>Đơn giá</option>
                </select>
            </div>
            
            <div class="col-lg-1">
                <select name="order" class="form-select">
                    <option value="ASC" <?php echo ($filters['order'] === 'ASC') ? 'selected' : ''; ?>>↑</option>
                    <option value="DESC" <?php echo ($filters['order'] === 'DESC') ? 'selected' : ''; ?>>↓</option>
                </select>
            </div>
            
            <div class="col-lg-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Tìm
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Danh sách Items
            <span class="badge bg-primary ms-2"><?php echo number_format($totalItems); ?></span>
        </h5>
        <div>
            <?php echo $pageActions; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="itemsTable">
                <thead class="table-light">
                    <tr>

                        <th width="100">Mã Item</th>
                        <th>Tên Item</th>
                        <th width="100">ĐVT</th>
                        <th width="130">Đơn giá</th>
                        <th width="100">Mã NCC</th>
                        <th width="180">Tên NCC</th>
                        <th width="130">Ngày cập nhật</th>
                        <?php if (hasPermission('item_mmb', 'delete')): ?>
                        <th width="80">Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted">Không tìm thấy dữ liệu</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr data-id="<?php echo $item['ID']; ?>">

                        <td class="editable-cell" data-field="ITEM_CODE" data-id="<?php echo $item['ID']; ?>">
                            <?php echo htmlspecialchars($item['ITEM_CODE']); ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="ITEM_NAME" data-id="<?php echo $item['ID']; ?>">
                            <?php echo htmlspecialchars($item['ITEM_NAME']); ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="UOM" data-id="<?php echo $item['ID']; ?>">
                            <?php echo htmlspecialchars($item['UOM'] ?? ''); ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell text-end" data-field="UNIT_PRICE" data-id="<?php echo $item['ID']; ?>">
                            <?php echo $item['UNIT_PRICE'] ? number_format($item['UNIT_PRICE'], 2) : ''; ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="VENDOR_ID" data-id="<?php echo $item['ID']; ?>">
                            <?php 
                            if ($item['VENDOR_ID'] !== null && $item['VENDOR_ID'] !== '' && $item['VENDOR_ID'] !== 0) {
                                echo htmlspecialchars($item['VENDOR_ID']);
                            } else {
                                echo '<span class="text-muted fst-italic">Không rõ</span>';
                            }
                            ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="VENDOR_NAME" data-id="<?php echo $item['ID']; ?>">
                            <?php 
                            if ($item['VENDOR_NAME'] !== null && $item['VENDOR_NAME'] !== '') {
                                echo htmlspecialchars($item['VENDOR_NAME']);
                            } else {
                                echo '<span class="text-muted fst-italic">Không rõ nhà cung cấp</span>';
                            }
                            ?>
                            <?php if (hasPermission('item_mmb', 'edit')): ?>
                            <span class="edit-icons"><i class="fas fa-edit text-primary"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center small text-muted">
                            <?php 
if ($item['TIME_UPDATE']) {
    echo date('d/m/Y', strtotime($item['TIME_UPDATE']));
}
?>
                        </td>
                        <?php if (hasPermission('item_mmb', 'delete')): ?>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="ItemMMB.deleteItem(<?php echo $item['ID']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Hiển thị <?php echo (($pagination['current_page'] - 1) * $pagination['per_page'] + 1); ?> 
                đến <?php echo min($pagination['current_page'] * $pagination['per_page'], $totalItems); ?> 
                trong tổng số <?php echo number_format($totalItems); ?> items
            </div>
            <div>
                <?php 
                $currentUrl = 'index.php?search=' . urlencode($filters['search']) . 
                              '&vendor=' . urlencode($filters['vendor']) . 
                              '&sort=' . $filters['sort'] . 
                              '&order=' . $filters['order'];
                echo buildPaginationHtml($pagination, $currentUrl);
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>