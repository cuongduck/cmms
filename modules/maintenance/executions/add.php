<?php
$pageTitle = 'Thực hiện bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'create');

// Get parameters
$planId = $_GET['plan_id'] ?? null;
$equipmentId = $_GET['equipment_id'] ?? null;

$selectedPlan = null;
$selectedEquipment = null;

// If plan_id provided, get plan details
if ($planId) {
    $sql = "SELECT mp.*, e.code as equipment_code, e.name as equipment_name,
                   mt.name as machine_type_name, i.name as industry_name, 
                   w.name as workshop_name, pl.name as line_name, a.name as area_name,
                   u1.full_name as assigned_name, u2.full_name as backup_name
            FROM maintenance_plans mp
            JOIN equipment e ON mp.equipment_id = e.id
            JOIN machine_types mt ON e.machine_type_id = mt.id
            JOIN industries i ON e.industry_id = i.id
            JOIN workshops w ON e.workshop_id = w.id
            LEFT JOIN production_lines pl ON e.line_id = pl.id
            LEFT JOIN areas a ON e.area_id = a.id
            LEFT JOIN users u1 ON mp.assigned_to = u1.id
            LEFT JOIN users u2 ON mp.backup_assigned_to = u2.id
            WHERE mp.id = ? AND mp.status = 'active'";
    
    $selectedPlan = $db->fetch($sql, [$planId]);
    
    if (!$selectedPlan) {
        header('Location: ../index.php?error=Kế hoạch bảo trì không tồn tại');
        exit;
    }
    
    $equipmentId = $selectedPlan['equipment_id'];
}

// If equipment_id provided (for breakdown maintenance), get equipment details
if ($equipmentId && !$selectedPlan) {
    $sql = "SELECT e.*, mt.name as machine_type_name, i.name as industry_name, 
                   w.name as workshop_name, pl.name as line_name, a.name as area_name
            FROM equipment e
            JOIN machine_types mt ON e.machine_type_id = mt.id
            JOIN industries i ON e.industry_id = i.id
            JOIN workshops w ON e.workshop_id = w.id
            LEFT JOIN production_lines pl ON e.line_id = pl.id
            LEFT JOIN areas a ON e.area_id = a.id
            WHERE e.id = ?";
    
    $selectedEquipment = $db->fetch($sql, [$equipmentId]);
    
    if (!$selectedEquipment) {
        header('Location: ../index.php?error=Thiết bị không tồn tại');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'plan_id' => $_POST['plan_id'] ?: null,
        'equipment_id' => $_POST['equipment_id'],
        'execution_type' => $_POST['execution_type'],
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'scheduled_date' => $_POST['scheduled_date'],
        'estimated_duration' => $_POST['estimated_duration'],
        'assigned_to' => $_POST['assigned_to'] ?: null,
        'priority' => $_POST['priority'],
        'notes' => $_POST['notes']
    ];
    
    // Validation
    $errors = [];
    
    if (empty($data['equipment_id'])) {
        $errors[] = 'Vui lòng chọn thiết bị';
    }
    
    if (empty($data['execution_type'])) {
        $errors[] = 'Vui lòng chọn loại thực hiện';
    }
    
    if (empty($data['title'])) {
        $errors[] = 'Vui lòng nhập tiêu đề công việc';
    }
    
    if (empty($data['scheduled_date'])) {
        $errors[] = 'Vui lòng chọn ngày thực hiện';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate execution code
            $prefix = strtoupper($data['execution_type']) . 'E';
            $sql = "SELECT MAX(CAST(SUBSTRING(execution_code, 4) AS UNSIGNED)) as max_seq 
                    FROM maintenance_executions 
                    WHERE execution_code LIKE ?";
            $result = $db->fetch($sql, [$prefix . '%']);
            $sequence = ($result['max_seq'] ?? 0) + 1;
            $executionCode = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            
            // Process team members
            $teamMembers = [];
            if (!empty($_POST['team_members'])) {
                foreach ($_POST['team_members'] as $memberId) {
                    if (!empty($memberId)) {
                        $teamMembers[] = $memberId;
                    }
                }
            }
            
            // Insert maintenance execution
            $sql = "INSERT INTO maintenance_executions (
                plan_id, equipment_id, execution_code, execution_type, title, description,
                scheduled_date, estimated_duration, assigned_to, team_members, priority, 
                notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['plan_id'],
                $data['equipment_id'],
                $executionCode,
                $data['execution_type'],
                $data['title'],
                $data['description'],
                $data['scheduled_date'],
                $data['estimated_duration'],
                $data['assigned_to'],
                !empty($teamMembers) ? json_encode($teamMembers) : null,
                $data['priority'],
                $data['notes'],
                getCurrentUser()['id']
            ];
            
            $db->execute($sql, $params);
            $executionId = $db->lastInsertId();
            
            // Process checklist if from plan
            if ($selectedPlan && !empty($selectedPlan['checklist'])) {
                $checklist = json_decode($selectedPlan['checklist'], true);
                if ($checklist) {
                    $checklistData = [];
                    foreach ($checklist as $item) {
                        $checklistData[] = [
                            'id' => $item['id'],
                            'description' => $item['description'],
                            'required' => $item['required'] ?? false,
                            'notes' => $item['notes'] ?? '',
                            'completed' => false,
                            'completion_notes' => ''
                        ];
                    }
                    
                    $sql = "UPDATE maintenance_executions SET checklist_data = ? WHERE id = ?";
                    $db->execute($sql, [json_encode($checklistData, JSON_UNESCAPED_UNICODE), $executionId]);
                }
            }
            
            // Create history record
            $sql = "INSERT INTO maintenance_history (execution_id, action, comments, performed_by) 
                    VALUES (?, 'created', ?, ?)";
            $db->execute($sql, [$executionId, 'Tạo lệnh thực hiện bảo trì', getCurrentUser()['id']]);
            
            $db->commit();
            
            $_SESSION['success'] = 'Tạo lệnh thực hiện bảo trì thành công';
            header('Location: view.php?id=' . $executionId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

// Get data for selects
$users = $db->fetchAll("
    SELECT id, full_name, role 
    FROM users 
    WHERE status = 'active' 
    ORDER BY full_name
");

$equipment = $db->fetchAll("
    SELECT e.*, mt.name as machine_type_name, i.name as industry_name, 
           w.name as workshop_name, pl.name as line_name, a.name as area_name
    FROM equipment e
    JOIN machine_types mt ON e.machine_type_id = mt.id
    JOIN industries i ON e.industry_id = i.id
    JOIN workshops w ON e.workshop_id = w.id
    LEFT JOIN production_lines pl ON e.line_id = pl.id
    LEFT JOIN areas a ON e.area_id = a.id
    WHERE e.status = 'active'
    ORDER BY e.code
");

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Thực hiện bảo trì', 'url' => '']
];
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <!-- Plan/Equipment Info -->
            <?php if ($selectedPlan): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Thông tin kế hoạch bảo trì
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Mã kế hoạch:</strong> <?php echo htmlspecialchars($selectedPlan['plan_code']); ?><br>
                            <strong>Tên kế hoạch:</strong> <?php echo htmlspecialchars($selectedPlan['plan_name']); ?><br>
                            <strong>Thiết bị:</strong> <?php echo htmlspecialchars($selectedPlan['equipment_code'] . ' - ' . $selectedPlan['equipment_name']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Loại bảo trì:</strong> 
                            <span class="badge badge-<?php echo $selectedPlan['maintenance_type'] === 'PM' ? 'info' : 'warning'; ?>">
                                <?php echo $selectedPlan['maintenance_type'] === 'PM' ? 'Kế hoạch' : 'CLIT'; ?>
                            </span><br>
                            <strong>Tần suất:</strong> <?php echo $selectedPlan['frequency_days']; ?> ngày<br>
                            <strong>Người phụ trách:</strong> <?php echo htmlspecialchars($selectedPlan['assigned_name'] ?? 'Chưa phân công'); ?>
                        </div>
                    </div>
                    
                    <?php if ($selectedPlan['description']): ?>
                    <div class="mt-3">
                        <strong>Mô tả:</strong><br>
                        <div class="text-muted"><?php echo nl2br(htmlspecialchars($selectedPlan['description'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <input type="hidden" name="plan_id" value="<?php echo $selectedPlan['id']; ?>">
            <input type="hidden" name="equipment_id" value="<?php echo $selectedPlan['equipment_id']; ?>">
            <input type="hidden" name="execution_type" value="<?php echo $selectedPlan['maintenance_type']; ?>">
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-wrench me-2"></i>
                        Thông tin thực hiện
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!$selectedPlan): ?>
                    <!-- Equipment Selection for breakdown maintenance -->
                    <div class="mb-3">
                        <label class="form-label">Thiết bị <span class="text-danger">*</span></label>
                        <?php if ($selectedEquipment): ?>
                            <div class="form-control bg-light">
                                <strong><?php echo htmlspecialchars($selectedEquipment['code']); ?></strong> - 
                                <?php echo htmlspecialchars($selectedEquipment['name']); ?>
                                <br>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($selectedEquipment['industry_name'] . ' › ' . 
                                                                $selectedEquipment['workshop_name']); ?>
                                    <?php if ($selectedEquipment['line_name']): ?>
                                        › <?php echo htmlspecialchars($selectedEquipment['line_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <input type="hidden" name="equipment_id" value="<?php echo $selectedEquipment['id']; ?>">
                        <?php else: ?>
                            <select name="equipment_id" class="form-select" required>
                                <option value="">Chọn thiết bị...</option>
                                <?php foreach ($equipment as $eq): ?>
                                    <option value="<?php echo $eq['id']; ?>">
                                        <?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?>
                                        (<?php echo htmlspecialchars($eq['industry_name'] . ' › ' . $eq['workshop_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Loại thực hiện <span class="text-danger">*</span></label>
                        <select name="execution_type" class="form-select" required>
                            <option value="">Chọn loại...</option>
                            <option value="BREAKDOWN">Bảo trì sự cố</option>
                            <option value="PM">Bảo trì kế hoạch</option>
                            <option value="CLIT">Hiệu chuẩn</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề công việc <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? 
                                                                 ($selectedPlan['plan_name'] ?? '')); ?>" 
                               placeholder="Ví dụ: Thay dầu máy nén khí" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Mô tả chi tiết các công việc cần thực hiện..."><?php echo htmlspecialchars($_POST['description'] ?? ($selectedPlan['description'] ?? '')); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ngày thực hiện dự kiến <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_date" class="form-control" 
                                       value="<?php echo $_POST['scheduled_date'] ?? date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Thời gian dự kiến (phút)</label>
                                <input type="number" name="estimated_duration" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? 
                                                                         ($selectedPlan['estimated_duration'] ?? '60')); ?>" 
                                       min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú thêm</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Ghi chú thêm về công việc..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Checklist Preview -->
            <?php if ($selectedPlan && !empty($selectedPlan['checklist'])): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Danh sách kiểm tra
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    $checklist = json_decode($selectedPlan['checklist'], true);
                    if ($checklist):
                    ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Danh sách kiểm tra sẽ được tự động tải từ kế hoạch. Bạn có thể cập nhật trong quá trình thực hiện.
                    </div>
                    
                    <div class="list-group">
                        <?php foreach ($checklist as $item): ?>
                        <div class="list-group-item">
                            <div class="d-flex align-items-start">
                                <div class="form-check me-3">
                                    <input type="checkbox" class="form-check-input" disabled>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                        <?php if ($item['required'] ?? false): ?>
                                            <span class="badge badge-danger ms-1">Bắt buộc</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($item['notes'])): ?>
                                        <div class="text-muted small mt-1">
                                            <?php echo htmlspecialchars($item['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Phân công thực hiện
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Người thực hiện chính</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Chọn người thực hiện...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($_POST['assigned_to'] ?? ($selectedPlan['assigned_to'] ?? '')) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thành viên nhóm</label>
                        <select name="team_members[]" class="form-select" multiple size="4">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Giữ Ctrl để chọn nhiều người</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="Low" <?php echo ($_POST['priority'] ?? ($selectedPlan['priority'] ?? 'Medium')) === 'Low' ? 'selected' : ''; ?>>
                                Thấp
                            </option>
                            <option value="Medium" <?php echo ($_POST['priority'] ?? ($selectedPlan['priority'] ?? 'Medium')) === 'Medium' ? 'selected' : ''; ?>>
                                Trung bình
                            </option>
                            <option value="High" <?php echo ($_POST['priority'] ?? ($selectedPlan['priority'] ?? 'Medium')) === 'High' ? 'selected' : ''; ?>>
                                Cao
                            </option>
                            <option value="Critical" <?php echo ($_POST['priority'] ?? ($selectedPlan['priority'] ?? 'Medium')) === 'Critical' ? 'selected' : ''; ?>>
                                Nghiêm trọng
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($selectedPlan && !empty($selectedPlan['safety_requirements'])): ?>
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Yêu cầu an toàn
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-muted">
                        <?php echo nl2br(htmlspecialchars($selectedPlan['safety_requirements'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i>
                            Tạo lệnh thực hiện
                        </button>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Hủy bỏ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once '../../../includes/footer.php'; ?>