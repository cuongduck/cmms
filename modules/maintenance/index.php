<?php
$pageTitle = 'Quản lý bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../includes/header.php';
requirePermission('maintenance', 'view');

// Get filter parameters
$filters = [
    'maintenance_type' => $_GET['maintenance_type'] ?? '',
    'urgency' => $_GET['urgency'] ?? '',
    'industry_id' => $_GET['industry_id'] ?? '',
    'workshop_id' => $_GET['workshop_id'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'status' => $_GET['status'] ?? 'active',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query - Sử dụng LEFT JOIN thay vì view để tránh lỗi collation
$sql = "SELECT 
    e.id as equipment_id,
    e.code as equipment_code,
    e.name as equipment_name,
    e.maintenance_frequency_days,
    e.maintenance_frequency_type,
    mt.name as machine_type_name,
    i.name as industry_name,
    w.name as workshop_name,
    pl.name as line_name,
    a.name as area_name,
    mp.id as plan_id,
    mp.plan_code,
    mp.maintenance_type,
    mp.next_maintenance_date,
    mp.status as plan_status,
    CASE 
        WHEN mp.next_maintenance_date IS NULL THEN 'Chưa có kế hoạch'
        WHEN mp.next_maintenance_date < CURDATE() THEN 'Quá hạn'
        WHEN mp.next_maintenance_date = CURDATE() THEN 'Hôm nay'
        WHEN mp.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Trong tuần'
        WHEN mp.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Trong tháng'
        ELSE 'Tương lai'
    END as urgency_status,
    (
        SELECT MAX(me.completed_at)
        FROM maintenance_executions me 
        WHERE me.equipment_id = e.id 
        AND me.status = 'completed'
    ) as last_maintenance_date
FROM equipment e
LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
LEFT JOIN industries i ON e.industry_id = i.id
LEFT JOIN workshops w ON e.workshop_id = w.id
LEFT JOIN production_lines pl ON e.line_id = pl.id
LEFT JOIN areas a ON e.area_id = a.id
LEFT JOIN maintenance_plans mp ON e.id = mp.equipment_id AND mp.status = 'active'
WHERE e.maintenance_frequency_days > 0";

$params = [];

if (!empty($filters['maintenance_type'])) {
    $sql .= " AND mp.maintenance_type = ?";
    $params[] = $filters['maintenance_type'];
}

if (!empty($filters['urgency'])) {
    switch($filters['urgency']) {
        case 'Quá hạn':
            $sql .= " AND mp.next_maintenance_date < CURDATE()";
            break;
        case 'Hôm nay':
            $sql .= " AND mp.next_maintenance_date = CURDATE()";
            break;
        case 'Trong tuần':
            $sql .= " AND mp.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND mp.next_maintenance_date > CURDATE()";
            break;
        case 'Trong tháng':
            $sql .= " AND mp.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND mp.next_maintenance_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
    }
}

if (!empty($filters['industry_id'])) {
    $sql .= " AND e.industry_id = ?";
    $params[] = $filters['industry_id'];
}

if (!empty($filters['workshop_id'])) {
    $sql .= " AND e.workshop_id = ?";
    $params[] = $filters['workshop_id'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (e.code LIKE ? OR e.name LIKE ? OR mp.plan_code LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$search, $search, $search]);
}

// Count total - Sử dụng query đơn giản hơn
$countSql = "SELECT COUNT(DISTINCT e.id) as total 
             FROM equipment e 
             LEFT JOIN maintenance_plans mp ON e.id = mp.equipment_id AND mp.status = 'active'
             WHERE e.maintenance_frequency_days > 0";

$countParams = [];
if (!empty($filters['industry_id'])) {
    $countSql .= " AND e.industry_id = ?";
    $countParams[] = $filters['industry_id'];
}

if (!empty($filters['workshop_id'])) {
    $countSql .= " AND e.workshop_id = ?";
    $countParams[] = $filters['workshop_id'];
}

try {
    $totalItems = $db->fetch($countSql, $countParams)['total'];
    
    // Get data with pagination
    $sql .= " ORDER BY 
        CASE 
            WHEN mp.next_maintenance_date < CURDATE() THEN 1
            WHEN mp.next_maintenance_date = CURDATE() THEN 2
            WHEN mp.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 3
            ELSE 4
        END,
        mp.next_maintenance_date
        LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $maintenanceItems = $db->fetchAll($sql, $params);
    
} catch (Exception $e) {
    // Nếu vẫn lỗi, dùng query backup đơn giản
    error_log("Maintenance query error: " . $e->getMessage());
    
    $totalItems = 0;
    $maintenanceItems = $db->fetchAll("
        SELECT 
            e.id as equipment_id,
            e.code as equipment_code,
            e.name as equipment_name,
            e.maintenance_frequency_days,
            mt.name as machine_type_name,
            i.name as industry_name,
            w.name as workshop_name,
            'Chưa có kế hoạch' as urgency_status
        FROM equipment e
        LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
        LEFT JOIN industries i ON e.industry_id = i.id
        LEFT JOIN workshops w ON e.workshop_id = w.id
        WHERE e.maintenance_frequency_days > 0
        LIMIT 20
    ");
}

// Get filter options
$industries = $db->fetchAll("SELECT id, name FROM industries WHERE status = 'active' ORDER BY name");
$workshops = $db->fetchAll("SELECT id, name, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
$users = $db->fetchAll("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name");

// Get statistics - Query đơn giản để tránh lỗi
try {
    $stats = $db->fetch("
        SELECT 
            COUNT(e.id) as total_equipment,
            0 as overdue,
            0 as today,
            0 as this_week,
            COUNT(e.id) as no_plan
        FROM equipment e
        WHERE e.maintenance_frequency_days > 0
    ");
} catch (Exception $e) {
    $stats = [
        'total_equipment' => 0,
        'overdue' => 0,
        'today' => 0,
        'this_week' => 0,
        'no_plan' => 0
    ];
}

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '']
];

$pageActions = '<div class="btn-group">
    <a href="plans/add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Thêm kế hoạch BT
    </a>
    <a href="executions/add.php" class="btn btn-success">
        <i class="fas fa-wrench"></i> Bảo trì sự cố
    </a>
    <a href="reports/" class="btn btn-info">
        <i class="fas fa-chart-bar"></i> Báo cáo
    </a>
</div>';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_equipment']); ?></div>
                        <div class="stat-label">Tổng thiết bị BT</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['overdue']); ?></div>
                        <div class="stat-label">Quá hạn</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['today'] + $stats['this_week']); ?></div>
                        <div class="stat-label">Trong tuần</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['no_plan']); ?></div>
                        <div class="stat-label">Chưa có kế hoạch</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Loại bảo trì</label>
                <select name="maintenance_type" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="PM" <?php echo $filters['maintenance_type'] === 'PM' ? 'selected' : ''; ?>>Kế hoạch (PM)</option>
                    <option value="BREAKDOWN" <?php echo $filters['maintenance_type'] === 'BREAKDOWN' ? 'selected' : ''; ?>>Sự cố</option>
                    <option value="CLIT" <?php echo $filters['maintenance_type'] === 'CLIT' ? 'selected' : ''; ?>>CLIT</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Mức độ khẩn cấp</label>
                <select name="urgency" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="Quá hạn" <?php echo $filters['urgency'] === 'Quá hạn' ? 'selected' : ''; ?>>Quá hạn</option>
                    <option value="Hôm nay" <?php echo $filters['urgency'] === 'Hôm nay' ? 'selected' : ''; ?>>Hôm nay</option>
                    <option value="Trong tuần" <?php echo $filters['urgency'] === 'Trong tuần' ? 'selected' : ''; ?>>Trong tuần</option>
                    <option value="Trong tháng" <?php echo $filters['urgency'] === 'Trong tháng' ? 'selected' : ''; ?>>Trong tháng</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Ngành</label>
                <select name="industry_id" class="form-select" onchange="loadWorkshops(this.value)">
                    <option value="">Tất cả ngành</option>
                    <?php echo buildSelectOptions($industries, 'id', 'name', $filters['industry_id']); ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Xưởng</label>
                <select name="workshop_id" class="form-select" id="workshop_select">
                    <option value="">Tất cả xưởng</option>
                    <?php
                    $filteredWorkshops = $filters['industry_id'] ? 
                        array_filter($workshops, fn($w) => $w['industry_id'] == $filters['industry_id']) : 
                        $workshops;
                    echo buildSelectOptions($filteredWorkshops, 'id', 'name', $filters['workshop_id']);
                    ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Mã thiết bị, tên thiết bị..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Main Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-wrench me-2"></i>
            Kế hoạch bảo trì thiết bị
            <span class="badge badge-secondary ms-2"><?php echo number_format($totalItems); ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Thiết bị</th>
                        <th>Vị trí</th>
                        <th>Loại BT</th>
                        <th>Tần suất</th>
                        <th>Lần cuối</th>
                        <th>Kế hoạch tiếp theo</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($maintenanceItems)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i><br>
                            Không tìm thấy thiết bị nào cần bảo trì
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($maintenanceItems as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($item['equipment_code']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($item['equipment_name']); ?></div>
                        </td>
                        <td>
                            <div class="small">
                                <?php echo htmlspecialchars($item['industry_name']); ?><br>
                                <?php echo htmlspecialchars($item['workshop_name']); ?>
                                <?php if (!empty($item['line_name'])): ?>
                                    <br><?php echo htmlspecialchars($item['line_name']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($item['maintenance_type'])): ?>
                                <span class="badge badge-<?php echo $item['maintenance_type'] === 'PM' ? 'info' : ($item['maintenance_type'] === 'CLIT' ? 'warning' : 'danger'); ?>">
                                    <?php 
                                    echo $item['maintenance_type'] === 'PM' ? 'Kế hoạch' : 
                                        ($item['maintenance_type'] === 'CLIT' ? 'CLIT' : 'Sự cố'); 
                                    ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Chưa có</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $item['maintenance_frequency_days']; ?> ngày
                        </td>
                        <td>
                            <?php if (!empty($item['last_maintenance_date'])): ?>
                                <?php echo formatDateTime($item['last_maintenance_date'], 'd/m/Y'); ?>
                            <?php else: ?>
                                <span class="text-muted">Chưa có</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($item['next_maintenance_date'])): ?>
                                <?php echo formatDate($item['next_maintenance_date']); ?>
                            <?php else: ?>
                                <span class="text-muted">Chưa lên kế hoạch</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $urgencyClass = match($item['urgency_status'] ?? 'Chưa có kế hoạch') {
                                'Quá hạn' => 'badge-danger',
                                'Hôm nay' => 'badge-warning',
                                'Trong tuần' => 'badge-info',
                                'Trong tháng' => 'badge-secondary',
                                default => 'badge-light'
                            };
                            ?>
                            <span class="badge <?php echo $urgencyClass; ?>">
                                <?php echo $item['urgency_status'] ?? 'Chưa có kế hoạch'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if (!empty($item['plan_id'])): ?>
                                    <a href="plans/view.php?id=<?php echo $item['plan_id']; ?>" 
                                       class="btn btn-outline-info" title="Xem kế hoạch">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasPermission('maintenance', 'edit')): ?>
                                    <a href="plans/edit.php?id=<?php echo $item['plan_id']; ?>" 
                                       class="btn btn-outline-primary" title="Sửa kế hoạch">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="executions/add.php?plan_id=<?php echo $item['plan_id']; ?>" 
                                       class="btn btn-outline-success" title="Thực hiện">
                                        <i class="fas fa-play"></i>
                                    </a>
                                <?php else: ?>
                                    <?php if (hasPermission('maintenance', 'create')): ?>
                                    <a href="plans/add.php?equipment_id=<?php echo $item['equipment_id']; ?>" 
                                       class="btn btn-outline-primary" title="Tạo kế hoạch">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="executions/add.php?equipment_id=<?php echo $item['equipment_id']; ?>" 
                                       class="btn btn-outline-warning" title="Bảo trì sự cố">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="btn-group">
                                    <button class="btn btn-outline-secondary dropdown-toggle" 
                                            data-bs-toggle="dropdown" title="Thêm">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" 
                                               href="executions/?equipment_id=<?php echo $item['equipment_id']; ?>">
                                            <i class="fas fa-history me-2"></i>Lịch sử BT
                                        </a></li>
                                        <li><a class="dropdown-item" 
                                               href="../equipment/view.php?id=<?php echo $item['equipment_id']; ?>">
                                            <i class="fas fa-cog me-2"></i>Chi tiết thiết bị
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalItems > $perPage): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted">
                Hiển thị <?php echo min($offset + 1, $totalItems); ?> - 
                <?php echo min($offset + $perPage, $totalItems); ?> 
                trong tổng số <?php echo $totalItems; ?> thiết bị
            </div>
            <?php
            $pagination = paginate($totalItems, $page, $perPage);
            echo buildPaginationHtml($pagination, '?' . http_build_query(array_merge($_GET, ['page' => ''])));
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function loadWorkshops(industryId) {
    const workshopSelect = document.getElementById('workshop_select');
    workshopSelect.innerHTML = '<option value="">Đang tải...</option>';
    
    if (!industryId) {
        workshopSelect.innerHTML = '<option value="">Tất cả xưởng</option>';
        return;
    }
    
    fetch(`api/workshops.php?industry_id=${industryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let options = '<option value="">Tất cả xưởng</option>';
                data.workshops.forEach(workshop => {
                    options += `<option value="${workshop.id}">${workshop.name}</option>`;
                });
                workshopSelect.innerHTML = options;
            } else {
                workshopSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            workshopSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>