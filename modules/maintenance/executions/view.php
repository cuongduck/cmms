<?php
$pageTitle = 'Chi tiết thực hiện bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'view');

$executionId = $_GET['id'] ?? null;

if (!$executionId) {
    header('Location: ../index.php?error=ID không hợp lệ');
    exit;
}

// Get execution details
$sql = "SELECT me.*, e.code as equipment_code, e.name as equipment_name,
               e.serial_number, e.manufacturer, e.model, e.location_details,
               mt.name as machine_type_name, i.name as industry_name, 
               w.name as workshop_name, pl.name as line_name, a.name as area_name,
               mp.plan_code, mp.plan_name, mp.safety_requirements,
               u1.full_name as assigned_name, u1.email as assigned_email,
               u2.full_name as created_by_name
        FROM maintenance_executions me
        JOIN equipment e ON me.equipment_id = e.id
        JOIN machine_types mt ON e.machine_type_id = mt.id
        JOIN industries i ON e.industry_id = i.id
        JOIN workshops w ON e.workshop_id = w.id
        LEFT JOIN production_lines pl ON e.line_id = pl.id
        LEFT JOIN areas a ON e.area_id = a.id
        LEFT JOIN maintenance_plans mp ON me.plan_id = mp.id
        LEFT JOIN users u1 ON me.assigned_to = u1.id
        LEFT JOIN users u2 ON me.created_by = u2.id
        WHERE me.id = ?";

$execution = $db->fetch($sql, [$executionId]);

if (!$execution) {
    header('Location: ../index.php?error=Không tìm thấy lệnh thực hiện');
    exit;
}

// Get execution history
$history = $db->fetchAll("
    SELECT mh.*, u.full_name as performed_by_name
    FROM maintenance_history mh
    LEFT JOIN users u ON mh.performed_by = u.id
    WHERE mh.execution_id = ?
    ORDER BY mh.performed_at DESC
", [$executionId]);

// Get team members
$teamMembers = [];
if (!empty($execution['team_members'])) {
    $memberIds = json_decode($execution['team_members'], true);
    if ($memberIds) {
        $placeholders = str_repeat('?,', count($memberIds) - 1) . '?';
        $teamMembers = $db->fetchAll("
            SELECT id, full_name, email, role 
            FROM users 
            WHERE id IN ($placeholders)
        ", $memberIds);
    }
}

// Parse checklist data
$checklist = [];
if (!empty($execution['checklist_data'])) {
    $checklist = json_decode($execution['checklist_data'], true) ?? [];
}

// Parse parts used
$partsUsed = [];
if (!empty($execution['parts_used'])) {
    $partsUsed = json_decode($execution['parts_used'], true) ?? [];
}

// Parse attachments
$attachments = [];
if (!empty($execution['attachments'])) {
    $attachments = json_decode($execution['attachments'], true) ?? [];
}

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Chi tiết thực hiện', 'url' => '']
];

$pageActions = '<div class="btn-group">
    <a href="edit.php?id=' . $executionId . '" class="btn btn-primary">
        <i class="fas fa-edit"></i> Cập nhật
    </a>
    <button class="btn btn-success" onclick="printExecution()">
        <i class="fas fa-print"></i> In báo cáo
    </button>
    <div class="btn-group">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="../equipment/view.php?id=' . $execution['equipment_id'] . '">
                <i class="fas fa-cog me-2"></i>Xem thiết bị
            </a></li>';

if ($execution['plan_id']) {
    $pageActions .= '<li><a class="dropdown-item" href="../plans/view.php?id=' . $execution['plan_id'] . '">
                <i class="fas fa-clipboard-list me-2"></i>Xem kế hoạch
            </a></li>';
}

$pageActions .= '<li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="deleteExecution()">
                <i class="fas fa-trash me-2"></i>Xóa lệnh
            </a></li>
        </ul>
    </div>
</div>';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Execution Overview -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-wrench me-2"></i>
                    Thông tin thực hiện
                </h5>
                <div class="execution-status <?php echo strtolower($execution['status']); ?>">
                    <?php
                    $statusText = [
                        'planned' => 'Đã lên kế hoạch',
                        'in_progress' => 'Đang thực hiện',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy',
                        'on_hold' => 'Tạm hoãn'
                    ];
                    echo $statusText[$execution['status']] ?? $execution['status'];
                    ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Mã thực hiện:</strong> 
                        <span class="text-primary fw-bold"><?php echo htmlspecialchars($execution['execution_code']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Loại bảo trì:</strong>
                        <span class="maintenance-type-<?php echo strtolower($execution['execution_type']); ?>">
                            <?php 
                            echo $execution['execution_type'] === 'PM' ? 'Kế hoạch' : 
                                ($execution['execution_type'] === 'CLIT' ? 'CLIT' : 'Sự cố'); 
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($execution['plan_id']): ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Mã kế hoạch:</strong> 
                        <a href="../plans/view.php?id=<?php echo $execution['plan_id']; ?>">
                            <?php echo htmlspecialchars($execution['plan_code']); ?>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <strong>Tên kế hoạch:</strong> 
                        <?php echo htmlspecialchars($execution['plan_name']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Tiêu đề công việc:</strong><br>
                    <h6 class="text-primary"><?php echo htmlspecialchars($execution['title']); ?></h6>
                </div>
                
                <?php if ($execution['description']): ?>
                <div class="mb-3">
                    <strong>Mô tả:</strong><br>
                    <div class="text-muted"><?php echo nl2br(htmlspecialchars($execution['description'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <strong>Độ ưu tiên:</strong><br>
                        <span class="badge badge-<?php echo strtolower($execution['priority']) === 'high' ? 'danger' : 
                                                            (strtolower($execution['priority']) === 'critical' ? 'dark' : 
                                                            (strtolower($execution['priority']) === 'medium' ? 'warning' : 'info')); ?>">
                            <?php echo getStatusText($execution['priority']); ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Ngày dự kiến:</strong><br>
                        <?php echo formatDateTime($execution['scheduled_date']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Thời gian dự kiến:</strong><br>
                        <?php echo $execution['estimated_duration']; ?> phút
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
                        <a href="../equipment/view.php?id=<?php echo $execution['equipment_id']; ?>" class="equipment-code">
                            <?php echo htmlspecialchars($execution['equipment_code']); ?>
                        </a><br>
                        <strong>Tên thiết bị:</strong> <?php echo htmlspecialchars($execution['equipment_name']); ?><br>
                        <strong>Loại máy:</strong> <?php echo htmlspecialchars($execution['machine_type_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Vị trí:</strong><br>
                        <div class="equipment-location">
                            <?php echo htmlspecialchars($execution['industry_name']); ?> › 
                            <?php echo htmlspecialchars($execution['workshop_name']); ?>
                            <?php if ($execution['line_name']): ?>
                                › <?php echo htmlspecialchars($execution['line_name']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($execution['manufacturer']): ?>
                        <strong>Nhà sản xuất:</strong> <?php echo htmlspecialchars($execution['manufacturer']); ?><br>
                        <?php endif; ?>
                        <?php if ($execution['model']): ?>
                        <strong>Model:</strong> <?php echo htmlspecialchars($execution['model']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progress and Timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Tiến độ thực hiện
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Phần trăm hoàn thành:</strong><br>
                        <div class="maintenance-progress">
                            <div class="maintenance-progress-bar" 
                                 style="width: <?php echo $execution['completion_percentage']; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo number_format($execution['completion_percentage'], 1); ?>%</small>
                    </div>
                    <div class="col-md-4">
                        <?php if ($execution['started_at']): ?>
                        <strong>Bắt đầu:</strong><br>
                        <?php echo formatDateTime($execution['started_at']); ?>
                        <?php else: ?>
                        <span class="text-muted">Chưa bắt đầu</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <?php if ($execution['completed_at']): ?>
                        <strong>Hoàn thành:</strong><br>
                        <?php echo formatDateTime($execution['completed_at']); ?>
                        <?php if ($execution['actual_duration']): ?>
                            <br><small class="text-success">Thời gian thực: <?php echo $execution['actual_duration']; ?> phút</small>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">Chưa hoàn thành</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if (hasPermission('maintenance', 'edit') && $execution['status'] !== 'completed' && $execution['status'] !== 'cancelled'): ?>
                <div class="btn-group btn-group-sm">
                    <?php if ($execution['status'] === 'planned'): ?>
                    <button class="btn btn-success" onclick="updateStatus('in_progress')">
                        <i class="fas fa-play me-1"></i>Bắt đầu
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($execution['status'] === 'in_progress'): ?>
                    <button class="btn btn-warning" onclick="updateStatus('on_hold')">
                        <i class="fas fa-pause me-1"></i>Tạm hoãn
                    </button>
                    <button class="btn btn-success" onclick="updateStatus('completed')">
                        <i class="fas fa-check me-1"></i>Hoàn thành
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($execution['status'] === 'on_hold'): ?>
                    <button class="btn btn-primary" onclick="updateStatus('in_progress')">
                        <i class="fas fa-play me-1"></i>Tiếp tục
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-danger" onclick="updateStatus('cancelled')">
                        <i class="fas fa-times me-1"></i>Hủy
                    </button>
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
                    <span class="badge badge-secondary ms-2">
                        <?php
                        $completedCount = array_sum(array_column($checklist, 'completed'));
                        echo $completedCount . '/' . count($checklist);
                        ?>
                    </span>
                </h6>
            </div>
            <div class="card-body">
                <div class="checklist-container">
                    <?php foreach ($checklist as $index => $item): ?>
                    <div class="checklist-item <?php echo $item['completed'] ? 'completed' : ''; ?> <?php echo $item['required'] ? 'required' : ''; ?>">
                        <div class="d-flex align-items-start">
                            <div class="form-check me-3">
                                <input type="checkbox" class="form-check-input checklist-checkbox" 
                                       <?php echo $item['completed'] ? 'checked' : ''; ?>
                                       <?php echo hasPermission('maintenance', 'edit') && $execution['status'] !== 'completed' ? '' : 'disabled'; ?>
                                       onchange="updateChecklistItem(<?php echo $index; ?>, this.checked)">
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
                                <?php if (!empty($item['completion_notes'])): ?>
                                    <div class="mt-2">
                                        <small class="text-success">
                                            <i class="fas fa-comment me-1"></i>
                                            <?php echo htmlspecialchars($item['completion_notes']); ?>
                                        </small>
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
        
        <!-- Parts Used -->
        <?php if (!empty($partsUsed)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Phụ tùng sử dụng
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Mã phụ tùng</th>
                                <th>Tên phụ tùng</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partsUsed as $part): ?>
                            <tr>
                                <td class="part-code"><?php echo htmlspecialchars($part['part_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($part['part_name'] ?? ''); ?></td>
                                <td class="part-quantity"><?php echo number_format($part['quantity'] ?? 0, 2); ?></td>
                                <td><?php echo formatCurrency($part['unit_price'] ?? 0); ?></td>
                                <td class="part-cost"><?php echo formatCurrency(($part['quantity'] ?? 0) * ($part['unit_price'] ?? 0)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Team and Assignment -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Phân công thực hiện
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Người thực hiện chính:</strong><br>
                    <?php if ($execution['assigned_name']): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user text-primary me-2"></i>
                            <div>
                                <?php echo htmlspecialchars($execution['assigned_name']); ?>
                                <?php if ($execution['assigned_email']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($execution['assigned_email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Chưa phân công</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($teamMembers)): ?>
                <div class="mb-3">
                    <strong>Thành viên nhóm:</strong><br>
                    <?php foreach ($teamMembers as $member): ?>
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-user-friends text-info me-2"></i>
                            <div>
                                <?php echo htmlspecialchars($member['full_name']); ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($member['role']); ?>)</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Người tạo:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($execution['created_by_name'] ?? 'N/A'); ?></span>
                    <br><small class="text-muted"><?php echo formatDateTime($execution['created_at']); ?></small>
                </div>
            </div>
        </div>
        
        <!-- Safety Requirements -->
        <?php if (!empty($execution['safety_requirements'])): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Yêu cầu an toàn
                </h6>
            </div>
            <div class="card-body">
                <div class="text-muted">
                    <?php echo nl2br(htmlspecialchars($execution['safety_requirements'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Cost Summary -->
        <?php if ($execution['total_cost'] > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>
                    Tổng kết chi phí
                </h6>
            </div>
            <div class="card-body">
                <div class="cost-summary">
                    <div class="cost-item">
                        <span>Chi phí nhân công:</span>
                        <span><?php echo formatCurrency($execution['labor_cost']); ?></span>
                    </div>
                    <div class="cost-item">
                        <span>Chi phí phụ tùng:</span>
                        <span><?php echo formatCurrency($execution['parts_cost']); ?></span>
                    </div>
                    <div class="cost-item">
                        <span><strong>Tổng chi phí:</strong></span>
                        <span><strong><?php echo formatCurrency($execution['total_cost']); ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Execution History -->
        <?php if (!empty($history)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Lịch sử thực hiện
                </h6>
            </div>
            <div class="card-body">
                <div class="maintenance-timeline">
                    <?php foreach ($history as $record): ?>
                    <div class="timeline-item <?php echo $record['action'] === 'completed' ? 'completed' : 
                                                      ($record['action'] === 'status_changed' && $record['status_to'] === 'in_progress' ? 'in-progress' : ''); ?>">
                        <div class="timeline-content">
                            <div class="timeline-time">
                                <?php echo formatDateTime($record['performed_at']); ?>
                            </div>
                            <div class="timeline-title">
                                <?php
                                $actionTexts = [
                                    'created' => 'Tạo lệnh thực hiện',
                                    'started' => 'Bắt đầu thực hiện',
                                    'paused' => 'Tạm hoãn',
                                    'resumed' => 'Tiếp tục thực hiện',
                                    'completed' => 'Hoàn thành',
                                    'cancelled' => 'Hủy bỏ',
                                    'status_changed' => 'Thay đổi trạng thái'
                                ];
                                echo $actionTexts[$record['action']] ?? $record['action'];
                                
                                if ($record['action'] === 'status_changed' && $record['status_from'] && $record['status_to']) {
                                    echo ': ' . getStatusText($record['status_from']) . ' → ' . getStatusText($record['status_to']);
                                }
                                ?>
                            </div>
                            <?php if ($record['comments']): ?>
                            <div class="timeline-description">
                                <?php echo htmlspecialchars($record['comments']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="timeline-description">
                                <small class="text-muted">
                                    bởi <?php echo htmlspecialchars($record['performed_by_name'] ?? 'Hệ thống'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notes and Findings -->
        <?php if ($execution['notes'] || $execution['issues_found'] || $execution['recommendations']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-sticky-note me-2"></i>
                    Ghi chú và khuyến nghị
                </h6>
            </div>
            <div class="card-body">
                <?php if ($execution['notes']): ?>
                <div class="mb-3">
                    <strong>Ghi chú:</strong><br>
                    <div class="text-muted"><?php echo nl2br(htmlspecialchars($execution['notes'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($execution['issues_found']): ?>
                <div class="mb-3">
                    <strong>Vấn đề phát hiện:</strong><br>
                    <div class="text-warning"><?php echo nl2br(htmlspecialchars($execution['issues_found'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($execution['recommendations']): ?>
                <div class="mb-3">
                    <strong>Khuyến nghị:</strong><br>
                    <div class="text-info"><?php echo nl2br(htmlspecialchars($execution['recommendations'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($execution['next_maintenance_recommendation']): ?>
                <div class="mb-3">
                    <strong>Ngày bảo trì tiếp theo đề xuất:</strong><br>
                    <span class="text-primary"><?php echo formatDate($execution['next_maintenance_recommendation']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-paperclip me-2"></i>
                    File đính kèm
                    <span class="badge badge-secondary ms-2"><?php echo count($attachments); ?></span>
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($attachments as $attachment): ?>
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-file text-muted me-2"></i>
                    <a href="<?php echo htmlspecialchars($attachment['url']); ?>" target="_blank" class="text-decoration-none">
                        <?php echo htmlspecialchars($attachment['name']); ?>
                    </a>
                    <small class="text-muted ms-auto">
                        <?php echo formatFileSize($attachment['size'] ?? 0); ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật trạng thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Trạng thái mới:</label>
                    <div id="statusText" class="fw-bold"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú (tùy chọn):</label>
                    <textarea id="statusComments" class="form-control" rows="3" 
                              placeholder="Nhập ghi chú về việc thay đổi trạng thái..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">Cập nhật</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingStatus = null;

function updateStatus(newStatus) {
    pendingStatus = newStatus;
    
    const statusTexts = {
        'in_progress': 'Đang thực hiện',
        'completed': 'Hoàn thành',
        'cancelled': 'Đã hủy',
        'on_hold': 'Tạm hoãn'
    };
    
    document.getElementById('statusText').textContent = statusTexts[newStatus] || newStatus;
    document.getElementById('statusComments').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

function confirmStatusUpdate() {
    if (!pendingStatus) return;
    
    const comments = document.getElementById('statusComments').value;
    
    fetch('api/maintenance.php?action=update_status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            execution_id: <?php echo $executionId; ?>,
            status: pendingStatus,
            comments: comments
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Lỗi: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi cập nhật trạng thái');
    });
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
    modal.hide();
}

function updateChecklistItem(index, completed) {
    // Get current checklist data
    const checklist = <?php echo json_encode($checklist, JSON_UNESCAPED_UNICODE); ?>;
    
    // Update the specific item
    checklist[index].completed = completed;
    
    // If completed and can add notes
    if (completed && <?php echo hasPermission('maintenance', 'edit') ? 'true' : 'false'; ?>) {
        const notes = prompt('Ghi chú hoàn thành (tùy chọn):');
        if (notes !== null) {
            checklist[index].completion_notes = notes;
        }
    }
    
    // Send update to server
    fetch('api/maintenance.php?action=checklist', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            execution_id: <?php echo $executionId; ?>,
            checklist: checklist
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update progress bar
            const progressBar = document.querySelector('.maintenance-progress-bar');
            if (progressBar) {
                progressBar.style.width = data.completion_percentage + '%';
                progressBar.parentElement.nextElementSibling.textContent = data.completion_percentage.toFixed(1) + '%';
            }
            
            // Refresh page to show updated checklist
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Lỗi: ' + data.message);
            // Revert checkbox state
            event.target.checked = !completed;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi cập nhật checklist');
        // Revert checkbox state
        event.target.checked = !completed;
    });
}

function printExecution() {
    window.print();
}

function deleteExecution() {
    if (confirm('Bạn có chắc chắn muốn xóa lệnh thực hiện này?')) {
        fetch('api/maintenance.php?action=executions', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                execution_id: <?php echo $executionId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Xóa thành công');
                window.location.href = '../index.php';
            } else {
                alert('Lỗi: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa lệnh thực hiện');
        });
    }
}

// Auto refresh every 5 minutes for active executions
<?php if ($execution['status'] === 'in_progress'): ?>
setInterval(function() {
    location.reload();
}, 300000); // 5 minutes
<?php endif; ?>
</script>

<?php require_once '../../../includes/footer.php'; ?>