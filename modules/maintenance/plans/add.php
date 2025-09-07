<?php
$pageTitle = 'Thêm kế hoạch bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'create');

// Get equipment_id from URL if provided
$equipmentId = $_GET['equipment_id'] ?? null;
$selectedEquipment = null;

if ($equipmentId) {
    $sql = "SELECT e.*, mt.name as machine_type_name, i.name as industry_name, 
                   w.name as workshop_name, pl.name as line_name, a.name as area_name
            FROM equipment e
            JOIN machine_types mt ON e.machine_type_id = mt.id
            JOIN industries i ON e.industry_id = i.id
            JOIN workshops w ON e.workshop_id = w.id
            LEFT JOIN production_lines pl ON e.line_id = pl.id
            LEFT JOIN areas a ON e.area_id = a.id
            WHERE e.id = ? AND e.maintenance_frequency_days > 0";
    
    $selectedEquipment = $db->fetch($sql, [$equipmentId]);
    
    if (!$selectedEquipment) {
        header('Location: ../index.php?error=Thiết bị không tồn tại hoặc không cần bảo trì');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'equipment_id' => $_POST['equipment_id'],
        'plan_name' => $_POST['plan_name'],
        'maintenance_type' => $_POST['maintenance_type'],
        'frequency_days' => $_POST['frequency_days'],
        'frequency_type' => $_POST['frequency_type'],
        'estimated_duration' => $_POST['estimated_duration'],
        'description' => $_POST['description'],
        'assigned_to' => $_POST['assigned_to'] ?: null,
        'backup_assigned_to' => $_POST['backup_assigned_to'] ?: null,
        'priority' => $_POST['priority'],
        'safety_requirements' => $_POST['safety_requirements'],
        'required_skills' => $_POST['required_skills']
    ];
    
    // Validation
    $errors = [];
    
    if (empty($data['equipment_id'])) {
        $errors[] = 'Vui lòng chọn thiết bị';
    }
    
    if (empty($data['plan_name'])) {
        $errors[] = 'Vui lòng nhập tên kế hoạch';
    }
    
    if (empty($data['maintenance_type'])) {
        $errors[] = 'Vui lòng chọn loại bảo trì';
    }
    
    if (empty($data['frequency_days']) || $data['frequency_days'] <= 0) {
        $errors[] = 'Tần suất bảo trì phải lớn hơn 0';
    }
    
    // Check if equipment already has active plan of this type
    if (!empty($data['equipment_id']) && !empty($data['maintenance_type'])) {
        $existingPlan = $db->fetch(
            "SELECT id FROM maintenance_plans 
             WHERE equipment_id = ? AND maintenance_type = ? AND status = 'active'",
            [$data['equipment_id'], $data['maintenance_type']]
        );
        
        if ($existingPlan) {
            $errors[] = 'Thiết bị đã có kế hoạch bảo trì loại này';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate plan code
            $prefix = strtoupper($data['maintenance_type']);
            $sql = "SELECT MAX(CAST(SUBSTRING(plan_code, 4) AS UNSIGNED)) as max_seq 
                    FROM maintenance_plans 
                    WHERE plan_code LIKE ?";
            $result = $db->fetch($sql, [$prefix . '%']);
            $sequence = ($result['max_seq'] ?? 0) + 1;
            $planCode = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            
            // Calculate next maintenance date
            $nextDate = new DateTime();
            $nextDate->add(new DateInterval('P' . $data['frequency_days'] . 'D'));
            
            // Insert maintenance plan
            $sql = "INSERT INTO maintenance_plans (
                equipment_id, plan_code, plan_name, maintenance_type, 
                frequency_days, frequency_type, next_maintenance_date,
                estimated_duration, description, assigned_to, backup_assigned_to,
                priority, safety_requirements, required_skills, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['equipment_id'],
                $planCode,
                $data['plan_name'],
                $data['maintenance_type'],
                $data['frequency_days'],
                $data['frequency_type'],
                $nextDate->format('Y-m-d'),
                $data['estimated_duration'],
                $data['description'],
                $data['assigned_to'],
                $data['backup_assigned_to'],
                $data['priority'],
                $data['safety_requirements'],
                $data['required_skills'],
                getCurrentUser()['id']
            ];
            
            $db->execute($sql, $params);
            $planId = $db->lastInsertId();
            
            // Process checklist if provided
            if (!empty($_POST['checklist_items'])) {
                $checklistItems = [];
                foreach ($_POST['checklist_items'] as $index => $item) {
                    if (!empty($item['description'])) {
                        $checklistItems[] = [
                            'id' => $index + 1,
                            'description' => $item['description'],
                            'required' => !empty($item['required']),
                            'notes' => $item['notes'] ?? ''
                        ];
                    }
                }
                
                if (!empty($checklistItems)) {
                    $sql = "UPDATE maintenance_plans SET checklist = ? WHERE id = ?";
                    $db->execute($sql, [json_encode($checklistItems, JSON_UNESCAPED_UNICODE), $planId]);
                }
            }
            
            $db->commit();
            
            $_SESSION['success'] = 'Tạo kế hoạch bảo trì thành công';
            header('Location: view.php?id=' . $planId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

// Get data for selects
$equipment = $db->fetchAll("
    SELECT e.*, mt.name as machine_type_name, i.name as industry_name, 
           w.name as workshop_name, pl.name as line_name, a.name as area_name
    FROM equipment e
    JOIN machine_types mt ON e.machine_type_id = mt.id
    JOIN industries i ON e.industry_id = i.id
    JOIN workshops w ON e.workshop_id = w.id
    LEFT JOIN production_lines pl ON e.line_id = pl.id
    LEFT JOIN areas a ON e.area_id = a.id
    WHERE e.maintenance_frequency_days > 0 AND e.status = 'active'
    ORDER BY e.code
");

$users = $db->fetchAll("
    SELECT id, full_name, role 
    FROM users 
    WHERE status = 'active' 
    ORDER BY full_name
");

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Thêm kế hoạch bảo trì', 'url' => '']
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-plus me-2"></i>
                        Thông tin kế hoạch bảo trì
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Equipment Selection -->
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
                                    <option value="<?php echo $eq['id']; ?>" 
                                            data-frequency="<?php echo $eq['maintenance_frequency_days']; ?>">
                                        <?php echo htmlspecialchars($eq['code'] . ' - ' . $eq['name']); ?>
                                        (<?php echo htmlspecialchars($eq['industry_name'] . ' › ' . $eq['workshop_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Tên kế hoạch <span class="text-danger">*</span></label>
                                <input type="text" name="plan_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['plan_name'] ?? ''); ?>" 
                                       placeholder="Ví dụ: Bảo trì định kỳ máy đóng gói" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Loại bảo trì <span class="text-danger">*</span></label>
                                <select name="maintenance_type" class="form-select" required>
                                    <option value="">Chọn loại...</option>
                                    <option value="PM" <?php echo ($_POST['maintenance_type'] ?? '') === 'PM' ? 'selected' : ''; ?>>
                                        Bảo trì kế hoạch (PM)
                                    </option>
                                    <option value="CLIT" <?php echo ($_POST['maintenance_type'] ?? '') === 'CLIT' ? 'selected' : ''; ?>>
                                        Hiệu chuẩn (CLIT)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tần suất (ngày) <span class="text-danger">*</span></label>
                                <input type="number" name="frequency_days" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['frequency_days'] ?? 
                                                                         ($selectedEquipment['maintenance_frequency_days'] ?? '')); ?>" 
                                       min="1" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Loại tần suất</label>
                                <select name="frequency_type" class="form-select">
                                    <option value="custom">Tùy chỉnh</option>
                                    <option value="daily">Hàng ngày</option>
                                    <option value="weekly">Hàng tuần</option>
                                    <option value="monthly">Hàng tháng</option>
                                    <option value="quarterly">Hàng quý</option>
                                    <option value="yearly">Hàng năm</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Thời gian dự kiến (phút)</label>
                                <input type="number" name="estimated_duration" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? '60'); ?>" 
                                       min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả công việc</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Mô tả chi tiết các công việc cần thực hiện..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yêu cầu an toàn</label>
                        <textarea name="safety_requirements" class="form-control" rows="3" 
                                  placeholder="Các yêu cầu về an toàn lao động khi thực hiện..."><?php echo htmlspecialchars($_POST['safety_requirements'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kỹ năng yêu cầu</label>
                        <input type="text" name="required_skills" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['required_skills'] ?? ''); ?>" 
                               placeholder="Ví dụ: Điện công nghiệp, An toàn lao động...">
                    </div>
                </div>
            </div>
            
            <!-- Checklist -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Danh sách kiểm tra
                    </h6>
                </div>
                <div class="card-body">
                    <div id="checklist-container">
                        <div class="checklist-item border rounded p-3 mb-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" name="checklist_items[0][description]" 
                                           class="form-control" placeholder="Mô tả công việc kiểm tra...">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="checklist_items[0][required]" 
                                               class="form-check-input" value="1">
                                        <label class="form-check-label">Bắt buộc</label>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-checklist">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <textarea name="checklist_items[0][notes]" class="form-control" 
                                              rows="2" placeholder="Ghi chú thêm..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary" id="add-checklist-item">
                        <i class="fas fa-plus me-1"></i>
                        Thêm mục kiểm tra
                    </button>
                </div>
            </div>
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
                        <label class="form-label">Người phụ trách chính</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Chọn người phụ trách...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($_POST['assigned_to'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Người dự phòng</label>
                        <select name="backup_assigned_to" class="form-select">
                            <option value="">Chọn người dự phòng...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($_POST['backup_assigned_to'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="Low" <?php echo ($_POST['priority'] ?? 'Medium') === 'Low' ? 'selected' : ''; ?>>
                                Thấp
                            </option>
                            <option value="Medium" <?php echo ($_POST['priority'] ?? 'Medium') === 'Medium' ? 'selected' : ''; ?>>
                                Trung bình
                            </option>
                            <option value="High" <?php echo ($_POST['priority'] ?? 'Medium') === 'High' ? 'selected' : ''; ?>>
                                Cao
                            </option>
                            <option value="Critical" <?php echo ($_POST['priority'] ?? 'Medium') === 'Critical' ? 'selected' : ''; ?>>
                                Nghiêm trọng
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Tạo kế hoạch
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

<script>
let checklistCounter = 1;

document.getElementById('add-checklist-item').addEventListener('click', function() {
    const container = document.getElementById('checklist-container');
    const newItem = document.createElement('div');
    newItem.className = 'checklist-item border rounded p-3 mb-3';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <input type="text" name="checklist_items[${checklistCounter}][description]" 
                       class="form-control" placeholder="Mô tả công việc kiểm tra...">
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" name="checklist_items[${checklistCounter}][required]" 
                           class="form-check-input" value="1">
                    <label class="form-check-label">Bắt buộc</label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm remove-checklist">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <textarea name="checklist_items[${checklistCounter}][notes]" class="form-control" 
                          rows="2" placeholder="Ghi chú thêm..."></textarea>
            </div>
        </div>
    `;
    
    container.appendChild(newItem);
    checklistCounter++;
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-checklist')) {
        e.target.closest('.checklist-item').remove();
    }
});

// Auto-fill frequency from selected equipment
document.querySelector('select[name="equipment_id"]')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const frequency = selectedOption.getAttribute('data-frequency');
    if (frequency) {
        document.querySelector('input[name="frequency_days"]').value = frequency;
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>