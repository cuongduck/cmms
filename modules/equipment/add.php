<?php
/**
 * Add New Equipment - modules/equipment/add.php
 * Complete implementation with proper error handling
 */

$pageTitle = 'Thêm thiết bị mới';
$currentModule = 'equipment';
$moduleCSS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Thêm thiết bị mới']
];

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'create');

$pageActions = '
<div class="btn-group">
    <button type="button" class="btn btn-success" onclick="saveEquipment()">
        <i class="fas fa-save me-1"></i> Lưu thiết bị
    </button>
    <button type="button" class="btn btn-outline-info" onclick="saveDraft()">
        <i class="fas fa-bookmark me-1"></i> Lưu nháp
    </button>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Quay lại
    </a>
</div>';

// Create upload directories if not exist
$uploadDirs = [
    'uploads/equipment/images/',
    'uploads/equipment/manuals/'
];

foreach ($uploadDirs as $dir) {
    $fullPath = BASE_PATH . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
}

// Handle form submission
$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'save' || $action === 'draft') {
        // Process form data
        $formData = [
            'code' => trim($_POST['code'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'industry_id' => (int)($_POST['industry_id'] ?? 0),
            'workshop_id' => (int)($_POST['workshop_id'] ?? 0),
            'line_id' => !empty($_POST['line_id']) ? (int)$_POST['line_id'] : null,
            'area_id' => !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null,
            'machine_type_id' => (int)($_POST['machine_type_id'] ?? 0),
            'equipment_group_id' => !empty($_POST['equipment_group_id']) ? (int)$_POST['equipment_group_id'] : null,
            'owner_user_id' => !empty($_POST['owner_user_id']) ? (int)$_POST['owner_user_id'] : null,
            'backup_owner_user_id' => !empty($_POST['backup_owner_user_id']) ? (int)$_POST['backup_owner_user_id'] : null,
            'manufacturer' => trim($_POST['manufacturer'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'serial_number' => trim($_POST['serial_number'] ?? ''),
            'manufacture_year' => !empty($_POST['manufacture_year']) ? (int)$_POST['manufacture_year'] : null,
            'installation_date' => !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
            'warranty_expiry' => !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null,
            'maintenance_frequency_days' => !empty($_POST['maintenance_frequency_days']) ? (int)$_POST['maintenance_frequency_days'] : null,
            'maintenance_frequency_type' => $_POST['maintenance_frequency_type'] ?? 'monthly',
            'specifications' => trim($_POST['specifications'] ?? ''),
            'location_details' => trim($_POST['location_details'] ?? ''),
            'criticality' => $_POST['criticality'] ?? 'Medium',
            'status' => $_POST['status'] ?? 'active',
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        // Validation for save action
        if ($action === 'save') {
            $errors = validateEquipmentForm($formData);
        }
        
        // If no errors, proceed with save
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Handle file uploads
                $imagePath = null;
                $manualPath = null;
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imageUpload = uploadFile(
                        $_FILES['image'],
                        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                        'uploads/equipment/images/',
                        5 * 1024 * 1024 // 5MB
                    );
                    
                    if ($imageUpload['success']) {
                        $imagePath = $imageUpload['relative_path'];
                        
                        // Resize image if needed
                        if (function_exists('resizeImage')) {
                            resizeImage($imageUpload['path'], $imageUpload['path'], 800, 600);
                        }
                    } else {
                        $errors[] = 'Lỗi upload hình ảnh: ' . $imageUpload['message'];
                    }
                }
                
                if (isset($_FILES['manual']) && $_FILES['manual']['error'] === UPLOAD_ERR_OK) {
                    $manualUpload = uploadFile(
                        $_FILES['manual'],
                        ['pdf', 'doc', 'docx'],
                        'uploads/equipment/manuals/',
                        10 * 1024 * 1024 // 10MB
                    );
                    
                    if ($manualUpload['success']) {
                        $manualPath = $manualUpload['relative_path'];
                    } else {
                        $errors[] = 'Lỗi upload tài liệu: ' . $manualUpload['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Auto-generate code if empty
                    if (empty($formData['code'])) {
                        try {
                            $industry = $db->fetch("SELECT code FROM industries WHERE id = ?", [$formData['industry_id']]);
                            $workshop = $db->fetch("SELECT code FROM workshops WHERE id = ?", [$formData['workshop_id']]);
                            
                            $lineCode = '';
                            if ($formData['line_id']) {
                                $line = $db->fetch("SELECT code FROM production_lines WHERE id = ?", [$formData['line_id']]);
                                $lineCode = $line['code'] ?? '';
                            }
                            
                            $areaCode = '';
                            if ($formData['area_id']) {
                                $area = $db->fetch("SELECT code FROM areas WHERE id = ?", [$formData['area_id']]);
                                $areaCode = $area['code'] ?? '';
                            }
                            
                            $formData['code'] = generateEquipmentCode(
                                $industry['code'] ?? 'EQ', 
                                $workshop['code'] ?? 'WS', 
                                $lineCode, 
                                $areaCode
                            );
                        } catch (Exception $e) {
                            $formData['code'] = 'EQ' . date('YmdHis');
                        }
                    }
                    
                    // Insert equipment
                    $sql = "INSERT INTO equipment (
                        code, name, industry_id, workshop_id, line_id, area_id,
                        machine_type_id, equipment_group_id, owner_user_id, backup_owner_user_id,
                        manufacturer, model, serial_number, manufacture_year,
                        installation_date, warranty_expiry, maintenance_frequency_days, maintenance_frequency_type,
                        specifications, location_details, criticality, status,
                        image_path, manual_path, notes, created_by, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    
                    $params = [
                        $formData['code'], $formData['name'], $formData['industry_id'], $formData['workshop_id'],
                        $formData['line_id'], $formData['area_id'], $formData['machine_type_id'], $formData['equipment_group_id'],
                        $formData['owner_user_id'], $formData['backup_owner_user_id'], $formData['manufacturer'],
                        $formData['model'], $formData['serial_number'], $formData['manufacture_year'],
                        $formData['installation_date'], $formData['warranty_expiry'], $formData['maintenance_frequency_days'],
                        $formData['maintenance_frequency_type'], $formData['specifications'], $formData['location_details'],
                        $formData['criticality'], $formData['status'], $imagePath, $manualPath, $formData['notes'],
                        getCurrentUser()['id']
                    ];
                    
                    $db->execute($sql, $params);
                    $equipmentId = $db->lastInsertId();
                    
                    // Log activity
                    if (function_exists('logActivity')) {
                        logActivity('create', 'equipment', "Tạo thiết bị: {$formData['name']} ({$formData['code']})", getCurrentUser()['id']);
                    }
                    
                    // Create audit trail
                    if (function_exists('createAuditTrail')) {
                        createAuditTrail('equipment', $equipmentId, 'create', null, $formData);
                    }
                    
                    $db->commit();
                    
                    if ($action === 'save') {
                        $success = 'Tạo thiết bị thành công! Mã: ' . $formData['code'];
                        // Redirect after success
                        header('Refresh: 2; url=view.php?id=' . $equipmentId);
                    } else {
                        $success = 'Lưu nháp thành công!';
                    }
                    
                    // Clear form data after successful save
                    if ($action === 'save') {
                        $formData = [];
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = 'Lỗi khi lưu thiết bị: ' . $e->getMessage();
                
                // Clean up uploaded files if database insert failed
                if (isset($imagePath) && $imagePath && file_exists(BASE_PATH . '/' . $imagePath)) {
                    unlink(BASE_PATH . '/' . $imagePath);
                }
                if (isset($manualPath) && $manualPath && file_exists(BASE_PATH . '/' . $manualPath)) {
                    unlink(BASE_PATH . '/' . $manualPath);
                }
            }
        }
    }
}

// Load options for dropdowns
$industries = $db->fetchAll("SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name");
$workshops = $db->fetchAll("SELECT id, name, code, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
$lines = $db->fetchAll("SELECT id, name, code, workshop_id FROM production_lines WHERE status = 'active' ORDER BY name");
$areas = $db->fetchAll("SELECT id, name, code, workshop_id FROM areas WHERE status = 'active' ORDER BY name");
$machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
$equipmentGroups = $db->fetchAll("SELECT id, name, machine_type_id FROM equipment_groups WHERE status = 'active' ORDER BY name");
$users = $db->fetchAll("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name");

/**
 * Validation Functions
 */
function validateEquipmentForm($data) {
    global $db;
    $errors = [];
    
    // Required fields validation
    if (empty($data['name'])) {
        $errors[] = 'Tên thiết bị không được trống';
    } elseif (strlen($data['name']) < 2 || strlen($data['name']) > 200) {
        $errors[] = 'Tên thiết bị phải từ 2-200 ký tự';
    }
    
    if (empty($data['industry_id'])) {
        $errors[] = 'Vui lòng chọn ngành sản xuất';
    } else {
        $sql = "SELECT id FROM industries WHERE id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['industry_id']])) {
            $errors[] = 'Ngành sản xuất không hợp lệ';
        }
    }
    
    if (empty($data['workshop_id'])) {
        $errors[] = 'Vui lòng chọn xưởng sản xuất';
    } else {
        $sql = "SELECT id FROM workshops WHERE id = ? AND industry_id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['workshop_id'], $data['industry_id']])) {
            $errors[] = 'Xưởng sản xuất không hợp lệ';
        }
    }
    
    if (empty($data['machine_type_id'])) {
        $errors[] = 'Vui lòng chọn dòng máy';
    } else {
        $sql = "SELECT id FROM machine_types WHERE id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['machine_type_id']])) {
            $errors[] = 'Dòng máy không hợp lệ';
        }
    }
    
    // Code validation
    if (!empty($data['code'])) {
        if (!preg_match('/^[A-Z0-9_-]+$/', $data['code'])) {
            $errors[] = 'Mã thiết bị chỉ được chứa chữ hoa, số, dấu gạch ngang và gạch dưới';
        } elseif (strlen($data['code']) > 30) {
            $errors[] = 'Mã thiết bị không được quá 30 ký tự';
        } else {
            $sql = "SELECT id FROM equipment WHERE code = ?";
            if ($db->fetch($sql, [$data['code']])) {
                $errors[] = 'Mã thiết bị đã tồn tại';
            }
        }
    }
    
    // Optional field validations
    if ($data['manufacture_year'] && ($data['manufacture_year'] < 1900 || $data['manufacture_year'] > date('Y') + 1)) {
        $errors[] = 'Năm sản xuất không hợp lệ';
    }
    
    if ($data['maintenance_frequency_days'] && ($data['maintenance_frequency_days'] < 1 || $data['maintenance_frequency_days'] > 365)) {
        $errors[] = 'Chu kỳ bảo trì phải từ 1 đến 365 ngày';
    }
    
    if (!in_array($data['criticality'], ['Low', 'Medium', 'High', 'Critical'])) {
        $errors[] = 'Mức độ quan trọng không hợp lệ';
    }
    
    if (!in_array($data['status'], ['active', 'inactive', 'maintenance', 'broken'])) {
        $errors[] = 'Trạng thái không hợp lệ';
    }
    
    return $errors;
}

/**
 * Upload file function
 */

require_once '../../includes/header.php';
?>
<!-- CSS Styles for Equipment Add Form -->
<style>
:root {
    --equipment-primary: #1e3a8a;
    --equipment-success: #10b981;
    --equipment-warning: #f59e0b;
    --equipment-danger: #ef4444;
    --equipment-info: #06b6d4;
    --equipment-light: #f8fafc;
    --equipment-dark: #374151;
}

.equipment-form-container {
    background: var(--equipment-light);
    min-height: 100vh;
    padding: 1rem;
}

.form-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.form-section-header {
    background: linear-gradient(135deg, var(--equipment-primary), #3b82f6);
    color: white;
    padding: 1rem 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-section-body {
    padding: 1.5rem;
}

.form-floating .form-control,
.form-floating .form-select {
    border-radius: 0.5rem;
    border: 1px solid #d1d5db;
    transition: all 0.2s ease;
}

.form-floating .form-control:focus,
.form-floating .form-select:focus {
    border-color: var(--equipment-primary);
    box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
}

.form-floating > label {
    color: #6b7280;
    font-weight: 500;
}

.file-upload-section {
    border: 2px dashed #d1d5db;
    border-radius: 0.75rem;
    padding: 2rem;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-upload-section:hover,
.file-upload-section.drag-over {
    border-color: var(--equipment-primary);
    background: rgba(30, 58, 138, 0.05);
}

.file-upload-icon {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}

.file-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.equipment-preview {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border: 1px solid #d1d5db;
    border-radius: 0.75rem;
    padding: 1.5rem;
    position: sticky;
    top: 100px;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.preview-item:last-child {
    border-bottom: none;
}

.preview-label {
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.preview-value {
    color: #6b7280;
    font-size: 0.875rem;
    text-align: right;
    max-width: 200px;
    word-wrap: break-word;
}

.preview-value.empty {
    color: #9ca3af;
    font-style: italic;
}

.form-progress {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.progress-bar-custom {
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--equipment-primary), #3b82f6);
    transition: width 0.3s ease;
    border-radius: 4px;
}

.character-counter {
    font-size: 0.75rem;
    color: #9ca3af;
    text-align: right;
    margin-top: 0.25rem;
}

.character-counter.warning {
    color: #f59e0b;
}

.character-counter.danger {
    color: #ef4444;
}

.error-summary {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.success-summary {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .equipment-form-container {
        padding: 0.5rem;
    }
    
    .form-section-body {
        padding: 1rem;
    }
    
    .file-upload-section {
        padding: 1rem;
    }
    
    .file-upload-icon {
        font-size: 2rem;
    }
    
    .equipment-preview {
        position: static;
        margin-top: 1rem;
    }
}
</style>

<div class="equipment-form-container">
    <!-- Form Progress -->
    <div class="form-progress">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Tiến độ hoàn thành form</h6>
            <small class="text-muted"><span id="progressPercent">0</span>%</small>
        </div>
        <div class="progress-bar-custom">
            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
        </div>
        <small class="text-muted">Điền đầy đủ thông tin để hoàn thành việc tạo thiết bị</small>
    </div>

    <!-- Error/Success Messages -->
    <?php if (!empty($errors)): ?>
        <div class="error-summary">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-summary">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form id="equipmentForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="action" id="formAction" value="save">
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-info-circle"></i>
                        Thông tin cơ bản
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="equipmentName" name="name" 
                                           placeholder="Tên thiết bị" required maxlength="200"
                                           value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                                    <label for="equipmentName">Tên thiết bị *</label>
                                    <div class="invalid-feedback">Vui lòng nhập tên thiết bị</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control text-uppercase" id="equipmentCode" name="code" 
                                           placeholder="Mã thiết bị" maxlength="30"
                                           value="<?php echo htmlspecialchars($formData['code'] ?? ''); ?>">
                                    <label for="equipmentCode">Mã thiết bị</label>
                                    <div class="form-text">Để trống để tự động tạo mã</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="manufacturer" name="manufacturer" 
                                           placeholder="Nhà sản xuất" maxlength="100"
                                           value="<?php echo htmlspecialchars($formData['manufacturer'] ?? ''); ?>">
                                    <label for="manufacturer">Nhà sản xuất</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="model" name="model" 
                                           placeholder="Model" maxlength="100"
                                           value="<?php echo htmlspecialchars($formData['model'] ?? ''); ?>">
                                    <label for="model">Model</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="serialNumber" name="serial_number" 
                                           placeholder="Số seri" maxlength="100"
                                           value="<?php echo htmlspecialchars($formData['serial_number'] ?? ''); ?>">
                                    <label for="serialNumber">Số seri</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="manufactureYear" name="manufacture_year" 
                                           placeholder="Năm sản xuất" min="1900" max="2030"
                                           value="<?php echo htmlspecialchars($formData['manufacture_year'] ?? ''); ?>">
                                    <label for="manufactureYear">Năm sản xuất</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Structure Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-map-marked-alt"></i>
                        Vị trí & Phân loại
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="industryId" name="industry_id" required onchange="updateWorkshops()">
                                        <option value="">Chọn ngành</option>
                                        <?php foreach ($industries as $industry): ?>
                                            <option value="<?php echo $industry['id']; ?>" 
                                                    <?php echo ($formData['industry_id'] ?? null) == $industry['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($industry['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="industryId">Ngành sản xuất *</label>
                                    <div class="invalid-feedback">Vui lòng chọn ngành sản xuất</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="workshopId" name="workshop_id" required onchange="updateLines()">
                                        <option value="">Chọn xưởng</option>
                                        <?php foreach ($workshops as $workshop): ?>
                                            <option value="<?php echo $workshop['id']; ?>" 
                                                    data-industry="<?php echo $workshop['industry_id']; ?>"
                                                    <?php echo ($formData['workshop_id'] ?? null) == $workshop['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($workshop['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="workshopId">Xưởng sản xuất *</label>
                                    <div class="invalid-feedback">Vui lòng chọn xưởng sản xuất</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="lineId" name="line_id">
                                        <option value="">Chọn line sản xuất</option>
                                        <?php foreach ($lines as $line): ?>
                                            <option value="<?php echo $line['id']; ?>" 
                                                    data-workshop="<?php echo $line['workshop_id']; ?>"
                                                    <?php echo ($formData['line_id'] ?? null) == $line['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($line['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="lineId">Line sản xuất</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="areaId" name="area_id">
                                        <option value="">Chọn khu vực</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo $area['id']; ?>" 
                                                    data-workshop="<?php echo $area['workshop_id']; ?>"
                                                    <?php echo ($formData['area_id'] ?? null) == $area['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($area['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="areaId">Khu vực</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="machineTypeId" name="machine_type_id" required onchange="updateEquipmentGroups()">
                                        <option value="">Chọn dòng máy</option>
                                        <?php foreach ($machineTypes as $machineType): ?>
                                            <option value="<?php echo $machineType['id']; ?>" 
                                                    <?php echo ($formData['machine_type_id'] ?? null) == $machineType['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($machineType['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="machineTypeId">Dòng máy *</label>
                                    <div class="invalid-feedback">Vui lòng chọn dòng máy</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="equipmentGroupId" name="equipment_group_id">
                                        <option value="">Chọn cụm thiết bị</option>
                                        <?php foreach ($equipmentGroups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>" 
                                                    data-machine-type="<?php echo $group['machine_type_id']; ?>"
                                                    <?php echo ($formData['equipment_group_id'] ?? null) == $group['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($group['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="equipmentGroupId">Cụm thiết bị</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="form-floating">
                                <textarea class="form-control" id="locationDetails" name="location_details" 
                                          placeholder="Chi tiết vị trí" style="height: 100px;" maxlength="500"><?php echo htmlspecialchars($formData['location_details'] ?? ''); ?></textarea>
                                <label for="locationDetails">Chi tiết vị trí</label>
                                <div class="character-counter">
                                    <span id="locationDetailsCounter">0</span>/500 ký tự
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Management & Maintenance Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-cogs"></i>
                        Quản lý & Bảo trì
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="ownerUserId" name="owner_user_id">
                                        <option value="">Chọn người quản lý</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo ($formData['owner_user_id'] ?? null) == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="ownerUserId">Người quản lý chính</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="backupOwnerUserId" name="backup_owner_user_id">
                                        <option value="">Chọn người quản lý phụ</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo ($formData['backup_owner_user_id'] ?? null) == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="backupOwnerUserId">Người quản lý phụ</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="installationDate" name="installation_date" 
                                           placeholder="Ngày lắp đặt"
                                           value="<?php echo htmlspecialchars($formData['installation_date'] ?? ''); ?>">
                                    <label for="installationDate">Ngày lắp đặt</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="warrantyExpiry" name="warranty_expiry" 
                                           placeholder="Ngày hết bảo hành"
                                           value="<?php echo htmlspecialchars($formData['warranty_expiry'] ?? ''); ?>">
                                    <label for="warrantyExpiry">Ngày hết bảo hành</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="maintenanceFrequencyDays" name="maintenance_frequency_days" 
                                           placeholder="Chu kỳ bảo trì (ngày)" min="1" max="365"
                                           value="<?php echo htmlspecialchars($formData['maintenance_frequency_days'] ?? ''); ?>">
                                    <label for="maintenanceFrequencyDays">Chu kỳ bảo trì (ngày)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="maintenanceFrequencyType" name="maintenance_frequency_type">
                                        <option value="daily" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'daily' ? 'selected' : ''; ?>>Hàng ngày</option>
                                        <option value="weekly" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'weekly' ? 'selected' : ''; ?>>Hàng tuần</option>
                                        <option value="monthly" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>>Hàng tháng</option>
                                        <option value="quarterly" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'quarterly' ? 'selected' : ''; ?>>Hàng quý</option>
                                        <option value="yearly" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'yearly' ? 'selected' : ''; ?>>Hàng năm</option>
                                        <option value="custom" <?php echo ($formData['maintenance_frequency_type'] ?? 'monthly') === 'custom' ? 'selected' : ''; ?>>Tùy chỉnh</option>
                                    </select>
                                    <label for="maintenanceFrequencyType">Loại chu kỳ bảo trì</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="criticality" name="criticality">
                                        <option value="Low" <?php echo ($formData['criticality'] ?? 'Medium') === 'Low' ? 'selected' : ''; ?>>Thấp</option>
                                        <option value="Medium" <?php echo ($formData['criticality'] ?? 'Medium') === 'Medium' ? 'selected' : ''; ?>>Trung bình</option>
                                        <option value="High" <?php echo ($formData['criticality'] ?? 'Medium') === 'High' ? 'selected' : ''; ?>>Cao</option>
                                        <option value="Critical" <?php echo ($formData['criticality'] ?? 'Medium') === 'Critical' ? 'selected' : ''; ?>>Nghiêm trọng</option>
                                    </select>
                                    <label for="criticality">Mức độ quan trọng</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($formData['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                        <option value="maintenance" <?php echo ($formData['status'] ?? 'active') === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                                        <option value="broken" <?php echo ($formData['status'] ?? 'active') === 'broken' ? 'selected' : ''; ?>>Hỏng</option>
                                    </select>
                                    <label for="status">Trạng thái</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="form-floating">
                                <textarea class="form-control" id="specifications" name="specifications" 
                                          placeholder="Thông số kỹ thuật" style="height: 120px;" maxlength="2000"><?php echo htmlspecialchars($formData['specifications'] ?? ''); ?></textarea>
                                <label for="specifications">Thông số kỹ thuật</label>
                                <div class="character-counter">
                                    <span id="specificationsCounter">0</span>/2000 ký tự
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="form-floating">
                                <textarea class="form-control" id="notes" name="notes" 
                                          placeholder="Ghi chú" style="height: 100px;" maxlength="1000"><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                                <label for="notes">Ghi chú</label>
                                <div class="character-counter">
                                    <span id="notesCounter">0</span>/1000 ký tự
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Files Upload Section -->
<div class="form-section">
    <div class="form-section-header">
        <i class="fas fa-upload"></i>
        Hình ảnh
    </div>
    <div class="form-section-body">
        <div class="row g-4">
            <div class="col-md-12">
                <label class="form-label fw-semibold">
                    <i class="fas fa-image me-2"></i>Hình ảnh thiết bị
                </label>
                <div class="file-upload-section" onclick="document.getElementById('imageFile').click()">
                    <input type="file" id="imageFile" name="image" accept="image/*" class="d-none" onchange="handleFileSelect(this, 'image')">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        <strong>Click để chọn hình ảnh</strong><br>
                        hoặc kéo thả file vào đây
                    </div>
                    <small class="text-muted">
                        Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB)
                    </small>
                    <div id="imagePreview" class="file-preview d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>

            <!-- Sidebar: Preview & Actions -->
            <div class="col-lg-4">
                <!-- Equipment Preview -->
                <div class="equipment-preview">
                    <h6 class="mb-3">
                        <i class="fas fa-eye me-2"></i>Xem trước thông tin
                    </h6>
                    
                    <div id="previewContent">
                        <div class="preview-item">
                            <span class="preview-label">Tên thiết bị:</span>
                            <span class="preview-value empty" id="previewName">Chưa nhập</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Mã thiết bị:</span>
                            <span class="preview-value empty" id="previewCode">Tự động tạo</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Ngành:</span>
                            <span class="preview-value empty" id="previewIndustry">Chưa chọn</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Xưởng:</span>
                            <span class="preview-value empty" id="previewWorkshop">Chưa chọn</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Dòng máy:</span>
                            <span class="preview-value empty" id="previewMachineType">Chưa chọn</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Nhà sản xuất:</span>
                            <span class="preview-value empty" id="previewManufacturer">Chưa nhập</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Model:</span>
                            <span class="preview-value empty" id="previewModel">Chưa nhập</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Mức độ quan trọng:</span>
                            <span class="preview-value" id="previewCriticality">Trung bình</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Trạng thái:</span>
                            <span class="preview-value" id="previewStatus">Hoạt động</span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Người quản lý:</span>
                            <span class="preview-value empty" id="previewOwner">Chưa chọn</span>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-lightbulb me-1"></i>
                            Thông tin sẽ được cập nhật tự động khi bạn điền form
                        </small>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-3">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="saveEquipment()">
                            <i class="fas fa-save me-2"></i>Lưu thiết bị
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="saveDraft()">
                            <i class="fas fa-bookmark me-2"></i>Lưu nháp
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="previewEquipment()">
                            <i class="fas fa-eye me-2"></i>Xem trước đầy đủ
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset form
                        </button>
                    </div>
                </div>

                <!-- Tips & Guidelines -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="text-primary">
                        <i class="fas fa-lightbulb me-2"></i>Gợi ý
                    </h6>
                    <ul class="small mb-0 ps-3">
                        <li>Điền đầy đủ thông tin cơ bản trước khi lưu</li>
                        <li>Mã thiết bị sẽ tự động tạo nếu bỏ trống</li>
                        <li>Hình ảnh giúp nhận diện thiết bị dễ dàng hơn</li>
                        <li>Chu kỳ bảo trì giúp lên lịch tự động</li>
                        <li>Sử dụng "Lưu nháp" để lưu tạm thời</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Full Preview Modal -->
<div class="modal fade" id="fullPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Xem trước thiết bị
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fullPreviewContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-success" onclick="saveEquipment()">
                    <i class="fas fa-save me-2"></i>Lưu thiết bị
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Configuration -->
<script>
// Set configuration for JavaScript
window.equipmentConfig = {
    maxFileSize: <?php echo 5 * 1024 * 1024; ?>, // 5MB for images
    allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    allowedDocTypes: ['pdf', 'doc', 'docx'],
    autoSaveInterval: 30000 // 30 seconds
};

// Pass form data to JavaScript if exists
window.formData = <?php echo json_encode($formData); ?>;
</script>

<!-- Load Equipment Add JavaScript -->
<script src="<?php echo APP_URL; ?>/assets/js/equipment-add.js?v=<?php echo time(); ?>"></script>

<?php require_once '../../includes/footer.php'; ?>