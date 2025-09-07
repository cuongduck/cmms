<?php
$pageTitle = 'Cập nhật thực hiện bảo trì';
$currentModule = 'maintenance';
$moduleCSS = 'maintenance';

require_once '../../../includes/header.php';
requirePermission('maintenance', 'edit');

$executionId = $_GET['id'] ?? null;

if (!$executionId) {
    header('Location: ../index.php?error=ID không hợp lệ');
    exit;
}

// Get execution details
$sql = "SELECT me.*, e.code as equipment_code, e.name as equipment_name,
               mp.plan_code, mp.plan_name
        FROM maintenance_executions me
        JOIN equipment e ON me.equipment_id = e.id
        LEFT JOIN maintenance_plans mp ON me.plan_id = mp.id
        WHERE me.id = ?";

$execution = $db->fetch($sql, [$executionId]);

if (!$execution) {
    header('Location: ../index.php?error=Không tìm thấy lệnh thực hiện');
    exit;
}

// Parse data
$teamMembers = [];
if (!empty($execution['team_members'])) {
    $teamMembers = json_decode($execution['team_members'], true) ?? [];
}

$checklist = [];
if (!empty($execution['checklist_data'])) {
    $checklist = json_decode($execution['checklist_data'], true) ?? [];
}

$partsUsed = [];
if (!empty($execution['parts_used'])) {
    $partsUsed = json_decode($execution['parts_used'], true) ?? [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'scheduled_date' => $_POST['scheduled_date'],
        'estimated_duration' => $_POST['estimated_duration'],
        'assigned_to' => $_POST['assigned_to'] ?: null,
        'priority' => $_POST['priority'],
        'notes' => $_POST['notes'],
        'issues_found' => $_POST['issues_found'],
        'recommendations' => $_POST['recommendations']
    ];
    
    // Validation
    $errors = [];
    
    if (empty($data['title'])) {
        $errors[] = 'Vui lòng nhập tiêu đề công việc';
    }
    
    if (empty($data['scheduled_date'])) {
        $errors[] = 'Vui lòng chọn ngày thực hiện';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Process team members
            $newTeamMembers = [];
            if (!empty($_POST['team_members'])) {
                foreach ($_POST['team_members'] as $memberId) {
                    if (!empty($memberId)) {
                        $newTeamMembers[] = $memberId;
                    }
                }
            }
            
            // Update execution
            $sql = "UPDATE maintenance_executions SET 
                title = ?, description = ?, scheduled_date = ?, estimated_duration = ?,
                assigned_to = ?, team_members = ?, priority = ?, notes = ?,
                issues_found = ?, recommendations = ?, updated_at = NOW()
                WHERE id = ?";
            
            $params = [
                $data['title'],
                $data['description'],
                $data['scheduled_date'],
                $data['estimated_duration'],
                $data['assigned_to'],
                !empty($newTeamMembers) ? json_encode($newTeamMembers) : null,
                $data['priority'],
                $data['notes'],
                $data['issues_found'],
                $data['recommendations'],
                $executionId
            ];
            
            $db->execute($sql, $params);
            
            // Update checklist if provided
            if (isset($_POST['checklist_items'])) {
                $newChecklist = [];
                foreach ($_POST['checklist_items'] as $index => $item) {
                    if (!empty($item['description'])) {
                        $newChecklist[] = [
                            'id' => $index + 1,
                            'description' => $item['description'],
                            'required' => !empty($item['required']),
                            'notes' => $item['notes'] ?? '',
                            'completed' => !empty($item['completed']),
                            'completion_notes' => $item['completion_notes'] ?? ''
                        ];
                    }
                }
                
                if (!empty($newChecklist)) {
                    // Calculate completion percentage
                    $totalItems = count($newChecklist);
                    $completedItems = 0;
                    foreach ($newChecklist as $item) {
                        if ($item['completed']) {
                            $completedItems++;
                        }
                    }
                    $completionPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;
                    
                    $sql = "UPDATE maintenance_executions SET checklist_data = ?, completion_percentage = ? WHERE id = ?";
                    $db->execute($sql, [json_encode($newChecklist, JSON_UNESCAPED_UNICODE), $completionPercentage, $executionId]);
                }
            }
            
            // Update parts used if provided
            if (isset($_POST['parts_used'])) {
                $newPartsUsed = [];
                $partsCost = 0;
                
                foreach ($_POST['parts_used'] as $index => $part) {
                    if (!empty($part['part_code']) && !empty($part['quantity'])) {
                        $partData = [
                            'part_code' => $part['part_code'],
                            'part_name' => $part['part_name'] ?? '',
                            'quantity' => floatval($part['quantity']),
                            'unit_price' => floatval($part['unit_price'] ?? 0),
                            'notes' => $part['notes'] ?? ''
                        ];
                        $newPartsUsed[] = $partData;
                        $partsCost += $partData['quantity'] * $partData['unit_price'];
                    }
                }
                
                $laborCost = floatval($_POST['labor_cost'] ?? 0);
                $totalCost = $partsCost + $laborCost;
                
                $sql = "UPDATE maintenance_executions SET 
                    parts_used = ?, labor_cost = ?, parts_cost = ?, total_cost = ?
                    WHERE id = ?";
                $db->execute($sql, [
                    !empty($newPartsUsed) ? json_encode($newPartsUsed, JSON_UNESCAPED_UNICODE) : null,
                    $laborCost,
                    $partsCost,
                    $totalCost,
                    $executionId
                ]);
            }
            
            // Create history record
            $sql = "INSERT INTO maintenance_history (execution_id, action, comments, performed_by) 
                    VALUES (?, 'updated', ?, ?)";
            $db->execute($sql, [$executionId, 'Cập nhật thông tin thực hiện', getCurrentUser()['id']]);
            
            $db->commit();
            
            $_SESSION['success'] = 'Cập nhật thực hiện bảo trì thành công';
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

$parts = $db->fetchAll("
    SELECT part_code, part_name, unit_price, unit
    FROM parts 
    WHERE part_code IS NOT NULL
    ORDER BY part_code
");

$breadcrumb = [
    ['title' => 'Bảo trì thiết bị', 'url' => '../index.php'],
    ['title' => 'Cập nhật thực hiện', 'url' => '']
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
            <!-- Execution Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Cập nhật thông tin thực hiện
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Execution Code and Equipment (Read-only) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mã thực hiện</label>
                            <div class="form-control bg-light">
                                <?php echo htmlspecialchars($execution['execution_code']); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Loại thực hiện</label>
                            <div class="form-control bg-light">
                                <span class="maintenance-type-<?php echo strtolower($execution['execution_type']); ?>">
                                    <?php 
                                    echo $execution['execution_type'] === 'PM' ? 'Bảo trì kế hoạch' : 
                                        ($execution['execution_type'] === 'CLIT' ? 'Hiệu chuẩn' : 'Sự cố'); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thiết bị</label>
                        <div class="form-control bg-light">
                            <strong><?php echo htmlspecialchars($execution['equipment_code']); ?></strong> - 
                            <?php echo htmlspecialchars($execution['equipment_name']); ?>
                            <?php if ($execution['plan_code']): ?>
                                <br><small class="text-muted">Kế hoạch: <?php echo htmlspecialchars($execution['plan_code']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề công việc <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? $execution['title']); ?>" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Mô tả chi tiết các công việc cần thực hiện..."><?php echo htmlspecialchars($_POST['description'] ?? $execution['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ngày thực hiện dự kiến <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_date" class="form-control" 
                                       value="<?php echo $_POST['scheduled_date'] ?? ($execution['scheduled_date'] ? date('Y-m-d\TH:i', strtotime($execution['scheduled_date'])) : ''); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Thời gian dự kiến (phút)</label>
                                <input type="number" name="estimated_duration" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? $execution['estimated_duration']); ?>" 
                                       min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ghi chú</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Ghi chú thêm về công việc..."><?php echo htmlspecialchars($_POST['notes'] ?? $execution['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vấn đề phát hiện</label>
                                <textarea name="issues_found" class="form-control" rows="3" 
                                          placeholder="Các vấn đề phát hiện trong quá trình thực hiện..."><?php echo htmlspecialchars($_POST['issues_found'] ?? $execution['issues_found']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Khuyến nghị</label>
                        <textarea name="recommendations" class="form-control" rows="3" 
                                  placeholder="Khuyến nghị cho lần bảo trì tiếp theo..."><?php echo htmlspecialchars($_POST['recommendations'] ?? $execution['recommendations']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Checklist -->
            <?php if (!empty($checklist)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Danh sách kiểm tra
                    </h6>
                </div>
                <div class="card-body">
                    <div id="checklist-container">
                        <?php foreach ($checklist as $index => $item): ?>
                        <div class="checklist-item border rounded p-3 mb-3 <?php echo !empty($item['completed']) ? 'completed' : ''; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-check">
                                        <input type="checkbox" name="checklist_items[<?php echo $index; ?>][completed]" 
                                               class="form-check-input me-2" value="1"
                                               <?php echo !empty($item['completed']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                            <?php if (!empty($item['required'])): ?>
                                                <span class="badge badge-warning ms-1">Bắt buộc</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <input type="hidden" name="checklist_items[<?php echo $index; ?>][description]" 
                                           value="<?php echo htmlspecialchars($item['description']); ?>">
                                    <input type="hidden" name="checklist_items[<?php echo $index; ?>][required]" 
                                           value="<?php echo !empty($item['required']) ? '1' : '0'; ?>">
                                    <?php if (!empty($item['notes'])): ?>
                                        <div class="text-muted small mt-1">
                                            <?php echo htmlspecialchars($item['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <textarea name="checklist_items[<?php echo $index; ?>][completion_notes]" 
                                              class="form-control form-control-sm" rows="2" 
                                              placeholder="Ghi chú hoàn thành..."><?php echo htmlspecialchars($item['completion_notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Parts Used -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Phụ tùng sử dụng
                    </h6>
                </div>
                <div class="card-body">
                    <div id="parts-container">
                        <?php if (!empty($partsUsed)): ?>
                            <?php foreach ($partsUsed as $index => $part): ?>
                            <div class="parts-item border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Mã phụ tùng</label>
                                        <input type="text" name="parts_used[<?php echo $index; ?>][part_code]" 
                                               class="form-control" value="<?php echo htmlspecialchars($part['part_code']); ?>"
                                               list="parts-list">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tên phụ tùng</label>
                                        <input type="text" name="parts_used[<?php echo $index; ?>][part_name]" 
                                               class="form-control" value="<?php echo htmlspecialchars($part['part_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Số lượng</label>
                                        <input type="number" name="parts_used[<?php echo $index; ?>][quantity]" 
                                               class="form-control" step="0.01" 
                                               value="<?php echo $part['quantity'] ?? 0; ?>"
                                               onchange="calculatePartCost(this)">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Đơn giá</label>
                                        <input type="number" name="parts_used[<?php echo $index; ?>][unit_price]" 
                                               class="form-control" step="0.01" 
                                               value="<?php echo $part['unit_price'] ?? 0; ?>"
                                               onchange="calculatePartCost(this)">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-part">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Thành tiền</label>
                                        <div class="form-control-plaintext part-total">
                                            <?php echo number_format(($part['quantity'] ?? 0) * ($part['unit_price'] ?? 0), 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <textarea name="parts_used[<?php echo $index; ?>][notes]" 
                                                  class="form-control" rows="1" 
                                                  placeholder="Ghi chú..."><?php echo htmlspecialchars($part['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="parts-item border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Mã phụ tùng</label>
                                        <input type="text" name="parts_used[0][part_code]" 
                                               class="form-control" list="parts-list">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Tên phụ tùng</label>
                                        <input type="text" name="parts_used[0][part_name]" 
                                               class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Số lượng</label>
                                        <input type="number" name="parts_used[0][quantity]" 
                                               class="form-control" step="0.01" 
                                               onchange="calculatePartCost(this)">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Đơn giá</label>
                                        <input type="number" name="parts_used[0][unit_price]" 
                                               class="form-control" step="0.01" 
                                               onchange="calculatePartCost(this)">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-part">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Thành tiền</label>
                                        <div class="form-control-plaintext part-total">0</div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <textarea name="parts_used[0][notes]" 
                                                  class="form-control" rows="1" 
                                                  placeholder="Ghi chú..."></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-primary" id="add-part">
                                <i class="fas fa-plus me-1"></i>
                                Thêm phụ tùng
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">Chi phí nhân công</label>
                                    <input type="number" name="labor_cost" class="form-control" 
                                           value="<?php echo $execution['labor_cost'] ?? 0; ?>" 
                                           step="1000" onchange="calculateTotalCost()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Tổng chi phí</label>
                                    <div class="form-control bg-light" id="total-cost">
                                        <?php echo number_format($execution['total_cost'] ?? 0, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Assignment -->
            <div class="card mb-4">
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
                                        <?php echo ($_POST['assigned_to'] ?? $execution['assigned_to']) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thành viên nhóm</label>
                        <select name="team_members[]" class="form-select" multiple size="4">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"
                                        <?php echo in_array($user['id'], $teamMembers) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Giữ Ctrl để chọn nhiều người</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Độ ưu tiên</label>
                        <select name="priority" class="form-select">
                            <option value="Low" <?php echo ($_POST['priority'] ?? $execution['priority']) === 'Low' ? 'selected' : ''; ?>>
                                Thấp
                            </option>
                            <option value="Medium" <?php echo ($_POST['priority'] ?? $execution['priority']) === 'Medium' ? 'selected' : ''; ?>>
                                Trung bình
                            </option>
                            <option value="High" <?php echo ($_POST['priority'] ?? $execution['priority']) === 'High' ? 'selected' : ''; ?>>
                                Cao
                            </option>
                            <option value="Critical" <?php echo ($_POST['priority'] ?? $execution['priority']) === 'Critical' ? 'selected' : ''; ?>>
                                Nghiêm trọng
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Cập nhật thực hiện
                        </button>
                        <a href="view.php?id=<?php echo $executionId; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Hủy bỏ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Parts datalist -->
<datalist id="parts-list">
    <?php foreach ($parts as $part): ?>
        <option value="<?php echo htmlspecialchars($part['part_code']); ?>" 
                data-name="<?php echo htmlspecialchars($part['part_name']); ?>"
                data-price="<?php echo $part['unit_price']; ?>">
            <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
        </option>
    <?php endforeach; ?>
</datalist>

<script>
let partsCounter = <?php echo !empty($partsUsed) ? count($partsUsed) : 1; ?>;

// Add new part
document.getElementById('add-part').addEventListener('click', function() {
    const container = document.getElementById('parts-container');
    const newPart = document.createElement('div');
    newPart.className = 'parts-item border rounded p-3 mb-3';
    newPart.innerHTML = `
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">Mã phụ tùng</label>
                <input type="text" name="parts_used[${partsCounter}][part_code]" 
                       class="form-control" list="parts-list">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tên phụ tùng</label>
                <input type="text" name="parts_used[${partsCounter}][part_name]" 
                       class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Số lượng</label>
                <input type="number" name="parts_used[${partsCounter}][quantity]" 
                       class="form-control" step="0.01" 
                       onchange="calculatePartCost(this)">
            </div>
            <div class="col-md-2">
                <label class="form-label">Đơn giá</label>
                <input type="number" name="parts_used[${partsCounter}][unit_price]" 
                       class="form-control" step="0.01" 
                       onchange="calculatePartCost(this)">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-part">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-1">
                <label class="form-label">Thành tiền</label>
                <div class="form-control-plaintext part-total">0</div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <textarea name="parts_used[${partsCounter}][notes]" 
                          class="form-control" rows="1" 
                          placeholder="Ghi chú..."></textarea>
            </div>
        </div>
    `;
    
    container.appendChild(newPart);
    partsCounter++;
});

// Remove part
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-part')) {
        e.target.closest('.parts-item').remove();
        calculateTotalCost();
    }
});

// Calculate part cost
function calculatePartCost(element) {
    const partItem = element.closest('.parts-item');
    const quantity = parseFloat(partItem.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(partItem.querySelector('input[name*="[unit_price]"]').value) || 0;
    const total = quantity * unitPrice;
    
    partItem.querySelector('.part-total').textContent = total.toLocaleString('vi-VN');
    calculateTotalCost();
}

// Calculate total cost
function calculateTotalCost() {
    let partsCost = 0;
    document.querySelectorAll('.parts-item').forEach(function(item) {
        const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(item.querySelector('input[name*="[unit_price]"]').value) || 0;
        partsCost += quantity * unitPrice;
    });
    
    const laborCost = parseFloat(document.querySelector('input[name="labor_cost"]').value) || 0;
    const totalCost = partsCost + laborCost;
    
    document.getElementById('total-cost').textContent = totalCost.toLocaleString('vi-VN');
}

// Auto-fill part info when selecting from datalist
document.addEventListener('input', function(e) {
    if (e.target.name && e.target.name.includes('[part_code]')) {
        const partCode = e.target.value;
        const option = document.querySelector(`#parts-list option[value="${partCode}"]`);
        if (option) {
            const partItem = e.target.closest('.parts-item');
            const nameInput = partItem.querySelector('input[name*="[part_name]"]');
            const priceInput = partItem.querySelector('input[name*="[unit_price]"]');
            
            if (nameInput) nameInput.value = option.dataset.name || '';
            if (priceInput) priceInput.value = option.dataset.price || 0;
            
            calculatePartCost(e.target);
        }
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>