<?php
$pageTitle = 'Danh sách thực hiện bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'view');

// Get filter parameters
$filters = [
    'execution_type' => $_GET['execution_type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'equipment_id' => $_GET['equipment_id'] ?? '',
    'plan_id' => $_GET['plan_id'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$sql = "SELECT me.*, e.code as equipment_code, e.name as equipment_name,
               mp.plan_code, mp.plan_name,
               u.full_name as assigned_name,
               i.name as industry_name, w.name as workshop_name
        FROM maintenance_executions me
        JOIN equipment e ON me.equipment_id = e.id
        LEFT JOIN maintenance_plans mp ON me.plan_id = mp.id
        LEFT JOIN users u ON me.assigned_to = u.id
        LEFT JOIN industries i ON e.industry_id = i.id
        LEFT JOIN workshops w ON e.workshop_id = w.id
        WHERE 1=1";

$params = [];

if (!empty($filters['execution_type'])) {
    $sql .= " AND me.execution_type = ?";
    $params[] = $filters['execution_type'];
}

if (!empty($filters['status'])) {
    $sql .= " AND me.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['equipment_id'])) {
    $sql .= " AND me.equipment_id = ?";
    $params[] = $filters['equipment_id'];
}

if (!empty($filters['plan_id'])) {
    $sql .= " AND me.plan_id = ?";
    $params[] = $filters['plan_id'];
}

if (!empty($filters['assigned_to'])) {
    $sql .= " AND me.assigned_to = ?";
    $params[] = $filters['assigned_to'];
}

if (!empty($filters['date_from'])) {
    $sql .= " AND DATE(me.scheduled_date) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND DATE(me.scheduled_date) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (me.execution_code LIKE ? OR me.title LIKE ? OR e.code LIKE ? OR e.name LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$search, $search, $search, $search]);
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as filtered";
$totalItems = $db->fetch($countSql, $params)['total'];

// Get data with pagination
$sql .= " ORDER BY me.scheduled_date DESC, me.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$executions = $db->fetchAll($sql, $params);

// Get filter options
$equipment = $db->fetchAll("
    SELECT e.id, e.code, e.name 
    FROM equipment e 
    WHERE e.maintenance_frequency_days > 0 
    ORDER BY e.code
");

$plans = $db->fetchAll("
    SELECT mp.id, mp.plan_code, mp.plan_name 
    FROM maintenance_plans mp 
    WHERE mp.status = 'active' 
    ORDER BY mp.plan_code
");

$users = $db->fetchAll("
    SELECT id, full_name 
    FROM users 
    WHERE status = 'active' 
    ORDER BY full_name
");

// Get statistics
$stats = $db->fetch("
    SELECT 
        COUNT(*) as total_executions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'planned' AND scheduled_date < NOW() THEN 1 ELSE 0 END) as overdue,
        AVG(CASE WHEN status = 'completed' AND actual_duration IS NOT NULL THEN actual_duration END) as avg_duration,
        SUM(CASE WHEN status = 'completed' THEN total_cost ELSE 0 END) as total_cost
    FROM maintenance_executions me
    WHERE me.scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Danh sách thực hiện', 'url' => '']
];

$pageActions = '<div class="btn-group">
    <a href="add.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Tạo lệnh mới
    </a>
    <button class="btn btn-info" onclick="exportExecutions()">
        <i class="fas fa-download"></i> Xuất Excel
    </button>
</div>';
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_executions']); ?></div>
                        <div class="stat-label">Tổng thực hiện</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['completed']); ?></div>
                        <div class="stat-label">Hoàn thành</div>
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
                        <i class="fas fa-cog fa-spin"></i>