<?php
/**
 * Add New Equipment - modules/equipment/add.php
 * Form to create new equipment in the system
 */

$pageTitle = 'Thêm thiết bị mới';
$currentModule = 'equipment';
$moduleCSS = 'equipment';
$moduleJS = 'equipment';

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
                        getConfig('upload.allowed_types.image'),
                        getConfig('upload.paths.equipment_images'),
                        getConfig('upload.max_size')
                    );
                    
                    if ($imageUpload['success']) {
                        $imagePath = $imageUpload['path'];
                        
                        // Resize image if needed
                        resizeImage($imagePath, $imagePath, 800, 600);
                    } else {
                        $errors[] = $imageUpload['message'];
                    }
                }
                
                if (isset($_FILES['manual']) && $_FILES['manual']['error'] === UPLOAD_ERR_OK) {
                    $manualUpload = uploadFile(
                        $_FILES['manual'],
                        getConfig('upload.allowed_types.document'),
                        getConfig('upload.paths.manuals'),
                        getConfig('upload.max_size')
                    );
                    
                    if ($manualUpload['success']) {
                        $manualPath = $manualUpload['path'];
                    } else {
                        $errors[] = $manualUpload['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Auto-generate code if empty
if (empty($formData['code'])) {
    $industry = $db->fetch("SELECT code FROM industries WHERE id = ?", [$formData['industry_id']]);
    $workshop = $db->fetch("SELECT code FROM workshops WHERE id = ?", [$formData['workshop_id']]);
    $line = !empty($formData['line_id']) ? $db->fetch("SELECT code FROM production_lines WHERE id = ?", [$formData['line_id']]) : null;
    $area = !empty($formData['area_id']) ? $db->fetch("SELECT code FROM areas WHERE id = ?", [$formData['area_id']]) : null;
    
    $formData['code'] = generateEquipmentCode(
        $industry['code'] ?? 'EQ',
        $workshop['code'] ?? '',
        $line['code'] ?? '',
        $area['code'] ?? ''
    );
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
                    logActivity('create', 'equipment', "Tạo thiết bị: {$formData['name']} ({$formData['code']})", getCurrentUser()['id']);
                    
                    // Create audit trail
                    createAuditTrail('equipment', $equipmentId, 'create', null, $formData);
                    
                    $db->commit();
                    
                    if ($action === 'save') {
                        $success = 'Tạo thiết bị thành công!';
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

// Load draft data if exists
$draftData = [];
if (empty($formData) && isset($_GET['load_draft'])) {
    $draftJson = CMMS.storage.get('equipment_draft');
    if ($draftJson) {
        $draftData = json_decode($draftJson, true);
        $formData = array_merge($formData, $draftData);
    }
}

require_once '../../includes/header.php';
?>

<style>
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

.file-upload-text {
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.file-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.file-preview img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 0.375rem;
}

.progress-container {
    margin-top: 1rem;
    display: none;
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

    <form id="equipmentForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate data-auto-save="equipment_form">
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
                                    <div class="invalid-feedback"></div>
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
                                        <option value="">-- Chọn ngành --</option>
                                        <?php foreach ($industries as $industry): ?>
                                            <option value="<?php echo $industry['id']; ?>" 
                                                    <?php echo ($formData['industry_id'] ?? '') == $industry['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($industry['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="industryId">Ngành sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="workshopId" name="workshop_id" required onchange="updateLines()">
                                        <option value="">-- Chọn xưởng --</option>
                                        <?php foreach ($workshops as $workshop): ?>
                                            <option value="<?php echo $workshop['id']; ?>" 
                                                    data-industry="<?php echo $workshop['industry_id']; ?>"
                                                    <?php echo ($formData['workshop_id'] ?? '') == $workshop['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($workshop['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="workshopId">Xưởng sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="lineId" name="line_id" onchange="updateAreas()">
                                        <option value="">-- Chọn line (tùy chọn) --</option>
                                        <?php foreach ($lines as $line): ?>
                                            <option value="<?php echo $line['id']; ?>" 
                                                    data-workshop="<?php echo $line['workshop_id']; ?>"
                                                    <?php echo ($formData['line_id'] ?? '') == $line['id'] ? 'selected' : ''; ?>>
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
                                        <option value="">-- Chọn khu vực (tùy chọn) --</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo $area['id']; ?>" 
                                                    data-workshop="<?php echo $area['workshop_id']; ?>"
                                                    <?php echo ($formData['area_id'] ?? '') == $area['id'] ? 'selected' : ''; ?>>
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
                                        <option value="">-- Chọn dòng máy --</option>
                                        <?php foreach ($machineTypes as $machineType): ?>
                                            <option value="<?php echo $machineType['id']; ?>"
                                                    <?php echo ($formData['machine_type_id'] ?? '') == $machineType['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($machineType['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="machineTypeId">Dòng máy *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="equipmentGroupId" name="equipment_group_id">
                                        <option value="">-- Chọn cụm thiết bị (tùy chọn) --</option>
                                        <?php foreach ($equipmentGroups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>" 
                                                    data-machine-type="<?php echo $group['machine_type_id']; ?>"
                                                    <?php echo ($formData['equipment_group_id'] ?? '') == $group['id'] ? 'selected' : ''; ?>>
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
                                <input type="text" class="form-control" id="locationDetails" name="location_details" 
                                       placeholder="Vị trí chi tiết" maxlength="200"
                                       value="<?php echo htmlspecialchars($formData['location_details'] ?? ''); ?>">
                                <label for="locationDetails">Vị trí chi tiết</label>
                                <div class="form-text">VD: Tầng 2, góc trái, gần cửa sổ...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management & Status Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-users"></i>
                        Quản lý & Trạng thái
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="ownerUserId" name="owner_user_id">
                                        <option value="">-- Chọn người quản lý chính --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"
                                                    <?php echo ($formData['owner_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if ($user['email']): ?>
                                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="ownerUserId">Người quản lý chính</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="backupOwnerUserId" name="backup_owner_user_id">
                                        <option value="">-- Chọn người quản lý phụ --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"
                                                    <?php echo ($formData['backup_owner_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if ($user['email']): ?>
                                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="backupOwnerUserId">Người quản lý phụ</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="criticality" name="criticality" required>
                                        <option value="Low" <?php echo ($formData['criticality'] ?? 'Medium') === 'Low' ? 'selected' : ''; ?>>Thấp</option>
                                        <option value="Medium" <?php echo ($formData['criticality'] ?? 'Medium') === 'Medium' ? 'selected' : ''; ?>>Trung bình</option>
                                        <option value="High" <?php echo ($formData['criticality'] ?? 'Medium') === 'High' ? 'selected' : ''; ?>>Cao</option>
                                        <option value="Critical" <?php echo ($formData['criticality'] ?? 'Medium') === 'Critical' ? 'selected' : ''; ?>>Nghiêm trọng</option>
                                    </select>
                                    <label for="criticality">Mức độ quan trọng *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($formData['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                                        <option value="maintenance" <?php echo ($formData['status'] ?? 'active') === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                                        <option value="broken" <?php echo ($formData['status'] ?? 'active') === 'broken' ? 'selected' : ''; ?>>Hỏng</option>
                                    </select>
                                    <label for="status">Trạng thái *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <small>Thiết bị mới thường ở trạng thái "Hoạt động"</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Details Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-cog"></i>
                        Thông số kỹ thuật & Bảo trì
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="installationDate" name="installation_date"
                                           value="<?php echo htmlspecialchars($formData['installation_date'] ?? ''); ?>">
                                    <label for="installationDate">Ngày lắp đặt</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="warrantyExpiry" name="warranty_expiry"
                                           value="<?php echo htmlspecialchars($formData['warranty_expiry'] ?? ''); ?>">
                                    <label for="warrantyExpiry">Ngày hết bảo hành</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="maintenanceFrequencyDays" name="maintenance_frequency_days" 
                                           placeholder="Chu kỳ bảo trì" min="1" max="365"
                                           value="<?php echo htmlspecialchars($formData['maintenance_frequency_days'] ?? ''); ?>">
                                    <label for="maintenanceFrequencyDays">Chu kỳ bảo trì (ngày)</label>
                                    <div class="form-text">VD: 30 ngày, 90 ngày...</div>
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
                        Tài liệu & Hình ảnh
                    </div>
                    <div class="form-section-body">
                        <div class="row g-4">
                            <div class="col-md-6">
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
                                        Chấp nhận: JPG, PNG, GIF, WEBP (tối đa <?php echo formatFileSize(getConfig('upload.max_size')); ?>)
                                    </small>
                                    <div id="imagePreview" class="file-preview d-none"></div>
                                    <div id="imageProgress" class="progress-container">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-file-pdf me-2"></i>Tài liệu hướng dẫn
                                </label>
                                <div class="file-upload-section" onclick="document.getElementById('manualFile').click()">
                                    <input type="file" id="manualFile" name="manual" accept=".pdf,.doc,.docx" class="d-none" onchange="handleFileSelect(this, 'manual')">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Click để chọn tài liệu</strong><br>
                                        hoặc kéo thả file vào đây
                                    </div>
                                    <small class="text-muted">
                                        Chấp nhận: PDF, DOC, DOCX (tối đa <?php echo formatFileSize(getConfig('upload.max_size')); ?>)
                                    </small>
                                    <div id="manualPreview" class="file-preview d-none"></div>
                                    <div id="manualProgress" class="progress-container">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 0%"></div>
                                        </div>
                                    </div>
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
                        <i class="fas fa-tips me-2"></i>Gợi ý
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

<script>
// Equipment form management
const EquipmentForm = {
    // Configuration
    config: {
        autoSaveInterval: 30000, // 30 seconds
        maxFileSize: <?php echo getConfig('upload.max_size'); ?>,
        allowedImageTypes: <?php echo json_encode(getConfig('upload.allowed_types.image')); ?>,
        allowedDocTypes: <?php echo json_encode(getConfig('upload.allowed_types.document')); ?>
    },
    
    // Form state
    state: {
        isDirty: false,
        autoSaveTimer: null,
        validationErrors: {}
    },
    
    // Initialize form
    init: function() {
        console.log('Initializing equipment form...');
        
        this.initializeEventListeners();
        this.initializeValidation();
        this.initializeDragAndDrop();
        this.initializeAutoSave();
        this.updateProgress();
        this.updatePreview();
        
        // Load dependent dropdowns on page load
        this.updateWorkshops();
        
        console.log('Equipment form initialized successfully');
    },
    
    // Initialize event listeners
    initializeEventListeners: function() {
        const form = document.getElementById('equipmentForm');
        
        // Form input listeners
        form.addEventListener('input', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
            
            // Real-time validation
            this.validateField(e.target);
        });
        
        form.addEventListener('change', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
        });
        
        // Character counters
        this.initializeCharacterCounters();
        
        // Prevent accidental navigation
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
                return e.returnValue;
            }
        });
    },
    
    // Initialize character counters
    initializeCharacterCounters: function() {
        const textareas = [
            { id: 'specifications', counterId: 'specificationsCounter', maxLength: 2000 },
            { id: 'notes', counterId: 'notesCounter', maxLength: 1000 }
        ];
        
        textareas.forEach(({ id, counterId, maxLength }) => {
            const textarea = document.getElementById(id);
            const counter = document.getElementById(counterId);
            
            if (textarea && counter) {
                const updateCounter = () => {
                    const length = textarea.value.length;
                    counter.textContent = length;
                    
                    counter.className = 'character-counter';
                    if (length > maxLength * 0.9) {
                        counter.classList.add('danger');
                    } else if (length > maxLength * 0.8) {
                        counter.classList.add('warning');
                    }
                };
                
                textarea.addEventListener('input', updateCounter);
                updateCounter(); // Initial update
            }
        });
    },
    
    // Update progress bar
    updateProgress: function() {
        const requiredFields = [
            'equipmentName', 'industryId', 'workshopId', 'machineTypeId', 'criticality', 'status'
        ];
        
        const filledFields = requiredFields.filter(id => {
            const field = document.getElementById(id);
            return field && field.value.trim() !== '';
        });
        
        const progress = Math.round((filledFields.length / requiredFields.length) * 100);
        
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        
        if (progressFill && progressPercent) {
            progressFill.style.width = progress + '%';
            progressPercent.textContent = progress;
        }
    },
// Update progress bar
    updateProgress: function() {
        const requiredFields = [
            'equipmentName', 'industryId', 'workshopId', 'machineTypeId', 'criticality', 'status'
        ];
        
        const filledFields = requiredFields.filter(id => {
            const field = document.getElementById(id);
            return field && field.value.trim() !== '';
        });
        
        const progress = Math.round((filledFields.length / requiredFields.length) * 100);
        
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        
        if (progressFill && progressPercent) {
            progressFill.style.width = progress + '%';
            progressPercent.textContent = progress;
        }
    },
    
    // Update preview panel
    updatePreview: function() {
        const fields = {
            'equipmentName': 'previewName',
            'equipmentCode': 'previewCode',
            'manufacturer': 'previewManufacturer',
            'model': 'previewModel'
        };
        
        Object.entries(fields).forEach(([inputId, previewId]) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            if (input && preview) {
                const value = input.value.trim();
                preview.textContent = value || (inputId === 'equipmentCode' ? 'Tự động tạo' : 'Chưa nhập');
                preview.classList.toggle('empty', !value);
            }
        });
        
        // Update select fields
        this.updateSelectPreview('industryId', 'previewIndustry');
        this.updateSelectPreview('workshopId', 'previewWorkshop');
        this.updateSelectPreview('machineTypeId', 'previewMachineType');
        this.updateSelectPreview('criticality', 'previewCriticality');
        this.updateSelectPreview('status', 'previewStatus');
        this.updateSelectPreview('ownerUserId', 'previewOwner');
    },
    
    updateSelectPreview: function(selectId, previewId) {
        const select = document.getElementById(selectId);
        const preview = document.getElementById(previewId);
        
        if (select && preview) {
            const selectedOption = select.options[select.selectedIndex];
            const value = selectedOption ? selectedOption.text : '';
            
            preview.textContent = value || 'Chưa chọn';
            preview.classList.toggle('empty', !value || select.selectedIndex === 0);
        }
    },
    
    // Initialize drag and drop
    initializeDragAndDrop: function() {
        const uploadSections = document.querySelectorAll('.file-upload-section');
        
        uploadSections.forEach(section => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                section.addEventListener(eventName, this.preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                section.addEventListener(eventName, () => {
                    section.classList.add('drag-over');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                section.addEventListener(eventName, () => {
                    section.classList.remove('drag-over');
                }, false);
            });
            
            section.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                const input = section.querySelector('input[type="file"]');
                
                if (files.length > 0 && input) {
                    input.files = files;
                    this.handleFileSelect(input, input.name);
                }
            }, false);
        });
    },
    
    preventDefaults: function(e) {
        e.preventDefault();
        e.stopPropagation();
    },
    
    // Initialize auto-save
    initializeAutoSave: function() {
        this.state.autoSaveTimer = setInterval(() => {
            if (this.state.isDirty) {
                this.saveDraft(true); // Silent auto-save
            }
        }, this.config.autoSaveInterval);
    },
    
    // Schedule auto-save
    scheduleAutoSave: function() {
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(() => {
            this.saveDraft(true);
        }, 5000); // Auto-save after 5 seconds of inactivity
    },
    
    // Form validation
    initializeValidation: function() {
        const form = document.getElementById('equipmentForm');
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (this.validateForm()) {
                this.submitForm();
            }
        });
    },
    
    validateField: function(field) {
        const fieldId = field.id;
        let isValid = true;
        let errorMessage = '';
        <?php
/**
 * Add New Equipment - modules/equipment/add.php
 * Form to create new equipment in the system
 */

$pageTitle = 'Thêm thiết bị mới';
$currentModule = 'equipment';
$moduleCSS = 'equipment';
$moduleJS = 'equipment';

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
                        getConfig('upload.allowed_types.image'),
                        getConfig('upload.paths.equipment_images'),
                        getConfig('upload.max_size')
                    );
                    
                    if ($imageUpload['success']) {
                        $imagePath = $imageUpload['path'];
                        
                        // Resize image if needed
                        resizeImage($imagePath, $imagePath, 800, 600);
                    } else {
                        $errors[] = $imageUpload['message'];
                    }
                }
                
                if (isset($_FILES['manual']) && $_FILES['manual']['error'] === UPLOAD_ERR_OK) {
                    $manualUpload = uploadFile(
                        $_FILES['manual'],
                        getConfig('upload.allowed_types.document'),
                        getConfig('upload.paths.manuals'),
                        getConfig('upload.max_size')
                    );
                    
                    if ($manualUpload['success']) {
                        $manualPath = $manualUpload['path'];
                    } else {
                        $errors[] = $manualUpload['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Auto-generate code if empty
                    if (empty($formData['code'])) {
                        $formData['code'] = generateEquipmentCode($formData);
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
                    logActivity('create', 'equipment', "Tạo thiết bị: {$formData['name']} ({$formData['code']})", getCurrentUser()['id']);
                    
                    // Create audit trail
                    createAuditTrail('equipment', $equipmentId, 'create', null, $formData);
                    
                    $db->commit();
                    
                    if ($action === 'save') {
                        $success = 'Tạo thiết bị thành công!';
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
            }
        }
    }
}

// Load options for dropdowns
$industries = $db->fetchAll("SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name");
$workshops = $db->fetchAll("SELECT id, name, code, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
$lines = $db->fetchAll("SELECT id, name, code, workshop_id FROM production_lines WHERE status = 'active' ORDER BY name");
$areas = $db->fetchAll("SELECT id, name, code, line_id FROM areas WHERE status = 'active' ORDER BY name");
$machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
$equipmentGroups = $db->fetchAll("SELECT id, name, machine_type_id FROM equipment_groups WHERE status = 'active' ORDER BY name");
$users = $db->fetchAll("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name");

// Load draft data if exists
$draftData = [];
if (empty($formData) && isset($_GET['load_draft'])) {
    $draftJson = CMMS.storage.get('equipment_draft');
    if ($draftJson) {
        $draftData = json_decode($draftJson, true);
        $formData = array_merge($formData, $draftData);
    }
}

require_once '../../includes/header.php';
?>

<style>
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

.file-upload-text {
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.file-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.file-preview img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 0.375rem;
}

.progress-container {
    margin-top: 1rem;
    display: none;
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

    <form id="equipmentForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate data-auto-save="equipment_form">
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
                                    <div class="invalid-feedback"></div>
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
                                        <option value="">-- Chọn ngành --</option>
                                        <?php foreach ($industries as $industry): ?>
                                            <option value="<?php echo $industry['id']; ?>" 
                                                    <?php echo ($formData['industry_id'] ?? '') == $industry['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($industry['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="industryId">Ngành sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="workshopId" name="workshop_id" required onchange="updateLines()">
                                        <option value="">-- Chọn xưởng --</option>
                                        <?php foreach ($workshops as $workshop): ?>
                                            <option value="<?php echo $workshop['id']; ?>" 
                                                    data-industry="<?php echo $workshop['industry_id']; ?>"
                                                    <?php echo ($formData['workshop_id'] ?? '') == $workshop['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($workshop['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="workshopId">Xưởng sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="lineId" name="line_id" onchange="updateAreas()">
                                        <option value="">-- Chọn line (tùy chọn) --</option>
                                        <?php foreach ($lines as $line): ?>
                                            <option value="<?php echo $line['id']; ?>" 
                                                    data-workshop="<?php echo $line['workshop_id']; ?>"
                                                    <?php echo ($formData['line_id'] ?? '') == $line['id'] ? 'selected' : ''; ?>>
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
                                        <option value="">-- Chọn khu vực (tùy chọn) --</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo $area['id']; ?>" 
                                                    data-line="<?php echo $area['line_id']; ?>"
                                                    <?php echo ($formData['area_id'] ?? '') == $area['id'] ? 'selected' : ''; ?>>
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
                                        <option value="">-- Chọn dòng máy --</option>
                                        <?php foreach ($machineTypes as $machineType): ?>
                                            <option value="<?php echo $machineType['id']; ?>"
                                                    <?php echo ($formData['machine_type_id'] ?? '') == $machineType['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($machineType['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="machineTypeId">Dòng máy *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="equipmentGroupId" name="equipment_group_id">
                                        <option value="">-- Chọn cụm thiết bị (tùy chọn) --</option>
                                        <?php foreach ($equipmentGroups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>" 
                                                    data-machine-type="<?php echo $group['machine_type_id']; ?>"
                                                    <?php echo ($formData['equipment_group_id'] ?? '') == $group['id'] ? 'selected' : ''; ?>>
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
                                <input type="text" class="form-control" id="locationDetails" name="location_details" 
                                       placeholder="Vị trí chi tiết" maxlength="200"
                                       value="<?php echo htmlspecialchars($formData['location_details'] ?? ''); ?>">
                                <label for="locationDetails">Vị trí chi tiết</label>
                                <div class="form-text">VD: Tầng 2, góc trái, gần cửa sổ...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management & Status Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-users"></i>
                        Quản lý & Trạng thái
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="ownerUserId" name="owner_user_id">
                                        <option value="">-- Chọn người quản lý chính --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"
                                                    <?php echo ($formData['owner_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if ($user['email']): ?>
                                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="ownerUserId">Người quản lý chính</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="backupOwnerUserId" name="backup_owner_user_id">
                                        <option value="">-- Chọn người quản lý phụ --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"
                                                    <?php echo ($formData['backup_owner_user_id'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if ($user['email']): ?>
                                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="backupOwnerUserId">Người quản lý phụ</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="criticality" name="criticality" required>
                                        <option value="Low" <?php echo ($formData['criticality'] ?? 'Medium') === 'Low' ? 'selected' : ''; ?>>Thấp</option>
                                        <option value="Medium" <?php echo ($formData['criticality'] ?? 'Medium') === 'Medium' ? 'selected' : ''; ?>>Trung bình</option>
                                        <option value="High" <?php echo ($formData['criticality'] ?? 'Medium') === 'High' ? 'selected' : ''; ?>>Cao</option>
                                        <option value="Critical" <?php echo ($formData['criticality'] ?? 'Medium') === 'Critical' ? 'selected' : ''; ?>>Nghiêm trọng</option>
                                    </select>
                                    <label for="criticality">Mức độ quan trọng *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                        <option value="inactive" <?php echo ($formData['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                                        <option value="maintenance" <?php echo ($formData['status'] ?? 'active') === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                                        <option value="broken" <?php echo ($formData['status'] ?? 'active') === 'broken' ? 'selected' : ''; ?>>Hỏng</option>
                                    </select>
                                    <label for="status">Trạng thái *</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <small>Thiết bị mới thường ở trạng thái "Hoạt động"</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Details Section -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-cog"></i>
                        Thông số kỹ thuật & Bảo trì
                    </div>
                    <div class="form-section-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="installationDate" name="installation_date"
                                           value="<?php echo htmlspecialchars($formData['installation_date'] ?? ''); ?>">
                                    <label for="installationDate">Ngày lắp đặt</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="warrantyExpiry" name="warranty_expiry"
                                           value="<?php echo htmlspecialchars($formData['warranty_expiry'] ?? ''); ?>">
                                    <label for="warrantyExpiry">Ngày hết bảo hành</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="maintenanceFrequencyDays" name="maintenance_frequency_days" 
                                           placeholder="Chu kỳ bảo trì" min="1" max="365"
                                           value="<?php echo htmlspecialchars($formData['maintenance_frequency_days'] ?? ''); ?>">
                                    <label for="maintenanceFrequencyDays">Chu kỳ bảo trì (ngày)</label>
                                    <div class="form-text">VD: 30 ngày, 90 ngày...</div>
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
                        Tài liệu & Hình ảnh
                    </div>
                    <div class="form-section-body">
                        <div class="row g-4">
                            <div class="col-md-6">
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
                                        Chấp nhận: JPG, PNG, GIF, WEBP (tối đa <?php echo formatFileSize(getConfig('upload.max_size')); ?>)
                                    </small>
                                    <div id="imagePreview" class="file-preview d-none"></div>
                                    <div id="imageProgress" class="progress-container">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-file-pdf me-2"></i>Tài liệu hướng dẫn
                                </label>
                                <div class="file-upload-section" onclick="document.getElementById('manualFile').click()">
                                    <input type="file" id="manualFile" name="manual" accept=".pdf,.doc,.docx" class="d-none" onchange="handleFileSelect(this, 'manual')">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Click để chọn tài liệu</strong><br>
                                        hoặc kéo thả file vào đây
                                    </div>
                                    <small class="text-muted">
                                        Chấp nhận: PDF, DOC, DOCX (tối đa <?php echo formatFileSize(getConfig('upload.max_size')); ?>)
                                    </small>
                                    <div id="manualPreview" class="file-preview d-none"></div>
                                    <div id="manualProgress" class="progress-container">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 0%"></div>
                                        </div>
                                    </div>
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
                        <i class="fas fa-tips me-2"></i>Gợi ý
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

<script>
// Equipment form management
const EquipmentForm = {
    // Configuration
    config: {
        autoSaveInterval: 30000, // 30 seconds
        maxFileSize: <?php echo getConfig('upload.max_size'); ?>,
        allowedImageTypes: <?php echo json_encode(getConfig('upload.allowed_types.image')); ?>,
        allowedDocTypes: <?php echo json_encode(getConfig('upload.allowed_types.document')); ?>
    },
    
    // Form state
    state: {
        isDirty: false,
        autoSaveTimer: null,
        validationErrors: {}
    },
    
    // Initialize form
    init: function() {
        console.log('Initializing equipment form...');
        
        this.initializeEventListeners();
        this.initializeValidation();
        this.initializeDragAndDrop();
        this.initializeAutoSave();
        this.updateProgress();
        this.updatePreview();
        
        // Load dependent dropdowns on page load
        this.updateWorkshops();
        
        console.log('Equipment form initialized successfully');
    },
    
    // Initialize event listeners
    initializeEventListeners: function() {
        const form = document.getElementById('equipmentForm');
        
        // Form input listeners
        form.addEventListener('input', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
            
            // Real-time validation
            this.validateField(e.target);
        });
        
        form.addEventListener('change', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
        });
        
        // Character counters
        this.initializeCharacterCounters();
        
        // Prevent accidental navigation
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
                return e.returnValue;
            }
        });
    },
    
    // Initialize character counters
    initializeCharacterCounters: function() {
        const textareas = [
            { id: 'specifications', counterId: 'specificationsCounter', maxLength: 2000 },
            { id: 'notes', counterId: 'notesCounter', maxLength: 1000 }
        ];
        
        textareas.forEach(({ id, counterId, maxLength }) => {
            const textarea = document.getElementById(id);
            const counter = document.getElementById(counterId);
            
            if (textarea && counter) {
                const updateCounter = () => {
                    const length = textarea.value.length;
                    counter.textContent = length;
                    
                    counter.className = 'character-counter';
                    if (length > maxLength * 0.9) {
                        counter.classList.add('danger');
                    } else if (length > maxLength * 0.8) {
                        counter.classList.add('warning');
                    }
                };
                
                textarea.addEventListener('input', updateCounter);
                updateCounter(); // Initial update
            }
        });
    },
    
    // Update progress bar
    updateProgress: function() {
        const requiredFields = [
            'equipmentName', 'industryId', 'workshopId', 'machineTypeId', 'criticality', 'status'
        ];
        
        const filledFields = requiredFields.filter(id => {
            const field = document.getElementById(id);
            return field && field.value.trim() !== '';
        });
        
        const progress = Math.round((filledFields.length / requiredFields.length) * 100);
        
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        
        if (progressFill && progressPercent) {
            progressFill.style.width = progress + '%';
            progressPercent.textContent = progress;
        }
    },
    validateField: function(field) {
        const fieldId = field.id;
        let isValid = true;
        let errorMessage = '';
        
        // Clear previous validation
        field.classList.remove('is-invalid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = '';
        
        // Validation rules
        switch (fieldId) {
            case 'equipmentName':
                if (!field.value.trim()) {
                    isValid = false;
                    errorMessage = 'Tên thiết bị không được trống';
                } else if (field.value.length < 2) {
                    isValid = false;
                    errorMessage = 'Tên thiết bị phải có ít nhất 2 ký tự';
                }
                break;
                
            case 'equipmentCode':
                if (field.value && !/^[A-Z0-9_-]+$/.test(field.value)) {
                    isValid = false;
                    errorMessage = 'Mã thiết bị chỉ được chứa chữ hoa, số, dấu gạch ngang và gạch dưới';
                }
                break;
                
            case 'industryId':
            case 'workshopId':
            case 'machineTypeId':
                if (!field.value) {
                    isValid = false;
                    errorMessage = 'Vui lòng chọn một tùy chọn';
                }
                break;
                
            case 'manufactureYear':
                if (field.value) {
                    const year = parseInt(field.value);
                    const currentYear = new Date().getFullYear();
                    if (year < 1900 || year > currentYear + 1) {
                        isValid = false;
                        errorMessage = `Năm sản xuất phải từ 1900 đến ${currentYear + 1}`;
                    }
                }
                break;
                
            case 'maintenanceFrequencyDays':
                if (field.value && (parseInt(field.value) < 1 || parseInt(field.value) > 365)) {
                    isValid = false;
                    errorMessage = 'Chu kỳ bảo trì phải từ 1 đến 365 ngày';
                }
                break;
        }
        
        // Show validation result
        if (!isValid) {
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = errorMessage;
            this.state.validationErrors[fieldId] = errorMessage;
        } else {
            delete this.state.validationErrors[fieldId];
        }
        
        return isValid;
    },
    
    validateForm: function() {
        const form = document.getElementById('equipmentForm');
        const inputs = form.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Check for any existing validation errors
        if (Object.keys(this.state.validationErrors).length > 0) {
            isValid = false;
        }
        
        if (!isValid) {
            CMMS.showToast('Vui lòng kiểm tra và sửa các lỗi trong form', 'error');
            
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return isValid;
    }
};

// Global functions for form actions
function saveEquipment() {
    document.getElementById('formAction').value = 'save';
    
    if (EquipmentForm.validateForm()) {
        CMMS.showLoading();
        document.getElementById('equipmentForm').submit();
    }
}

function saveDraft(silent = false) {
    document.getElementById('formAction').value = 'draft';
    
    // Save to localStorage as backup
    const formData = new FormData(document.getElementById('equipmentForm'));
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
        draftData[key] = value;
    }
    
    localStorage.setItem('equipment_draft', JSON.stringify(draftData));
    
    if (!silent) {
        CMMS.showLoading();
        document.getElementById('equipmentForm').submit();
    } else {
        EquipmentForm.state.isDirty = false;
        console.log('Auto-saved draft to localStorage');
    }
}

function previewEquipment() {
    const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
    
    // Generate preview content
    const previewContent = generateFullPreview();
    document.getElementById('fullPreviewContent').innerHTML = previewContent;
    
    modal.show();
}

function resetForm() {
    if (confirm('Bạn có chắc chắn muốn reset form? Tất cả dữ liệu đã nhập sẽ bị mất.')) {
        document.getElementById('equipmentForm').reset();
        EquipmentForm.state.isDirty = false;
        EquipmentForm.state.validationErrors = {};
        
        // Clear validation states
        const form = document.getElementById('equipmentForm');
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Clear file previews
        document.querySelectorAll('.file-preview').forEach(preview => {
            preview.classList.add('d-none');
            preview.innerHTML = '';
        });
        
        // Update UI
        EquipmentForm.updateProgress();
        EquipmentForm.updatePreview();
        updateWorkshops();
        
        // Clear localStorage
        localStorage.removeItem('equipment_draft');
        
        CMMS.showToast('Form đã được reset', 'info');
    }
}

// Dependent dropdown functions
function updateWorkshops() {
    const industrySelect = document.getElementById('industryId');
    const workshopSelect = document.getElementById('workshopId');
    
    if (!industrySelect || !workshopSelect) return;
    
    const selectedIndustryId = industrySelect.value;
    const workshopOptions = workshopSelect.querySelectorAll('option[data-industry]');
    
    // Reset dependent selects
    workshopSelect.value = '';
    updateLines();
    
    // Show/hide options
    workshopOptions.forEach(option => {
        const industryId = option.getAttribute('data-industry');
        option.style.display = (!selectedIndustryId || industryId === selectedIndustryId) ? '' : 'none';
    });
    
    EquipmentForm.updatePreview();
}

function updateLines() {
    const workshopSelect = document.getElementById('workshopId');
    const lineSelect = document.getElementById('lineId');
    
    if (!workshopSelect || !lineSelect) return;
    
    const selectedWorkshopId = workshopSelect.value;
    const lineOptions = lineSelect.querySelectorAll('option[data-workshop]');
    
    // Reset dependent selects
    lineSelect.value = '';
    updateAreas();
    
    // Show/hide options
    lineOptions.forEach(option => {
        const workshopId = option.getAttribute('data-workshop');
        option.style.display = (!selectedWorkshopId || workshopId === selectedWorkshopId) ? '' : 'none';
    });
    
    EquipmentForm.updatePreview();
}

function updateAreas() {
    const workshopSelect = document.getElementById('workshopId');
    const areaSelect = document.getElementById('areaId');
    
    if (!workshopSelect || !areaSelect) return;
    
    const selectedWorkshopId = workshopSelect.value;
    const areaOptions = areaSelect.querySelectorAll('option[data-workshop]');
    
    // Reset area select
    areaSelect.value = '';
    
    // Show/hide options
    areaOptions.forEach(option => {
        const workshopId = option.getAttribute('data-workshop');
        option.style.display = (!selectedWorkshopId || workshopId === selectedWorkshopId) ? '' : 'none';
    });
    
    EquipmentForm.updatePreview();
}

function updateEquipmentGroups() {
    const machineTypeSelect = document.getElementById('machineTypeId');
    const equipmentGroupSelect = document.getElementById('equipmentGroupId');
    
    if (!machineTypeSelect || !equipmentGroupSelect) return;
    
    const selectedMachineTypeId = machineTypeSelect.value;
    const groupOptions = equipmentGroupSelect.querySelectorAll('option[data-machine-type]');
    
    // Reset group select
    equipmentGroupSelect.value = '';
    
    // Show/hide options
    groupOptions.forEach(option => {
        const machineTypeId = option.getAttribute('data-machine-type');
        option.style.display = (!selectedMachineTypeId || machineTypeId === selectedMachineTypeId) ? '' : 'none';
    });
}

// File handling functions
function handleFileSelect(input, type) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file
    if (!validateFile(file, type)) {
        input.value = '';
        return;
    }
    
    // Show preview
    showFilePreview(file, type);
    
    EquipmentForm.state.isDirty = true;
}

function validateFile(file, type) {
    const maxSize = EquipmentForm.config.maxFileSize;
    const allowedTypes = type === 'image' ? 
        EquipmentForm.config.allowedImageTypes : 
        EquipmentForm.config.allowedDocTypes;
    
    // Check file size
    if (file.size > maxSize) {
        CMMS.showToast(`File quá lớn. Kích thước tối đa: ${CMMS.formatFileSize(maxSize)}`, 'error');
        return false;
    }
    
    // Check file type
    const extension = file.name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(extension)) {
        CMMS.showToast(`Loại file không được phép. Chấp nhận: ${allowedTypes.join(', ')}`, 'error');
        return false;
    }
    
    return true;
}

function showFilePreview(file, type) {
    const previewId = type + 'Preview';
    const preview = document.getElementById(previewId);
    
    if (!preview) return;
    
    preview.classList.remove('d-none');
    
    if (type === 'image') {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 0.375rem;">
                <div class="mt-2">
                    <small class="text-muted">${file.name} (${CMMS.formatFileSize(file.size)})</small>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFilePreview('${type}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-file-pdf text-danger me-2" style="font-size: 2rem;"></i>
                <div>
                    <div class="fw-semibold">${file.name}</div>
                    <small class="text-muted">${CMMS.formatFileSize(file.size)}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearFilePreview('${type}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }
}

function clearFilePreview(type) {
    const input = document.getElementById(type + 'File');
    const preview = document.getElementById(type + 'Preview');
    
    if (input) input.value = '';
    if (preview) {
        preview.classList.add('d-none');
        preview.innerHTML = '';
    }
    
    EquipmentForm.state.isDirty = true;
}

// Generate full preview content
function generateFullPreview() {
    const form = document.getElementById('equipmentForm');
    const formData = new FormData(form);
    
    return `
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-3">Thông tin thiết bị</h5>
                <table class="table table-bordered">
                    <tr><th width="30%">Tên thiết bị</th><td>${formData.get('name') || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    <tr><th>Mã thiết bị</th><td>${formData.get('code') || '<em class="text-muted">Tự động tạo</em>'}</td></tr>
                    <tr><th>Nhà sản xuất</th><td>${formData.get('manufacturer') || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    <tr><th>Model</th><td>${formData.get('model') || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    <tr><th>Số seri</th><td>${formData.get('serial_number') || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    <tr><th>Năm sản xuất</th><td>${formData.get('manufacture_year') || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                </table>
                
                <h6 class="mt-4 mb-3">Vị trí & Phân loại</h6>
                <table class="table table-bordered">
                    <tr><th width="30%">Ngành</th><td>${getSelectedText('industryId')}</td></tr>
                    <tr><th>Xưởng</th><td>${getSelectedText('workshopId')}</td></tr>
                    <tr><th>Line sản xuất</th><td>${getSelectedText('lineId')}</td></tr>
                    <tr><th>Khu vực</th><td>${getSelectedText('areaId')}</td></tr>
                    <tr><th>Dòng máy</th><td>${getSelectedText('machineTypeId')}</td></tr>
                    <tr><th>Cụm thiết bị</th><td>${getSelectedText('equipmentGroupId')}</td></tr>
                </table>
                
                <h6 class="mt-4 mb-3">Quản lý & Trạng thái</h6>
                <table class="table table-bordered">
                    <tr><th width="30%">Người quản lý chính</th><td>${getSelectedText('ownerUserId')}</td></tr>
                    <tr><th>Người quản lý phụ</th><td>${getSelectedText('backupOwnerUserId')}</td></tr>
                    <tr><th>Mức độ quan trọng</th><td><span class="badge bg-info">${getSelectedText('criticality')}</span></td></tr>
                    <tr><th>Trạng thái</th><td><span class="badge bg-success">${getSelectedText('status')}</span></td></tr>
                </table>
                
                ${formData.get('specifications') ? `
                <h6 class="mt-4 mb-3">Thông số kỹ thuật</h6>
                <div class="p-3 bg-light rounded">${formData.get('specifications')}</div>
                ` : ''}
                
                ${formData.get('notes') ? `
                <h6 class="mt-4 mb-3">Ghi chú</h6>
                <div class="p-3 bg-light rounded">${formData.get('notes')}</div>
                ` : ''}
            </div>
            <div class="col-md-4">
                <h6 class="mb-3">Hình ảnh & Tài liệu</h6>
                <div id="previewFiles">
                    ${generateFilePreview()}
                </div>
            </div>
        </div>
    `;
}

function getSelectedText(selectId) {
    const select = document.getElementById(selectId);
    if (!select || select.selectedIndex === 0) {
        return '<em class="text-muted">Chưa chọn</em>';
    }
    return select.options[select.selectedIndex].text;
}

function generateFilePreview() {
    let html = '';
    
    const imageInput = document.getElementById('imageFile');
    const manualInput = document.getElementById('manualFile');
    
    if (imageInput.files.length > 0) {
        html += `<div class="mb-3"><strong>Hình ảnh:</strong> ${imageInput.files[0].name}</div>`;
    }
    
    if (manualInput.files.length > 0) {
        html += `<div class="mb-3"><strong>Tài liệu:</strong> ${manualInput.files[0].name}</div>`;
    }
    
    if (!html) {
        html = '<em class="text-muted">Chưa có file đính kèm</em>';
    }
    
    return html;
}

// Initialize form when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    EquipmentForm.init();
    
    // Load draft if exists
    const savedDraft = localStorage.getItem('equipment_draft');
    if (savedDraft && !<?php echo !empty($formData) ? 'true' : 'false'; ?>) {
        try {
            const draftData = JSON.parse(savedDraft);
            
            // Populate form fields
            Object.entries(draftData).forEach(([key, value]) => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = value;
                }
            });
            
            EquipmentForm.updateProgress();
            EquipmentForm.updatePreview();
            updateWorkshops();
            
            CMMS.showToast('Đã tải dữ liệu nháp từ lần trước', 'info');
        } catch (error) {
            console.error('Error loading draft:', error);
        }
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (EquipmentForm.state.autoSaveTimer) {
        clearInterval(EquipmentForm.state.autoSaveTimer);
    }
});
</script>

<?php
/**
 * PHP Validation Functions
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
            // Check uniqueness
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



require_once '../../includes/footer.php';
?>    