<?php
/**
 * Equipment Edit Page - modules/equipment/edit.php - PART 1
 * Form to edit existing equipment in the system
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

// Get equipment ID
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Get current equipment data
$sql = "
    SELECT 
        e.*,
        i.name as industry_name, i.code as industry_code,
        w.name as workshop_name, w.code as workshop_code,
        pl.name as line_name, pl.code as line_code,
        a.name as area_name, a.code as area_code,
        mt.name as machine_type_name, mt.code as machine_type_code,
        eg.name as equipment_group_name,
        u1.full_name as owner_name,
        u2.full_name as backup_owner_name,
        u3.full_name as created_by_name
        
    FROM equipment e
    LEFT JOIN industries i ON e.industry_id = i.id
    LEFT JOIN workshops w ON e.workshop_id = w.id
    LEFT JOIN production_lines pl ON e.line_id = pl.id
    LEFT JOIN areas a ON e.area_id = a.id
    LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
    LEFT JOIN equipment_groups eg ON e.equipment_group_id = eg.id
    LEFT JOIN users u1 ON e.owner_user_id = u1.id
    LEFT JOIN users u2 ON e.backup_owner_user_id = u2.id
    LEFT JOIN users u3 ON e.created_by = u3.id
    
    WHERE e.id = ?
";

$equipment = $db->fetch($sql, [$equipmentId]);

if (!$equipment) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Set breadcrumb and page actions
$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => $equipment['name'], 'url' => 'view.php?id=' . $equipmentId],
    ['title' => 'Chỉnh sửa']
];

$pageActions = '
<div class="btn-group">
    <button type="button" class="btn btn-success" onclick="updateEquipment()">
        <i class="fas fa-save me-1"></i> Lưu thay đổi
    </button>
    <button type="button" class="btn btn-outline-info" onclick="saveDraft()">
        <i class="fas fa-bookmark me-1"></i> Lưu nháp
    </button>
    <a href="view.php?id=' . $equipmentId . '" class="btn btn-outline-primary">
        <i class="fas fa-eye me-1"></i> Xem chi tiết
    </a>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
    </a>
</div>';

// Handle form submission
$errors = [];
$success = '';
$formData = $equipment; // Initialize with current data
