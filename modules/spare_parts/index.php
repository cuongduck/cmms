<?php
/**
 * Spare Parts Management - Index Page
 * /modules/spare_parts/index.php
 */

$pageTitle = 'Quản lý Spare Parts';
$currentModule = 'spare_parts';
$moduleCSS = 'bom';
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
        <i class="fas fa-plus me-2"></i>Thêm part
    </a> ';
}

// NÚT XUẤT TEMPLATE
$pageActions .= '<a href="api/spare_parts.php?action=export_template" class="btn btn-success">
    <i class="fas fa-file-excel me-2"></i>Xuất Template
</a> ';

// NÚT IMPORT
if (hasPermission('spare_parts', 'create')) {
    $pageActions .= '<button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="fas fa-file-import me-2"></i>Import Excel
    </button> ';
}

$pageActions .= '<a href="purchase_request.php" class="btn btn-warning">
    <i class="fas fa-shopping-cart me-2"></i>Đề xuất mua
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
// Get statistics
$stats = [
    'total_parts' => $db->fetch("SELECT COUNT(*) as count FROM spare_parts")['count'],
    'reorder_needed' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM spare_parts sp 
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) <= sp.reorder_point
    ")['count'],
    'out_of_stock' => $db->fetch("
        SELECT COUNT(*) as count 
        FROM spare_parts sp 
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE COALESCE(oh.Onhand, 0) = 0
    ")['count'],
    'critical_parts' => $db->fetch("SELECT COUNT(*) as count FROM spare_parts WHERE is_critical = 1")['count']
];
// Tính toán giá trị Max Policy và Budget năm
$financialStats = $db->fetch("
    SELECT 
        SUM(sp.max_stock * COALESCE(oh.Price, sp.standard_cost)) as max_policy_value,
        SUM(COALESCE(oh.Onhand, 0) * COALESCE(oh.Price, sp.standard_cost)) as current_value,
        SUM(sp.estimated_annual_usage * COALESCE(oh.Price, sp.standard_cost)) as annual_budget
    FROM spare_parts sp
    LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
");

$maxPolicyValue = $financialStats['max_policy_value'] ?? 0;
$currentValue = $financialStats['current_value'] ?? 0;
$remainingBudget = $maxPolicyValue - $currentValue;
$annualBudget = $financialStats['annual_budget'] ?? 0;
// Get categories và managers
$categories = getActiveCategories();
$managers = $db->fetchAll("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u 
    JOIN spare_parts sp ON u.id = sp.manager_user_id 
    ORDER BY u.full_name
");

// Get categories và managers for filters
$categories = getActiveCategories();
$managers = $db->fetchAll("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u 
    JOIN spare_parts sp ON u.id = sp.manager_user_id 
    WHERE sp.is_active = 1
    ORDER BY u.full_name
");

// Helper function để lấy badge class cho category
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
?>
<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}
</style>
<!-- Statistics Cards - Redesigned -->
<div class="row g-3 mb-4">
    <!-- Row 1: Main Stats - 4 cards compact -->
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="fas fa-cubes fa-lg text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small">Tổng spare parts</h6>
                        <h4 class="mb-0"><?php echo number_format($stats['total_parts']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="fas fa-shopping-cart fa-lg text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small">Cần đặt hàng</h6>
                        <h4 class="mb-0"><?php echo number_format($stats['reorder_needed']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                            <i class="fas fa-exclamation-circle fa-lg text-danger"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small">Hết hàng</h6>
                        <h4 class="mb-0"><?php echo number_format($stats['out_of_stock']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="fas fa-star fa-lg text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1 small">Vật tư quan trọng</h6>
                        <h4 class="mb-0"><?php echo number_format($stats['critical_parts']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Financial Stats -->
<div class="row g-3 mb-4">
    <!-- Max Policy Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fas fa-wallet text-primary me-2"></i>
                    Giá trị Max Policy
                </h6>
                
                <div class="row text-center g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block mb-1">Tối đa</small>
                            <strong class="text-primary d-block"><?php echo number_format($maxPolicyValue / 1000000, 1); ?>M</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block mb-1">Hiện tại</small>
                            <strong class="text-success d-block"><?php echo number_format($currentValue / 1000000, 1); ?>M</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block mb-1">Còn lại</small>
                            <strong class="text-warning d-block"><?php echo number_format($remainingBudget / 1000000, 1); ?>M</strong>
                        </div>
                    </div>
                </div>
                
                <div class="progress" style="height: 6px;">
                    <?php $policyPercentage = $maxPolicyValue > 0 ? ($currentValue / $maxPolicyValue) * 100 : 0; ?>
                    <div class="progress-bar bg-success" style="width: <?php echo min(100, $policyPercentage); ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">0%</small>
                    <small class="fw-bold"><?php echo number_format($policyPercentage, 1); ?>%</small>
                    <small class="text-muted">100%</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Annual Budget Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-3">
                    <i class="fas fa-chart-line text-success me-2"></i>
                    Budget dự kiến năm <?php echo date('Y'); ?>
                </h6>
                
                <div class="row text-center g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block mb-1">Tổng năm</small>
                            <h5 class="text-success mb-0"><?php echo number_format($annualBudget / 1000000, 1); ?>M</h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block mb-1">Trung bình/tháng</small>
                            <h5 class="text-info mb-0"><?php echo number_format($annualBudget / 12 / 1000000, 1); ?>M</h5>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-calculator me-1"></i>
                        Dựa trên dự kiến sử dụng
                    </small>
                    <button class="btn btn-sm btn-outline-success" onclick="showBudgetDetails()">
                        <i class="fas fa-chart-pie me-1"></i>Chi tiết
                    </button>
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
       <select name="category" class="form-select" id="categoryFilter">
    <option value="">Tất cả danh mục</option>
    <?php foreach ($categories as $cat): ?>
        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['category']) ? 'selected' : ''; ?>>
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
        <div>
            <?php echo $pageActions; ?>
        </div>
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
                        <th>SL dùng năm</th> <!-- THÊM CỘT NÀY -->
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
                        <td><?php echo htmlspecialchars($part['auto_category'] ?? 'Chưa phân loại'); ?></td>

                        <td class="text-center">
                            <strong><?php echo number_format($part['current_stock'], 2); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?></small>
                        </td>
                        <td class="text-center">
                            <small><?php echo number_format($part['min_stock'], 0); ?> / <?php echo number_format($part['max_stock'], 0); ?></small>
                            <?php if ($part['suggested_order_qty'] > 0): ?>
                            <small class="d-block text-info">Đề xuất: <?php echo number_format($part['suggested_order_qty'], 0); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
    <?php if ($part['estimated_annual_usage'] > 0): ?>
        <strong><?php echo number_format($part['estimated_annual_usage'], 0); ?></strong>
        <small class="d-block text-muted"><?php echo htmlspecialchars($part['stock_unit']); ?>/năm</small>
        <?php 
        // Tính số tháng tồn kho còn đủ
        $monthsRemaining = $part['current_stock'] > 0 && $part['estimated_annual_usage'] > 0 
            ? round(($part['current_stock'] / $part['estimated_annual_usage']) * 12, 1) 
            : 0;
        if ($monthsRemaining > 0 && $monthsRemaining < 6):
        ?>
            <small class="text-warning">Còn <?php echo $monthsRemaining; ?> tháng</small>
        <?php endif; ?>
    <?php else: ?>
        <small class="text-muted">Chưa cập nhật</small>
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
        <a href="view.php?id=<?php echo $part['id']; ?>" class="btn btn-outline-primary btn-sm" title="Xem chi tiết">
            <i class="fas fa-eye"></i>
        </a>
        <?php if (hasPermission('spare_parts', 'edit')): ?>
        <a href="edit.php?id=<?php echo $part['id']; ?>" class="btn btn-outline-warning btn-sm" title="Chỉnh sửa">
            <i class="fas fa-edit"></i>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('spare_parts', 'delete')): ?>
        <button onclick="deleteSparePart(<?php echo $part['id']; ?>, '<?php echo htmlspecialchars($part['item_code']); ?>')" 
                class="btn btn-outline-danger btn-sm" title="Xóa">
            <i class="fas fa-trash"></i>
        </button>
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
<script>
function deleteSparePart(id, itemCode) {
    if (!confirm(`⚠️ CẢNH BÁO: Bạn có CHẮC CHẮN muốn XÓA VĨNH VIỄN spare part "${itemCode}"?\n\n❌ Dữ liệu sẽ bị XÓA HOÀN TOÀN và KHÔNG THỂ KHÔI PHỤC!\n\nNhấn OK để xác nhận xóa vĩnh viễn.`)) {
        return;
    }
    
    // Double confirm
    if (!confirm(`Xác nhận lần cuối: Xóa vĩnh viễn "${itemCode}"?`)) {
        return;
    }
    
    CMMS.ajax({
        url: 'api/spare_parts.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'delete',
            id: id
        }),
        success: function(data) {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(error, response) {
            console.error('Delete error:', error);
            if (response && response.message) {
                CMMS.showToast(response.message, 'error');
            } else {
                CMMS.showToast('Không thể xóa spare part', 'error');
            }
        }
    });
}

function reclassifyPart(id) {
    if (!confirm('Bạn có muốn phân loại lại vật tư này?')) {
        return;
    }
    
    CMMS.ajax({
        url: 'api/spare_parts.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'reclassify',
            id: id
        }),
        success: function(data) {
            if (data.success) {
                CMMS.showToast(`${data.message}\nDanh mục mới: ${data.data.new_category}`, 'success');
                
                // Reload trang sau 1.5 giây
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
            }
        },
        error: function(error, response) {
            console.error('Reclassify error:', error);
            CMMS.showToast('Không thể phân loại lại', 'error');
        }
    });
}
</script>
<script>
function showBudgetDetails() {
    CMMS.showLoading();
    
    fetch('api/spare_parts.php?action=budget_details')
        .then(response => response.json())
        .then(data => {
            CMMS.hideLoading();
            
            if (data.success && data.data.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
                html += '<thead class="table-light"><tr><th>Danh mục</th><th class="text-end">Budget năm</th><th class="text-end">Budget/tháng</th></tr></thead><tbody>';
                
                let total = 0;
                data.data.forEach(item => {
                    total += parseFloat(item.annual_budget || 0);
                    html += `<tr>
                        <td>${item.category || 'Chưa phân loại'}</td>
                        <td class="text-end">${formatMoney(item.annual_budget)}</td>
                        <td class="text-end">${formatMoney(item.annual_budget / 12)}</td>
                    </tr>`;
                });
                
                html += `<tr class="table-light fw-bold">
                    <td>TỔNG</td>
                    <td class="text-end">${formatMoney(total)}</td>
                    <td class="text-end">${formatMoney(total / 12)}</td>
                </tr>`;
                html += '</tbody></table></div>';
                
                // Hiển thị trong modal Bootstrap
                const modalHtml = `
                    <div class="modal fade" id="budgetModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Chi tiết Budget theo Danh mục
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">${html}</div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remove old modal if exists
                const oldModal = document.getElementById('budgetModal');
                if (oldModal) oldModal.remove();
                
                // Add and show new modal
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                const modal = new bootstrap.Modal(document.getElementById('budgetModal'));
                modal.show();
            } else {
                CMMS.showToast('Chưa có dữ liệu budget', 'warning');
            }
        })
        .catch(error => {
            CMMS.hideLoading();
            console.error('Error:', error);
            CMMS.showToast('Không thể tải chi tiết budget', 'error');
        });
}

function formatMoney(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount || 0);
}
</script>
<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i>
                    Import Spare Parts từ Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Hướng dẫn:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Tải template Excel bằng nút "Xuất Template Excel"</li>
                        <li>Điền thông tin vào file Excel (không sửa header)</li>
                        <li>Chọn file và nhấn "Import"</li>
                        <li>Hệ thống sẽ <strong>bỏ qua các mã vật tư đã tồn tại</strong></li>
                    </ol>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Chọn file Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excel_file" 
                               accept=".xlsx,.xls" required>
                        <small class="text-muted">Chỉ chấp nhận file .xlsx hoặc .xls</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="updateExisting" name="update_existing">
                        <label class="form-check-label" for="updateExisting">
                            Cập nhật nếu mã vật tư đã tồn tại (mặc định: bỏ qua)
                        </label>
                    </div>
                    
                    <div id="importProgress" class="d-none">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="importStatus" class="text-center"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="handleImport()">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function handleImport() {
    const form = document.getElementById('importForm');
    const fileInput = document.getElementById('excelFile');
    
    if (!fileInput.files.length) {
        CMMS.showToast('Vui lòng chọn file Excel', 'warning');
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'import_excel');
    
    const progressDiv = document.getElementById('importProgress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const statusDiv = document.getElementById('importStatus');
    
    progressDiv.classList.remove('d-none');
    progressBar.style.width = '0%';
    statusDiv.textContent = 'Đang xử lý...';
    
    CMMS.showLoading();
    
    fetch('api/spare_parts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        CMMS.hideLoading();
        progressBar.style.width = '100%';
        
        if (data.success) {
            statusDiv.innerHTML = `
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Import thành công!</strong><br>
                    - Đã thêm: ${data.data.inserted} mục<br>
                    - Đã cập nhật: ${data.data.updated} mục<br>
                    - Bỏ qua (trùng): ${data.data.skipped} mục<br>
                    ${data.data.errors > 0 ? `- Lỗi: ${data.data.errors} mục` : ''}
                </div>
            `;
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${data.message || 'Có lỗi xảy ra'}
                </div>
            `;
        }
    })
    .catch(error => {
        CMMS.hideLoading();
        console.error('Error:', error);
        statusDiv.innerHTML = `
            <div class="alert alert-danger mt-3">
                Lỗi kết nối: ${error.message}
            </div>
        `;
    });
}
</script>
<?php require_once '../../includes/footer.php'; ?>