<?php
/**
 * Edit Equipment - modules/equipment/edit.php
 * Form to edit existing equipment in the system
 * PART 1: PHP Logic & Data Processing
 */

$pageTitle = 'Chỉnh sửa thiết bị';
$currentModule = 'equipment';
$moduleCSS = 'equipment';
$moduleJS = 'equipment';

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'edit');

$db = Database::getInstance();

// Lấy ID thiết bị từ URL
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipment_id <= 0) {
    header('Location: index.php?error=invalid_id');
    exit();
}

$errors = [];
$success = '';
$formData = [];

// Lấy thông tin thiết bị hiện tại
try {
    $sql = "SELECT e.*, 
                   i.name as industry_name,
                   w.name as workshop_name,
                   pl.name as line_name,
                   a.name as area_name,
                   mt.name as machine_type_name,
                   eg.name as equipment_group_name,
                   u1.full_name as owner_name,
                   u2.full_name as backup_owner_name
            FROM equipment e
            LEFT JOIN industries i ON e.industry_id = i.id
            LEFT JOIN workshops w ON e.workshop_id = w.id
            LEFT JOIN production_lines pl ON e.line_id = pl.id
            LEFT JOIN areas a ON e.area_id = a.id
            LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
            LEFT JOIN equipment_groups eg ON e.equipment_group_id = eg.id
            LEFT JOIN users u1 ON e.owner_user_id = u1.id
            LEFT JOIN users u2 ON e.backup_owner_user_id = u2.id
            WHERE e.id = ?";
    
    $equipment = $db->fetch($sql, [$equipment_id]);
    
    if (!$equipment) {
        header('Location: index.php?error=equipment_not_found');
        exit();
    }
    
    // Populate form data with current values
    $formData = $equipment;
    
} catch (Exception $e) {
    $errors[] = "Lỗi khi lấy thông tin thiết bị: " . $e->getMessage();
}
?>
<?php
// Xử lý form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'save' || $action === 'draft') {
        // Process form data
        $updateData = [
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
            $errors = validateEquipmentUpdate($updateData, $equipment_id);
        }
        
        // If no errors, proceed with update
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Handle file uploads
                $imagePath = $equipment['image_path'];
                $manualPath = $equipment['manual_path'];
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imageUpload = uploadFile(
                        $_FILES['image'],
                        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                        'uploads/equipment/images/',
                        5 * 1024 * 1024 // 5MB
                    );
                    
                    if ($imageUpload['success']) {
                        // Delete old image
                        if ($imagePath && file_exists($imagePath)) {
                            unlink($imagePath);
                        }
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
                        // Delete old manual
                        if ($manualPath && file_exists($manualPath)) {
                            unlink($manualPath);
                        }
                        $manualPath = $manualUpload['path'];
                    } else {
                        $errors[] = $manualUpload['message'];
                    }
                }
                
                if (empty($errors)) {
                    // Update equipment
                    $sql = "UPDATE equipment SET 
                           code = ?, name = ?, industry_id = ?, workshop_id = ?, line_id = ?, area_id = ?,
                           machine_type_id = ?, equipment_group_id = ?, owner_user_id = ?, backup_owner_user_id = ?,
                           manufacturer = ?, model = ?, serial_number = ?, manufacture_year = ?,
                           installation_date = ?, warranty_expiry = ?, maintenance_frequency_days = ?, 
                           maintenance_frequency_type = ?, specifications = ?, location_details = ?, 
                           criticality = ?, status = ?, image_path = ?, manual_path = ?, notes = ?, 
                           updated_at = NOW()
                         WHERE id = ?";
                    
                    $params = [
                        $updateData['code'], $updateData['name'], $updateData['industry_id'], $updateData['workshop_id'],
                        $updateData['line_id'], $updateData['area_id'], $updateData['machine_type_id'], $updateData['equipment_group_id'],
                        $updateData['owner_user_id'], $updateData['backup_owner_user_id'], $updateData['manufacturer'],
                        $updateData['model'], $updateData['serial_number'], $updateData['manufacture_year'],
                        $updateData['installation_date'], $updateData['warranty_expiry'], $updateData['maintenance_frequency_days'],
                        $updateData['maintenance_frequency_type'], $updateData['specifications'], $updateData['location_details'],
                        $updateData['criticality'], $updateData['status'], $imagePath, $manualPath, $updateData['notes'],
                        $equipment_id
                    ];
                    
                    $db->execute($sql, $params);
                    
                    // Log activity
                    logActivity('update', 'equipment', "Cập nhật thiết bị: {$updateData['name']} ({$updateData['code']})", getCurrentUser()['id']);
                    
                    // Create audit trail
                    createAuditTrail('equipment', $equipment_id, 'update', $equipment, $updateData);
                    
                    $db->commit();
                    
                    $success = 'Cập nhật thiết bị thành công!';
                    
                    // Update form data for display
                    $formData = array_merge($formData, $updateData);
                    
                    if ($action === 'save') {
                        header('Refresh: 2; url=view.php?id=' . $equipment_id);
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = 'Lỗi khi cập nhật thiết bị: ' . $e->getMessage();
            }
        }
    }
}
?>
<?php
// Lấy danh sách cho dropdown
$industries = $db->fetchAll("SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name");
$workshops = $db->fetchAll("SELECT id, name, code, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
$lines = $db->fetchAll("SELECT id, name, code, workshop_id FROM production_lines WHERE status = 'active' ORDER BY name");
$areas = $db->fetchAll("SELECT id, name, code, workshop_id FROM areas WHERE status = 'active' ORDER BY name");
$machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
$equipmentGroups = $db->fetchAll("SELECT id, name, machine_type_id FROM equipment_groups WHERE status = 'active' ORDER BY name");
$users = $db->fetchAll("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name");

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chỉnh sửa thiết bị']
];

$pageActions = '
<div class="btn-group">
    <button type="button" class="btn btn-success" onclick="saveEquipment()">
        <i class="fas fa-save me-1"></i> Cập nhật thiết bị
    </button>
    <button type="button" class="btn btn-outline-info" onclick="saveDraft()">
        <i class="fas fa-bookmark me-1"></i> Lưu nháp
    </button>
    <a href="view.php?id=' . $equipment_id . '" class="btn btn-outline-primary">
        <i class="fas fa-eye me-1"></i> Xem chi tiết
    </a>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Quay lại
    </a>
</div>';

require_once '../../includes/header.php';

// Helper functions for validation
function validateEquipmentUpdate($data, $equipmentId) {
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
    
    // Code validation (if provided)
    if (!empty($data['code'])) {
        if (!preg_match('/^[A-Z0-9_-]+$/', $data['code'])) {
            $errors[] = 'Mã thiết bị chỉ được chứa chữ hoa, số, dấu gạch ngang và gạch dưới';
        } elseif (strlen($data['code']) > 30) {
            $errors[] = 'Mã thiết bị không được quá 30 ký tự';
        } else {
            // Check uniqueness (exclude current equipment)
            $sql = "SELECT id FROM equipment WHERE code = ? AND id != ?";
            if ($db->fetch($sql, [$data['code'], $equipmentId])) {
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
?>
?>

<!-- Custom Styles -->
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/equipment.css">
<style>
.equipment-edit-container {
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

.current-file {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.current-file img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 0.375rem;
    object-fit: cover;
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
    .equipment-edit-container {
        padding: 0.5rem;
    }
    
    .form-section-body {
        padding: 1rem;
    }
    
    .equipment-preview {
        position: static;
        margin-top: 1rem;
    }
}
</style>

<div class="equipment-edit-container">
    <!-- Error/Success Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Có lỗi xảy ra:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                                    <select class="form-select" id="industryId" name="industry_id" required>
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
                                    <select class="form-select" id="workshopId" name="workshop_id" required>
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
                                    <select class="form-select" id="lineId" name="line_id">
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
                                    <select class="form-select" id="machineTypeId" name="machine_type_id" required>
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
                                    <small>Trạng thái hiện tại: <strong><?php echo getStatusText($formData['status'] ?? 'active'); ?></strong></small>
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
                                    <span id="specificationsCounter"><?php echo strlen($formData['specifications'] ?? ''); ?></span>/2000 ký tự
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="form-floating">
                                <textarea class="form-control" id="notes" name="notes" 
                                          placeholder="Ghi chú" style="height: 100px;" maxlength="1000"><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                                <label for="notes">Ghi chú</label>
                                <div class="character-counter">
                                    <span id="notesCounter"><?php echo strlen($formData['notes'] ?? ''); ?></span>/1000 ký tự
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
                                
                                <!-- Current Image -->
                                <?php if (!empty($formData['image_path']) && file_exists($formData['image_path'])): ?>
                                <div class="current-file">
                                    <div class="d-flex align-items-start">
                                        <img src="<?php echo str_replace(BASE_PATH, APP_URL, $formData['image_path']); ?>" 
                                             alt="Current image" class="me-3">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold text-success">Hình ảnh hiện tại</div>
                                            <small class="text-muted">
                                                <?php echo basename($formData['image_path']); ?>
                                            </small>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="removeCurrentImage()">
                                                    <i class="fas fa-times me-1"></i>Xóa hình ảnh
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="file-upload-section" onclick="document.getElementById('imageFile').click()">
                                    <input type="file" id="imageFile" name="image" accept="image/*" class="d-none" onchange="handleFileSelect(this, 'image')">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Click để chọn hình ảnh mới</strong><br>
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
                                
                                <!-- Current Manual -->
                                <?php if (!empty($formData['manual_path']) && file_exists($formData['manual_path'])): ?>
                                <div class="current-file">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-pdf text-danger me-3" style="font-size: 2rem;"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold text-success">Tài liệu hiện tại</div>
                                            <small class="text-muted">
                                                <?php echo basename($formData['manual_path']); ?>
                                            </small>
                                            <div class="mt-2">
                                                <a href="<?php echo str_replace(BASE_PATH, APP_URL, $formData['manual_path']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                                    <i class="fas fa-download me-1"></i>Tải xuống
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="removeCurrentManual()">
                                                    <i class="fas fa-times me-1"></i>Xóa tài liệu
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="file-upload-section" onclick="document.getElementById('manualFile').click()">
                                    <input type="file" id="manualFile" name="manual" accept=".pdf,.doc,.docx" class="d-none" onchange="handleFileSelect(this, 'manual')">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <strong>Click để chọn tài liệu mới</strong><br>
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
                        <i class="fas fa-eye me-2"></i>Thông tin hiện tại
                    </h6>
                    
                    <div id="previewContent">
                        <div class="preview-item">
                            <span class="preview-label">Tên thiết bị:</span>
                            <span class="preview-value" id="previewName"><?php echo htmlspecialchars($formData['name'] ?? 'Chưa nhập'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Mã thiết bị:</span>
                            <span class="preview-value" id="previewCode"><?php echo htmlspecialchars($formData['code'] ?? 'Chưa có mã'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Ngành:</span>
                            <span class="preview-value" id="previewIndustry"><?php echo htmlspecialchars($formData['industry_name'] ?? 'Chưa chọn'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Xưởng:</span>
                            <span class="preview-value" id="previewWorkshop"><?php echo htmlspecialchars($formData['workshop_name'] ?? 'Chưa chọn'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Dòng máy:</span>
                            <span class="preview-value" id="previewMachineType"><?php echo htmlspecialchars($formData['machine_type_name'] ?? 'Chưa chọn'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Nhà sản xuất:</span>
                            <span class="preview-value" id="previewManufacturer"><?php echo htmlspecialchars($formData['manufacturer'] ?? 'Chưa nhập'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Model:</span>
                            <span class="preview-value" id="previewModel"><?php echo htmlspecialchars($formData['model'] ?? 'Chưa nhập'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Mức độ quan trọng:</span>
                            <span class="preview-value" id="previewCriticality"><?php echo htmlspecialchars($formData['criticality'] ?? 'Medium'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Trạng thái:</span>
                            <span class="preview-value" id="previewStatus"><?php echo getStatusText($formData['status'] ?? 'active'); ?></span>
                        </div>
                        <div class="preview-item">
                            <span class="preview-label">Người quản lý:</span>
                            <span class="preview-value" id="previewOwner"><?php echo htmlspecialchars($formData['owner_name'] ?? 'Chưa chọn'); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Cập nhật lần cuối: <?php echo formatDateTime($formData['updated_at'] ?? $formData['created_at']); ?>
                        </small>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-3">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="saveEquipment()">
                            <i class="fas fa-save me-2"></i>Cập nhật thiết bị
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="saveDraft()">
                            <i class="fas fa-bookmark me-2"></i>Lưu nháp
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="previewEquipment()">
                            <i class="fas fa-eye me-2"></i>Xem trước đầy đủ
                        </button>
                        <a href="view.php?id=<?php echo $equipment_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-external-link-alt me-2"></i>Xem chi tiết
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset form
                        </button>
                    </div>
                </div>

                <!-- Equipment Info -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="text-primary">
                        <i class="fas fa-info-circle me-2"></i>Thông tin thiết bị
                    </h6>
                    <ul class="small mb-0 ps-3">
                        <li><strong>ID:</strong> <?php echo $equipment_id; ?></li>
                        <li><strong>Ngày tạo:</strong> <?php echo formatDateTime($formData['created_at']); ?></li>
                        <li><strong>Cập nhật:</strong> <?php echo formatDateTime($formData['updated_at'] ?? $formData['created_at']); ?></li>
                        <?php if (!empty($formData['installation_date'])): ?>
                        <li><strong>Lắp đặt:</strong> <?php echo formatDate($formData['installation_date']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
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
                    <i class="fas fa-save me-2"></i>Cập nhật thiết bị
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Configuration -->
<script>
// Set configuration for JavaScript
window.equipmentConfig = {
    equipmentId: <?php echo $equipment_id; ?>,
    maxFileSize: <?php echo 5 * 1024 * 1024; ?>, // 5MB for images
    allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    allowedDocTypes: ['pdf', 'doc', 'docx'],
    autoSaveInterval: 30000, // 30 seconds
    isEditMode: true
};

// Pass form data to JavaScript
window.formData = <?php echo json_encode($formData); ?>;

// Global functions for HTML callbacks
function saveEquipment() {
    document.getElementById('formAction').value = 'save';
    document.getElementById('equipmentForm').submit();
}

function saveDraft() {
    document.getElementById('formAction').value = 'draft';
    document.getElementById('equipmentForm').submit();
}

function previewEquipment() {
    const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
    // Generate preview content here
    modal.show();
}

function resetForm() {
    if (confirm('Bạn có chắc chắn muốn reset form? Tất cả thay đổi chưa lưu sẽ bị mất.')) {
        location.reload();
    }
}

function handleFileSelect(input, type) {
    const file = input.files[0];
    if (!file) return;
    
    console.log('File selected:', file.name, 'Type:', type);
    
    // Basic validation
    const maxSize = type === 'image' ? 5 * 1024 * 1024 : 10 * 1024 * 1024;
    if (file.size > maxSize) {
        alert(`File quá lớn. Kích thước tối đa: ${maxSize / 1024 / 1024}MB`);
        input.value = '';
        return;
    }
    
    // Show preview
    const previewId = type + 'Preview';
    const preview = document.getElementById(previewId);
    
    if (preview) {
        preview.classList.remove('d-none');
        preview.innerHTML = `
            <div class="mt-2 p-2 bg-light rounded">
                <i class="fas fa-file me-2"></i>
                <strong>File mới:</strong> ${file.name}
                <button type="button" class="btn btn-sm btn-outline-danger float-end" onclick="clearFilePreview('${type}')">
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
}

function removeCurrentImage() {
    if (confirm('Bạn có chắc chắn muốn xóa hình ảnh hiện tại?')) {
        // Add hidden input to mark for deletion
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_current_image';
        hiddenInput.value = '1';
        document.getElementById('equipmentForm').appendChild(hiddenInput);
        
        // Hide current image display
        const currentFile = document.querySelector('.current-file');
        if (currentFile) {
            currentFile.style.display = 'none';
        }
    }
}

function removeCurrentManual() {
    if (confirm('Bạn có chắc chắn muốn xóa tài liệu hiện tại?')) {
        // Add hidden input to mark for deletion
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_current_manual';
        hiddenInput.value = '1';
        document.getElementById('equipmentForm').appendChild(hiddenInput);
        
        // Hide current manual display
        const currentFile = document.querySelectorAll('.current-file')[1];
        if (currentFile) {
            currentFile.style.display = 'none';
        }
    }
}

// Initialize character counters
document.addEventListener('DOMContentLoaded', function() {
    const textareas = ['specifications', 'notes'];
    
    textareas.forEach(id => {
        const textarea = document.getElementById(id);
        const counter = document.getElementById(id + 'Counter');
        
        if (textarea && counter) {
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = length;
                
                // Update counter color based on length
                const maxLength = id === 'specifications' ? 2000 : 1000;
                counter.className = 'character-counter';
                
                if (length > maxLength * 0.9) {
                    counter.classList.add('danger');
                } else if (length > maxLength * 0.8) {
                    counter.classList.add('warning');
                }
            });
        }
    });
    
    // Initialize dependent dropdowns
    initializeDependentDropdowns();
});

function initializeDependentDropdowns() {
    const industrySelect = document.getElementById('industryId');
    const workshopSelect = document.getElementById('workshopId');
    const lineSelect = document.getElementById('lineId');
    const areaSelect = document.getElementById('areaId');
    const machineTypeSelect = document.getElementById('machineTypeId');
    const equipmentGroupSelect = document.getElementById('equipmentGroupId');
    
    // Handle industry change
    industrySelect.addEventListener('change', function() {
        updateWorkshops(this.value);
    });
    
    // Handle workshop change
    workshopSelect.addEventListener('change', function() {
        updateLines(this.value);
        updateAreas(this.value);
    });
    
    // Handle machine type change
    machineTypeSelect.addEventListener('change', function() {
        updateEquipmentGroups(this.value);
    });
}

function updateWorkshops(industryId) {
    const workshopSelect = document.getElementById('workshopId');
    const lineSelect = document.getElementById('lineId');
    const areaSelect = document.getElementById('areaId');
    
    // Clear dependent selects
    clearSelectOptions(workshopSelect, '-- Chọn xưởng --');
    clearSelectOptions(lineSelect, '-- Chọn line (tùy chọn) --');
    clearSelectOptions(areaSelect, '-- Chọn khu vực (tùy chọn) --');
    
    if (!industryId) return;
    
    // Filter workshops
    const workshopOptions = workshopSelect.querySelectorAll('option[data-industry]');
    workshopOptions.forEach(option => {
        option.style.display = option.dataset.industry === industryId ? '' : 'none';
    });
}

function updateLines(workshopId) {
    const lineSelect = document.getElementById('lineId');
    clearSelectOptions(lineSelect, '-- Chọn line (tùy chọn) --');
    
    if (!workshopId) return;
    
    const lineOptions = lineSelect.querySelectorAll('option[data-workshop]');
    lineOptions.forEach(option => {
        option.style.display = option.dataset.workshop === workshopId ? '' : 'none';
    });
}

function updateAreas(workshopId) {
    const areaSelect = document.getElementById('areaId');
    clearSelectOptions(areaSelect, '-- Chọn khu vực (tùy chọn) --');
    
    if (!workshopId) return;
    
    const areaOptions = areaSelect.querySelectorAll('option[data-workshop]');
    areaOptions.forEach(option => {
        option.style.display = option.dataset.workshop === workshopId ? '' : 'none';
    });
}

function updateEquipmentGroups(machineTypeId) {
    const equipmentGroupSelect = document.getElementById('equipmentGroupId');
    clearSelectOptions(equipmentGroupSelect, '-- Chọn cụm thiết bị (tùy chọn) --');
    
    if (!machineTypeId) return;
    
    const groupOptions = equipmentGroupSelect.querySelectorAll('option[data-machine-type]');
    groupOptions.forEach(option => {
        option.style.display = option.dataset.machineType === machineTypeId ? '' : 'none';
    });
}

function clearSelectOptions(selectElement, defaultText) {
    const currentValue = selectElement.value;
    Array.from(selectElement.options).forEach(option => {
        if (option.value === '') {
            option.textContent = defaultText;
        } else {
            option.style.display = 'none';
        }
    });
    selectElement.value = '';
}
</script>

<!-- Load Equipment JavaScript -->
<script src="<?php echo APP_URL; ?>/assets/js/equipment-add.js"></script>

<?php require_once '../../includes/footer.php'; ?>