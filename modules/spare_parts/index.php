<?php
/**
 * Spare Parts Management - Index Page
 * /modules/spare_parts/index.php
 */

$pageTitle = 'Quản lý Spare Parts';
$currentModule = 'spare_parts';
$moduleCSS = 'bom'; // Tái sử dụng CSS của BOM
$moduleJS = 'spare-parts';
require_once 'config.php';

// Check permission
requirePermission('spare_parts', 'view');

// Breadcrumb
$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => '']
];

// Page actions
$pageActions = '';
if (hasPermission('spare_parts', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Thêm spare part
    </a> ';
}

$pageActions .= '<a href="purchase_request.php" class="btn btn-warning">
    <i class="fas fa-shopping-cart me-2"></i>Đề xuất mua hàng
</a>';

require_once '../../includes/header.php';

// Get filters
$filters = [
    'category' => $_GET['category'] ?? '',
    'manager' => $_GET['manager'] ?? '',
    'stock_status' => $_GET['stock_status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get statistics
$stats = [
    'total_parts' => $db->fetch("SELECT COUNT(*) as count FROM spare_parts WHERE is_active = 1")['count'],
    'reorder_needed' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM spare_parts sp 
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE sp.is_active = 1 AND COALESCE(oh.Onhand, 0) <= sp.reorder_point
    ")['count'],
    'out_of_stock' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM spare_parts sp 
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE sp.is_active = 1 AND COALESCE(oh.Onhand, 0) = 0
    ")['count'],
    'critical_parts' => $db->fetch("SELECT COUNT(*) as count FROM spare_parts WHERE is_active = 1 AND is_critical = 1")['count']
];

// Get categories và managers for filters
$categories = $db->fetchAll("SELECT DISTINCT category FROM spare_parts WHERE category IS NOT NULL ORDER BY category");
$managers = $db->fetchAll("SELECT DISTINCT u.id, u.full_name FROM users u JOIN spare_parts sp ON (u.id = sp.manager_user_id OR u.id = sp.backup_manager_user_id) ORDER BY u.full_name");
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Tổng spare parts</h5>
                        <h2 class="mb-0"><?php echo number_format($stats['total_parts']); ?></h2>
                    </div>
                    <i class="fas fa-cubes fa-2x text-primary opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Cần đặt hàng</h5>
                        <h2 class="mb-0 text-warning"><?php echo number_format($stats['reorder_needed']); ?></h2>
                    </div>
                    <i class="fas fa-shopping-cart fa-2x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Hết hàng</h5>
                        <h2 class="mb-0 text-danger"><?php echo number_format($stats['out_of_stock']); ?></h2>
                    </div>
                    <i class="fas fa-exclamation-circle fa-2x text-danger opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Vật tư quan trọng</h5>
                        <h2 class="mb-0 text-info"><?php echo number_format($stats['critical_parts']); ?></h2>
                    </div>
                    <i class="fas fa-star fa-2x text-info opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<form class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" 
                   placeholder="Tìm theo mã, tên..." 
                   value="<?php echo htmlspecialchars($filters['search']); ?>">
        </div>
    </div>
    
    <div class="col-lg-2 col-md-6">
        <select name="category" class="form-select">
            <option value="">-- Tất cả danh mục --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                        <?php echo ($filters['category'] === $cat['category']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-lg-2 col-md-6">
        <select name="manager" class="form-select">
            <option value="">-- Tất cả quản lý --</option>
            <?php foreach ($managers as $manager): ?>
                <option value="<?php echo $manager['id']; ?>" 
                        <?php echo ($filters['manager'] == $manager['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($manager['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-lg-2 col-md-6">
        <select name="stock_status" class="form-select">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="reorder" <?php echo ($filters['stock_status'] === 'reorder') ? 'selected' : ''; ?>>Cần đặt hàng</option>
            <option value="low" <?php echo ($filters['stock_status'] === 'low') ? 'selected' : ''; ?>>Sắp hết/Hết hàng</option>
        </select>
    </div>
    
    <div class="col-lg-3 col-md-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search me-1"></i>Tìm
            </button>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i>Xóa
            </a>
        </div>
    </div>
</form>

<!-- Spare Parts Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-cubes me-2"></i>
            Danh sách Spare Parts
        </h5>
        <?php echo $pageActions; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>Mã vật tư</th>
                        <th>Tên vật tư</th>
                        <th>Danh mục</th>
                        <th>Tồn kho</th>
                        <th>Min/Max</th>
                        <th>Trạng thái</th>
                        <th>Người quản lý</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $spareParts = getSpareParts($filters);
                    if (empty($spareParts)): 
                    ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-cube fa-3x mb-2 d-block text-muted"></i>
                            Không tìm thấy spare part nào
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($spareParts as $part): ?>
                    <tr class="<?php echo $part['is_critical'] ? 'table-warning' : ''; ?>">
                        <td>
                            <span class="part-code"><?php echo htmlspecialchars($part['item_code']); ?></span>
                            <?php if ($part['is_critical']): ?>
                            <span class="badge badge-warning ms-1">Critical</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($part['item_name']); ?></strong>
                            <?php if ($part['description']): ?>
                            <small class="d-block text-muted"><?php echo htmlspecialchars(substr($part['description'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
<td>
    <div class="d-flex align-items-center gap-2">
        <span class="badge <?php echo getCategoryBadgeClass($part['category']); ?>">
            <?php echo htmlspecialchars($part['category'] ?? 'Vật tư khác'); ?>
        </span>
        <?php if ($part['category'] === 'Vật tư khác'): ?>
            <button onclick="reclassifyPart(<?php echo $part['id']; ?>)" 
                    class="btn btn-sm btn-outline-secondary" 
                    title="Phân loại lại">
                <i class="fas fa-sync-alt"></i>
            </button>
        <?php endif; ?>
    </div>
</td>                        <td class="text-center">
                            <strong><?php echo number_format($part['current_stock'], 2); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                        </td>
                        <td class="text-center">
                            <small><?php echo number_format($part['min_stock'], 0); ?> / <?php echo number_format($part['max_stock'], 0); ?></small>
                            <?php if ($part['suggested_order_qty'] > 0): ?>
                            <small class="d-block text-info">Đề xuất: <?php echo number_format($part['suggested_order_qty'], 0); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo getStockStatusClass($part['stock_status']); ?>">
                                <?php echo getStockStatusText($part['stock_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($part['manager_name']): ?>
                            <small><?php echo htmlspecialchars($part['manager_name']); ?></small>
                            <?php else: ?>
                            <small class="text-muted">Chưa phân công</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?php echo $part['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('spare_parts', 'edit')): ?>
                                <a href="edit.php?id=<?php echo $part['id']; ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-edit"></i>
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
</div>

<?php require_once '../../includes/footer.php'; ?>