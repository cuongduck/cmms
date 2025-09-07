<?php
$pageTitle = 'Chi tiết kế hoạch bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'view');

$planId = $_GET['id'] ?? null;

if (!$planId) {
    header('Location: ../index.php?error=ID không hợp lệ');
    exit;
}

// Get plan details
$sql = "SELECT mp.*, e.code as equipment_code, e.name as equipment_name,
               e.serial_number, e.manufacturer, e.model, e.location_details,
               mt.name as machine_type_name, i.name as industry_name, 
               w.name as workshop_name, pl.name as line_name, a.name as area_name,
               u1.full_name as assigned_name, u1.email as assigned_email,
               u2.full_name as backup_name, u2.email as backup_email,
               u3.full_name as created_by_name
        FROM maintenance_plans mp
        JOIN equipment e ON mp.equipment_id = e.id
        JOIN machine_types mt ON e.machine_type_id = mt.id
        JOIN industries i ON e.industry_id = i.id
        JOIN workshops w ON e.workshop_id = w.id
        LEFT JOIN production_lines pl ON e.line_id = pl.id
        LEFT JOIN areas a ON e.area_id = a.id
        LEFT JOIN users u1 ON mp.assigned_to = u1.id
        LEFT JOIN users u2 ON mp.backup_assigned_to = u2.id
        LEFT JOIN users u3 ON mp.created_by = u3.id
        WHERE mp.id = ?";

$plan = $db->fetch($sql, [$planId]);

if (!$plan) {
    header('Location: ../index.php?error=Không tìm thấy kế hoạch');
    exit;
}

// Get recent executions
$executions = $db->fetchAll("
    SELECT me.*, u.full_name as assigned_name
    FROM maintenance_executions me
    LEFT JOIN users u ON me.assigned_to = u.id
    WHERE me.plan_id = ?
    ORDER BY me.scheduled_date DESC
    LIMIT 10
", [$planId]);

// Parse checklist
$checklist = [];
if (!empty($plan['checklist'])) {
    $checklist = json_decode($plan['checklist'], true) ?? [];
}

// Calculate next dates
$nextExecutions = $db->fetchAll("
    SELECT scheduled_date, status 
    FROM maintenance_executions 
    WHERE plan_id = ? AND status IN ('planned', 'in_progress')
    ORDER BY scheduled_date ASC
    LIMIT 3
", [$planId]);

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Chi tiết kế hoạch', 'url' => '']
];

$pageActions = '<div class="btn-group">
    <a href="edit.php?id=' . $planId . '" class="btn btn-primary">
        <i class="fas fa-edit"></i> Cập nhật
    </a>
    <a href="../executions/add.php?plan_id=' . $planId . '" class="btn btn-success">
        <i class="fas fa-play"></i> Thực hiện
    </a>
    <button class="btn btn-info" onclick="printPlan()">
        <i class="fas fa-print"></i> In kế hoạch
    </button>
    <div class="btn-group">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="../equipment/view.php?id=' . $plan['equipment_id'] . '">
                <i class="fas fa-cog me-2"></i>Xem thiết bị
            </a></li>
            <li><a class="dropdown-item" href="../executions/?plan_id=' . $planId . '">
                <i class="fas fa-history me-2"></i>Lịch sử thực hiện
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="deletePlan()">
                <i class="fas fa-trash me-2"></i>Xóa kế hoạch
            </a></li>
        </ul>
    </div>
</div>';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Plan Overview -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Thông tin kế hoạch
                </h5>
                <span class="badge badge-<?php echo $plan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo $plan['status'] === 'active' ? 'Đang hoạt động' : 'Không hoạt động'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Mã kế hoạch:</strong> 
                        <span class="text-primary fw-bold"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Loại bảo trì:</strong>
                        <span class="maintenance-type-<?php echo strtolower($plan['maintenance_type']); ?>">
                            <?php 
                            echo $plan['maintenance_type'] === 'PM' ? 'Bảo trì kế hoạch' : 
                                ($plan['maintenance_type'] === 'CLIT' ? 'Hiệu chuẩn' : 'Sự cố'); 
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Tên kế hoạch:</strong><br>
                    <h6 class="text-primary"><?php echo htmlspecialchars($plan['plan_name']); ?></h6>
                </div>
                
                <?php if ($plan['description']): ?>
                <div class="mb-3">
                    <strong>Mô tả:</strong><br>
                    <div class="text-muted"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <strong>Tần suất:</strong><br>
                        <?php echo $plan['frequency_days']; ?> ngày
                        <small class="text-muted d-block">
                            (<?php echo ucfirst($plan['frequency_type']); ?>)
                        </small>
                    </div>
                    <div class="col-md-4">
                        <strong>Thời gian dự kiến:</strong><br>
                        <?php echo $plan['estimated_duration']; ?> phút
                    </div>
                    <div class="col-md-4">
                        <strong>Độ ưu tiên:</strong><br>
                        <span class="badge badge-<?php echo strtolower($plan['priority']) === 'high' ? 'danger' : 
                                                            (strtolower($plan['priority']) === 'critical' ? 'dark' : 
                                                            (strtolower($plan['priority']) === 'medium' ? 'warning' : 'info')); ?>">
                            <?php echo getStatusText($plan['priority']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Equipment Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Thông tin thiết bị
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Mã thiết bị:</strong> 
                        <a href="../equipment/view.php?id=<?php echo $plan['equipment_id']; ?>" class="equipment-code">
                            <?php echo htmlspecialchars($plan['equipment_code']); ?>
                        </a><br>
                        <strong>Tên thiết bị:</strong> <?php echo htmlspecialchars($plan['equipment_name']); ?><br>
                        <strong>Loại máy:</strong> <?php echo htmlspecialchars($plan['machine_type_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Vị trí:</strong><br>
                        <div class="equipment-location">
                            <?php echo htmlspecialchars($plan['industry_name']); ?> › 
                            <?php echo htmlspecialchars($plan['workshop_name']); ?>
                            <?php if ($plan['line_name']): ?>
                                › <?php echo htmlspecialchars($plan['line_name']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($plan['manufacturer']): ?>
                        <strong>Nhà sản xuất:</strong> <?php echo htmlspecialchars($plan['manufacturer']); ?><br>
                        <?php endif; ?>
                        <?php if ($plan['model']): ?>
                        <strong>Model:</strong> <?php echo htmlspecialchars($plan['model']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Schedule Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Lịch trình bảo trì
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Bảo trì cuối cùng:</strong><br>
                        <?php if ($plan['last_maintenance_date']): ?>
                            <?php echo formatDate($plan['last_maintenance_date']); ?>
                        <?php else: ?>
                            <span class="text-muted">Chưa có</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Bảo trì tiếp theo:</strong><br>
                        <?php if ($plan['next_maintenance_date']): ?>
                            <?php 
                            $nextDate = new DateTime($plan['next_maintenance_date']);
                            $today = new DateTime();
                            $diff = $today->diff($nextDate);
                            
                            echo formatDate($plan['next_maintenance_date']);
                            
                            if ($nextDate < $today) {
                                echo ' <span class="text-danger">(Quá hạn ' . $diff->days . ' ngày)</span>';
                            } elseif ($diff->days <= 7) {
                                echo ' <span class="text-warning">(Còn ' . $diff->days . ' ngày)</span>';
                            }
                            ?>
                        <?php else: ?>
                            <span class="text-muted">Chưa định</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($nextExecutions)): ?>
                <div class="mt-3">
                    <strong>Lịch thực hiện sắp tới:</strong>
                    <ul class="list-unstyled mt-2">
                        <?php foreach ($nextExecutions as $exec): ?>
                        <li class="mb-1">
                            <i class="fas fa-clock text-info me-2"></i>
                            <?php echo formatDateTime($exec['scheduled_date']); ?>
                            <span class="badge badge-<?php echo $exec['status'] === 'planned' ? 'info' : 'warning'; ?> ms-2">
                                <?php echo $exec['status'] === 'planned' ? 'Đã lên lịch' : 'Đang thực hiện'; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Checklist -->
        <?php if (!empty($checklist)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>
                    Danh sách kiểm tra
                    <span class="badge badge-secondary ms-2"><?php echo count($checklist); ?> mục</span>
                </h6>
            </div>
            <div class="card-body">
                <div class="checklist-container">
                    <?php foreach ($checklist as $item): ?>
                    <div class="checklist-item <?php echo $item['required'] ? 'required' : ''; ?>">
                        <div class="d-flex align-items-start">
                            <div class="form-check me-3">
                                <input type="checkbox" class="form-check-input" disabled>
                            </div>
                            <div class="flex-grow-1">
                                <div class="checklist-description">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                    <?php if ($item['required']): ?>
                                        <span class="badge badge-warning ms-1">Bắt buộc</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['notes'])): ?>
                                    <div class="checklist-notes">
                                        <?php echo htmlspecialchars($item['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Executions -->
        <?php if (!empty($executions)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Lịch sử thực hiện gần đây
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Mã thực hiện</th>
                                <th>Ngày thực hiện</th>
                                <th>Người thực hiện</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executions as $exec): ?>
                            <tr>
                                <td>
                                    <a href="../executions/view.php?id=<?php echo $exec['id']; ?>">
                                        <?php echo htmlspecialchars($exec['execution_code']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatDateTime($exec['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($exec['assigned_name'] ?? 'Chưa phân công'); ?></td>
                                <td>
                                    <span class="execution-status <?php echo $exec['status']; ?>">
                                        <?php
                                        $statusText = [
                                            'planned' => 'Đã lên kế hoạch',
                                            'in_progress' => 'Đang thực hiện',
                                            'completed' => 'Hoàn thành',
                                            'cancelled' => 'Đã hủy',
                                            'on_hold' => 'Tạm hoãn'
                                        ];
                                        echo $statusText[$exec['status']] ?? $exec['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../executions/view.php?id=<?php echo $exec['id']; ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-3">
                    <a href="../executions/?plan_id=<?php echo $planId; ?>" class="btn btn-outline-primary">
                        Xem tất cả lịch sử thực hiện
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Assignment Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Phân công thực hiện
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Người phụ trách chính:</strong><br>
                    <?php if ($plan['assigned_name']): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user text-primary me-2"></i>
                            <div>
                                <?php echo htmlspecialchars($plan['assigned_name']); ?>
                                <?php if ($plan['assigned_email']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($plan['assigned_email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Chưa phân công</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($plan['backup_name']): ?>
                <div class="mb-3">
                    <strong>Người dự phòng:</strong><br>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-shield text-info me-2"></i>
                        <div>
                            <?php echo htmlspecialchars($plan['backup_name']); ?>
                            <?php if ($plan['backup_email']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($plan['backup_email']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Người tạo:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($plan['created_by_name'] ?? 'N/A'); ?></span>
                    <br><small class="text-muted"><?php echo formatDateTime($plan['created_at']); ?></small>
                </div>
                
                <?php if ($plan['updated_at'] !== $plan['created_at']): ?>
                <div class="mb-3">
                    <strong>Cập nhật cuối:</strong><br>
                    <small class="text-muted"><?php echo formatDateTime($plan['updated_at']); ?></small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Skills and Safety -->
        <?php if ($plan['required_skills'] || $plan['safety_requirements']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>
                    Yêu cầu kỹ năng & An toàn
                </h6>
            </div>
            <div class="card-body">
                <?php if ($plan['required_skills']): ?>
                <div class="mb-3">
                    <strong>Kỹ năng yêu cầu:</strong><br>
                    <div class="text-info"><?php echo htmlspecialchars($plan['required_skills']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($plan['safety_requirements']): ?>
                <div class="mb-3">
                    <strong>Yêu cầu an toàn:</strong><br>
                    <div class="text-warning"><?php echo nl2br(htmlspecialchars($plan['safety_requirements'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
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
                    <a href="../executions/add.php?plan_id=<?php echo $planId; ?>" class="btn btn-success">
                        <i class="fas fa-play me-2"></i>
                        Tạo lệnh thực hiện
                    </a>
                    
                    <?php if (hasPermission('maintenance', 'edit')): ?>
                    <a href="edit.php?id=<?php echo $planId; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>
                        Chỉnh sửa kế hoạch
                    </a>
                    <?php endif; ?>
                    
                    <a href="../executions/?plan_id=<?php echo $planId; ?>" class="btn btn-outline-info">
                        <i class="fas fa-history me-2"></i>
                        Xem lịch sử đầy đủ
                    </a>
                    
                    <button class="btn btn-outline-secondary" onclick="copyPlan()">
                        <i class="fas fa-copy me-2"></i>
                        Sao chép kế hoạch
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printPlan() {
    window.print();
}

function deletePlan() {
    if (confirm('Bạn có chắc chắn muốn xóa kế hoạch bảo trì này?\n\nViệc xóa sẽ không thể hoàn tác.')) {
        // Implement delete functionality
        alert('Chức năng xóa sẽ được triển khai');
    }
}

function copyPlan() {
    if (confirm('Bạn có muốn tạo một kế hoạch mới dựa trên kế hoạch này?')) {
        window.location.href = 'add.php?copy_from=<?php echo $planId; ?>';
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>