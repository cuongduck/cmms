<?php
/**
 * Add New Equipment - modules/equipment/add.php
 * Form to create new equipment in the system
 * PART 1: PHP Logic & Data Processing
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
                        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                        'uploads/equipment/images/',
                        5 * 1024 * 1024 // 5MB
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
                        ['pdf', 'doc', 'docx'],
                        'uploads/equipment/manuals/',
                        10 * 1024 * 1024 // 10MB
                    );
                    
                    if ($manualUpload['success']) {
                        $manualPath = $imageUpload['path'];
                    } else {
                        $errors[] = $manualUpload['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Auto-generate code if empty
                    if (empty($formData['code'])) {
                        // Fetch codes
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
                        $formData['code'] = generateEquipmentCode($industry['code'] ?? '', $workshop['code'] ?? '', $lineCode, $areaCode);
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

require_once '../../includes/header.php';
?>

<!-- PART 1 END -->
<!-- PART 2: CSS Styles & HTML Structure -->

<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/equipment.css">
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
                                        <option value="">Chọn ngành</option>
                                        <?php echo buildSelectOptions($industries, 'id', 'name', $formData['industry_id'] ?? null); ?>
                                    </select>
                                    <label for="industryId">Ngành sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="workshopId" name="workshop_id" required onchange="updateLines()">
                                        <option value="">Chọn xưởng</option>
                                        <?php echo buildSelectOptions($workshops, 'id', 'name', $formData['workshop_id'] ?? null); ?>
                                    </select>
                                    <label for="workshopId">Xưởng sản xuất *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="form-floating">
            <select class="form-select" id="lineId" name="line_id">
                <option value="">Chọn line sản xuất</option>
                <?php echo buildSelectOptions($lines, 'id', 'name', $formData['line_id'] ?? null, ['workshop_id' => 'data-workshop']); ?>
            </select>
            <label for="lineId">Line sản xuất</label>
            <div class="invalid-feedback"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-floating">
            <select class="form-select" id="areaId" name="area_id">
                <option value="">Chọn khu vực</option>
                <?php echo buildSelectOptions($areas, 'id', 'name', $formData['area_id'] ?? null, ['workshop_id' => 'data-workshop']); ?>  <!-- SỬA: data-workshop thay vì data-line -->
            </select>
            <label for="areaId">Khu vực</label>
            <div class="invalid-feedback"></div>
        </div>
    </div>
</div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="machineTypeId" name="machine_type_id" required onchange="updateEquipmentGroups()">
                                        <option value="">Chọn dòng máy</option>
                                        <?php echo buildSelectOptions($machineTypes, 'id', 'name', $formData['machine_type_id'] ?? null); ?>
                                    </select>
                                    <label for="machineTypeId">Dòng máy *</label>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="equipmentGroupId" name="equipment_group_id">
                                        <option value="">Chọn cụm thiết bị</option>
                                        <?php echo buildSelectOptions($equipmentGroups, 'id', 'name', $formData['equipment_group_id'] ?? null); ?>
                                    </select>
                                    <label for="equipmentGroupId">Cụm thiết bị</label>
                                    <div class="invalid-feedback"></div>
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
                                        <?php echo buildSelectOptions($users, 'id', 'full_name', $formData['owner_user_id'] ?? null); ?>
                                    </select>
                                    <label for="ownerUserId">Người quản lý chính</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="backupOwnerUserId" name="backup_owner_user_id">
                                        <option value="">Chọn người quản lý phụ</option>
                                        <?php echo buildSelectOptions($users, 'id', 'full_name', $formData['backup_owner_user_id'] ?? null); ?>
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
                                        Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB)
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
                                        Chấp nhận: PDF, DOC, DOCX (tối đa 10MB)
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

<!-- PART 4 END -->
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
<script src="<?php echo APP_URL; ?>/assets/js/equipment-add.js"></script>

<?php
/**
 * PHP Helper Functions for Equipment Add
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
    
    // Validate optional references
    if (!empty($data['line_id'])) {
        $sql = "SELECT id FROM production_lines WHERE id = ? AND workshop_id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['line_id'], $data['workshop_id']])) {
            $errors[] = 'Line sản xuất không hợp lệ';
        }
    }
    
    if (!empty($data['area_id'])) {
        $sql = "SELECT id FROM areas WHERE id = ? AND workshop_id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['area_id'], $data['workshop_id']])) {
            $errors[] = 'Khu vực không hợp lệ';
        }
    }
    
    if (!empty($data['equipment_group_id'])) {
        $sql = "SELECT id FROM equipment_groups WHERE id = ? AND machine_type_id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['equipment_group_id'], $data['machine_type_id']])) {
            $errors[] = 'Cụm thiết bị không hợp lệ';
        }
    }
    
    if (!empty($data['owner_user_id'])) {
        $sql = "SELECT id FROM users WHERE id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['owner_user_id']])) {
            $errors[] = 'Người quản lý chính không hợp lệ';
        }
    }
    
    if (!empty($data['backup_owner_user_id'])) {
        $sql = "SELECT id FROM users WHERE id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['backup_owner_user_id']])) {
            $errors[] = 'Người quản lý phụ không hợp lệ';
        }
    }
    
    return $errors;
}

if (!function_exists('logActivity')) {
    function logActivity($action, $module, $description, $userId) {
        global $db;
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, module, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $params = [
                $userId, $action, $module, $description,
                $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            $db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}

if (!function_exists('createAuditTrail')) {
    function createAuditTrail($table, $recordId, $action, $oldData = null, $newData = null) {
        global $db;
        try {
            $sql = "INSERT INTO audit_trails (table_name, record_id, action, old_data, new_data, user_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $params = [
                $table, $recordId, $action,
                $oldData ? json_encode($oldData) : null,
                $newData ? json_encode($newData) : null,
                getCurrentUser()['id']
            ];
            $db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Failed to create audit trail: " . $e->getMessage());
        }
    }
}

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}

if (!function_exists('resizeImage')) {
    function resizeImage($source, $destination, $maxWidth = 800, $maxHeight = 600, $quality = 85) {
        $imageInfo = getimagesize($source);
        if (!$imageInfo) return false;
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio < 1) {
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Create image resource
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) return false;
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($newImage, $destination, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($newImage, $destination, 9);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($newImage, $destination);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return $result;
    }
}

require_once '../../includes/footer.php';
?>

<!-- PART 5 END -->
<!-- FILE COMPLETE -->