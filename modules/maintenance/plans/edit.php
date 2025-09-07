<?php
$pageTitle = 'Chỉnh sửa kế hoạch bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'edit');

$planId = $_GET['id'] ?? null;

if (!$planId) {
    header('Location: ../index.php?error=ID không hợp lệ');
    exit;
}

// Get plan details
$sql = "SELECT mp.*, e.code as equipment_code, e.name as equipment_name,
               mt.name as machine_type_name, i.name as industry_name, 
               w.name as workshop_name, pl.name as line_name, a.name as area_name
        FROM maintenance_plans mp
        JOIN equipment e ON mp.equipment_id = e.id
        JOIN machine_types mt ON e.machine_type_id = mt.id
        JOIN industries i ON e.industry_id = i.id
        JOIN workshops w ON e.workshop_id = w.id
        LEFT JOIN production_lines pl ON e.line_id = pl.id
        LEFT JOIN areas a ON e.area_id = a.id
        WHERE mp.id = ?";

$plan = $db->fetch($sql, [$planId]);

if (!$plan) {
    header('Location: ../index.php?error=Không tìm thấy kế hoạch');
    exit;
}

// Parse checklist
$checklist = [];
if (!empty($plan['checklist'])) {
    $checklist = json_decode($plan['checklist'], true) ?? [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
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
        'required_skills' => $_POST['required_skills'],
        'status' => $_POST['status']
    ];
    
    // Validation
    $errors = [];
    
    if (empty($data['plan_name'])) {
        $errors[] = 'Vui lòng nhập tên kế hoạch';
    }
    
    if (empty($data['maintenance_type'])) {
        $errors[] = 'Vui lòng chọn loại bảo trì';
    }
    
    if (empty($data['frequency_days']) || $data['frequency_days'] <= 0) {
        $errors[] = 'Tần suất bảo trì phải lớn hơn 0';
    }
    
    // Check if changing maintenance type conflicts with existing plans
    if ($data['maintenance_type'] !== $plan['maintenance_type']) {
        $existingPlan = $db->fetch(
            "SELECT id FROM maintenance_plans 
             WHERE equipment_id = ? AND maintenance_type = ? AND status = 'active' AND id != ?",
            [$plan['equipment_id'], $data['maintenance_type'], $planId]
        );
        
        if ($existingPlan) {
            $errors[] = 'Thiết bị đã có kế hoạch bảo trì loại này';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update maintenance plan
            $sql = "UPDATE maintenance_plans SET 
                plan_name = ?, maintenance_type = ?, frequency_days = ?, frequency_type = ?,
                estimated_duration = ?, description = ?, assigned_to = ?, backup_assigned_to = ?,
                priority = ?, safety_requirements = ?, required_skills = ?, status = ?,
                updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['plan_name'],
                $data['maintenance_type'],
                $data['frequency_days'],
                $data['frequency_type'],
                $data['estimated_duration'],
                $data['description'],
                $data['assigned_to'],
                $data['backup_assigned_to'],
                $data['priority'],
                $data['safety_requirements'],
                $data['required_skills'],
                $data['status'],
                $planId
            ];
            
            $db->execute($sql, $params);
            
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
                
                $sql = "UPDATE maintenance_plans SET next_maintenance_date = ? WHERE id = ?";
                $db->execute($sql, [$nextDate->format('Y-m-d'), $planId]);
            }
            
            $db->commit();
            
            $_SESSION['success'] = 'Cập nhật kế hoạch bảo trì thành công';
            header('Location: view.php?id=' . $planId);
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

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Chỉnh sửa kế hoạch', 'url' => '']
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
                        <i class="fas fa-edit me-2"></i>
                        Chỉnh sửa kế hoạch bảo trì
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Equipment Info (Read-only) -->
                    <div class="mb-3">
                        <label class="form-label">Thiết bị</label>
                        <div class="form-control bg-light">
                            <strong><?php echo htmlspecialchars($plan['equipment_code']); ?></strong> - 
                            <?php echo htmlspecialchars($plan['equipment_name']); ?>
                            <br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($plan['industry_name'] . ' › ' . 
                                                            $plan['workshop_name']); ?>
                                <?php if ($plan['line_name']): ?>
                                    › <?php echo htmlspecialchars($plan['line_name']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Tên kế hoạch <span class="text-danger">*</span></label>
                                <input type="text" name="plan_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['plan_name'] ?? $plan['plan_name']); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Loại bảo trì <span class="text-danger">*</span></label>
                                <select name="maintenance_type" class="form-select" required>
                                    <option value="">Chọn loại...</option>
                                    <option value="PM" <?php echo ($_POST['maintenance_type'] ?? $plan['maintenance_type']) === 'PM' ? 'selected' : ''; ?>>
                                        Bảo trì kế hoạch (PM)
                                    </option>
                                    <option value="CLIT" <?php echo ($_POST['maintenance_type'] ?? $plan['maintenance_type']) === 'CLIT' ? 'selected' : ''; ?>>
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
                                       value="<?php echo htmlspecialchars($_POST['frequency_days'] ?? $plan['frequency_days']); ?>" 
                                       min="1" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Loại tần suất</label>
                                <select name="frequency_type" class="form-select">
                                    <option value="custom" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'custom' ? 'selected' : ''; ?>>Tùy chỉnh</option>
                                    <option value="daily" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'daily' ? 'selected' : ''; ?>>Hàng ngày</option>
                                    <option value="weekly" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'weekly' ? 'selected' : ''; ?>>Hàng tuần</option>
                                    <option value="monthly" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'monthly' ? 'selected' : ''; ?>>Hàng tháng</option>
                                    <option value="quarterly" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'quarterly' ? 'selected' : ''; ?>>Hàng quý</option>
                                    <option value="yearly" <?php echo ($_POST['frequency_type'] ?? $plan['frequency_type']) === 'yearly' ? 'selected' : ''; ?>>Hàng năm</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Thời gian dự kiến (phút)</label>
                                <input type="number" name="estimated_duration" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? $plan['estimated_duration']); ?>" 
                                       min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả công việc</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Mô tả chi tiết các công việc cần thực hiện..."><?php echo htmlspecialchars($_POST['description'] ?? $plan['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yêu cầu an toàn</label>
                        <textarea name="safety_requirements" class="form-control" rows="3" 
                                  placeholder="Các yêu cầu về an toàn lao động khi thực hiện..."><?php echo htmlspecialchars($_POST['safety_requirements'] ?? $plan['safety_requirements']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kỹ năng yêu cầu</label>
                        <input type="text" name="required_skills" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['required_skills'] ?? $plan['required_skills']); ?>" 
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
                        <?php if (!empty($checklist)): ?>
                            <?php foreach ($checklist as $index => $item): ?>
                            <div class="checklist-item border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="text" name="checklist_items[<?php echo $index; ?>][description]" 
                                               class="form-control" placeholder="Mô tả công việc kiểm tra..."
                                               value="<?php echo htmlspecialchars($item['description']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="checklist_items[<?php echo $index; ?>][required]" 
                                                   class="form-check-input" value="1" 
                                                   <?php echo !empty($item['required']) ? 'checked' : ''; ?>>
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
                                        <textarea name="checklist_items[<?php echo $index; ?>][notes]" class="form-control" 
                                                  rows="2" placeholder="Ghi chú thêm..."><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
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
                        <?php endif; ?>
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
                                        <?php echo ($_POST['assigned_to'] ?? $plan['assigned_to']) == $user['id'] ? 'selected' : ''; ?>>
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
                                        <?php echo ($_POST['backup_assigned_to'] ?? $plan['backup_assigned_to']) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="Low" <?php echo ($_POST['priority'] ?? $plan['priority']) === 'Low' ? 'selected' : ''; ?>>
                                Thấp
                            </option>
                            <option value="Medium" <?php echo ($_POST['priority'] ?? $plan['priority']) === 'Medium' ? 'selected' : ''; ?>>
                                Trung bình
                            </option>
                            <option value="High" <?php echo ($_POST['priority'] ?? $plan['priority']) === 'High' ? 'selected' : ''; ?>>
                                Cao
                            </option>
                            <option value="Critical" <?php echo ($_POST['priority'] ?? $plan['priority']) === 'Critical' ? 'selected' : ''; ?>>
                                Nghiêm trọng
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo ($_POST['status'] ?? $plan['status']) === 'active' ? 'selected' : ''; ?>>
                                Đang hoạt động
                            </option>
                            <option value="inactive" <?php echo ($_POST['status'] ?? $plan['status']) === 'inactive' ? 'selected' : ''; ?>>
                                Không hoạt động
                            </option>
                            <option value="suspended" <?php echo ($_POST['status'] ?? $plan['status']) === 'suspended' ? 'selected' : ''; ?>>
                                Tạm dừng
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
                            Cập nhật kế hoạch
                        </button>
                        <a href="view.php?id=<?php echo $planId; ?>" class="btn btn-secondary">
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
let checklistCounter = <?php echo count($checklist); ?>;

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
</script>

<?php require_once '../../../includes/footer.php'; ?>