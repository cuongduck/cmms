<?php
/**
 * Equipment API - /modules/equipment/api/equipment.php
 * CRUD operations for Equipment module
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';

// Require login
requireLogin();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

/**
 * Handle GET requests
 */
function handleGet() {
    global $action;
    
    switch ($action) {
        case 'list':
            getEquipmentList();
            break;
        case 'get':
            getEquipment();
            break;
        case 'export':
            exportEquipment();
            break;
        case 'get_structure_options':
            getStructureOptions();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Handle POST requests
 */
function handlePost() {
    global $action;
    
    switch ($action) {
        case 'delete':
            deleteEquipment();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        case 'bulk_update_status':
            bulkUpdateStatus();
            break;
        case 'bulk_delete':
            bulkDelete();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get equipment list with filters and pagination
 */
function getEquipmentList() {
    global $db;
    
    requirePermission('equipment', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $industryId = $_GET['industry_id'] ?? '';
    $workshopId = $_GET['workshop_id'] ?? '';
    $lineId = $_GET['line_id'] ?? '';
    $areaId = $_GET['area_id'] ?? '';
    $machineTypeId = $_GET['machine_type_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $criticality = $_GET['criticality'] ?? '';
    $ownerId = $_GET['owner_id'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'criticality'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'name';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'ASC';
    }
    
    // Build main query with all joins
    $baseQuery = "
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
    ";
    
    // Build WHERE clause
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(e.name LIKE ? OR e.code LIKE ? OR e.manufacturer LIKE ? OR e.model LIKE ? OR e.serial_number LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($industryId)) {
        $whereConditions[] = "e.industry_id = ?";
        $params[] = $industryId;
    }
    
    if (!empty($workshopId)) {
        $whereConditions[] = "e.workshop_id = ?";
        $params[] = $workshopId;
    }
    
    if (!empty($lineId)) {
        $whereConditions[] = "e.line_id = ?";
        $params[] = $lineId;
    }
    
    if (!empty($areaId)) {
        $whereConditions[] = "e.area_id = ?";
        $params[] = $areaId;
    }
    
    if (!empty($machineTypeId)) {
        $whereConditions[] = "e.machine_type_id = ?";
        $params[] = $machineTypeId;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "e.status = ?";
        $params[] = $status;
    }
    
    if (!empty($criticality)) {
        $whereConditions[] = "e.criticality = ?";
        $params[] = $criticality;
    }
    
    if (!empty($ownerId)) {
        $whereConditions[] = "(e.owner_user_id = ? OR e.backup_owner_user_id = ?)";
        $params = array_merge($params, [$ownerId, $ownerId]);
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total $baseQuery $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($total / $limit);
    
    // Get data with all fields needed
    $sql = "
        SELECT 
            e.id, e.code, e.name, e.manufacturer, e.model, e.serial_number,
            e.manufacture_year, e.installation_date, e.warranty_expiry,
            e.maintenance_frequency_days, e.maintenance_frequency_type,
            e.specifications, e.location_details, e.criticality, e.status,
            e.image_path, e.manual_path, e.notes, e.created_at, e.updated_at,
            
            i.name as industry_name, i.code as industry_code,
            w.name as workshop_name, w.code as workshop_code,
            pl.name as line_name, pl.code as line_code,
            a.name as area_name, a.code as area_code,
            mt.name as machine_type_name, mt.code as machine_type_code,
            eg.name as equipment_group_name,
            
            u1.full_name as owner_name,
            u2.full_name as backup_owner_name,
            u3.full_name as created_by_name
            
        $baseQuery 
        $whereClause 
        ORDER BY e.$sortBy $sortOrder 
        LIMIT $limit OFFSET $offset
    ";
    
    $equipment = $db->fetchAll($sql, $params);
    
    // Format data and add calculated fields
    foreach ($equipment as &$item) {
        $item['created_at_formatted'] = formatDateTime($item['created_at']);
        $item['updated_at_formatted'] = formatDateTime($item['updated_at']);
        $item['installation_date_formatted'] = formatDate($item['installation_date']);
        $item['warranty_expiry_formatted'] = formatDate($item['warranty_expiry']);
        
        $item['status_text'] = getStatusText($item['status']);
        $item['status_class'] = getStatusClass($item['status']);
        $item['criticality_class'] = getStatusClass($item['criticality']);
        
        // Calculate next maintenance date (simplified)
        $item['next_maintenance'] = null;
        $item['maintenance_due'] = false;
        
        if ($item['maintenance_frequency_days'] && $item['installation_date']) {
            $installDate = new DateTime($item['installation_date']);
            $nextMaintenance = clone $installDate;
            $nextMaintenance->add(new DateInterval('P' . $item['maintenance_frequency_days'] . 'D'));
            
            // Keep adding maintenance intervals until we get a future date
            while ($nextMaintenance <= new DateTime()) {
                $nextMaintenance->add(new DateInterval('P' . $item['maintenance_frequency_days'] . 'D'));
            }
            
            $item['next_maintenance'] = $nextMaintenance->format('d/m/Y');
            
            // Check if maintenance is due soon (within 7 days)
            $today = new DateTime();
            $daysUntilMaintenance = $today->diff($nextMaintenance)->days;
            $item['maintenance_due'] = $daysUntilMaintenance <= 7;
        }
        
        // Format location path
        $locationParts = array_filter([
            $item['industry_name'],
            $item['workshop_name'],
            $item['line_name'],
            $item['area_name']
        ]);
        $item['location_path'] = implode(' → ', $locationParts);
        
        // Format image URL
        if ($item['image_path']) {
            $item['image_url'] = str_replace(BASE_PATH, APP_URL, $item['image_path']);
        } else {
            $item['image_url'] = null;
        }
    }
    
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $total,
        'per_page' => $limit,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages
    ];
    
    successResponse([
        'equipment' => $equipment,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'line_id' => $lineId,
            'area_id' => $areaId,
            'machine_type_id' => $machineTypeId,
            'status' => $status,
            'criticality' => $criticality,
            'owner_id' => $ownerId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single equipment with full details
 */
function getEquipment() {
    global $db;
    
    requirePermission('equipment', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "
        SELECT 
            e.*,
            i.name as industry_name, i.code as industry_code,
            w.name as workshop_name, w.code as workshop_code,
            pl.name as line_name, pl.code as line_code,
            a.name as area_name, a.code as area_code,
            mt.name as machine_type_name, mt.code as machine_type_code,
            eg.name as equipment_group_name,
            u1.full_name as owner_name, u1.email as owner_email,
            u2.full_name as backup_owner_name, u2.email as backup_owner_email,
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
    
    $equipment = $db->fetch($sql, [$id]);
    
    if (!$equipment) {
        throw new Exception('Không tìm thấy thiết bị');
    }
    
    // Format dates and add calculated fields
    $equipment['created_at_formatted'] = formatDateTime($equipment['created_at']);
    $equipment['updated_at_formatted'] = formatDateTime($equipment['updated_at']);
    $equipment['installation_date_formatted'] = formatDate($equipment['installation_date']);
    $equipment['warranty_expiry_formatted'] = formatDate($equipment['warranty_expiry']);
    
    $equipment['status_text'] = getStatusText($equipment['status']);
    $equipment['status_class'] = getStatusClass($equipment['status']);
    
    // Parse technical specs if JSON
    if ($equipment['technical_specs']) {
        $techSpecs = json_decode($equipment['technical_specs'], true);
        if ($techSpecs) {
            $equipment['technical_specs_parsed'] = $techSpecs;
        }
    }
    
    // Parse settings images if JSON
    if ($equipment['settings_images']) {
        $settingsImages = json_decode($equipment['settings_images'], true);
        if ($settingsImages) {
            $equipment['settings_images_parsed'] = $settingsImages;
        }
    }
    
    // Format image URLs
    if ($equipment['image_path']) {
        $equipment['image_url'] = str_replace(BASE_PATH, APP_URL, $equipment['image_path']);
    }
    
    if ($equipment['manual_path']) {
        $equipment['manual_url'] = str_replace(BASE_PATH, APP_URL, $equipment['manual_path']);
    }
    
    // Get maintenance history (basic implementation)
    $maintenanceHistory = $db->fetchAll(
        "SELECT 'Bảo trì định kỳ' as type, DATE_ADD(installation_date, INTERVAL ? DAY) as date, 'completed' as status 
         FROM equipment WHERE id = ? AND installation_date IS NOT NULL AND maintenance_frequency_days IS NOT NULL
         LIMIT 5", 
        [$equipment['maintenance_frequency_days'] ?? 30, $id]
    );
    
    $equipment['maintenance_history'] = $maintenanceHistory;
    
    successResponse($equipment);
}

/**
 * Get structure options for filters
 */
function getStructureOptions() {
    global $db;
    
    requirePermission('equipment', 'view');
    
    $industries = $db->fetchAll("SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name");
    $workshops = $db->fetchAll("SELECT id, name, code, industry_id FROM workshops WHERE status = 'active' ORDER BY name");
    $lines = $db->fetchAll("SELECT id, name, code, workshop_id FROM production_lines WHERE status = 'active' ORDER BY name");
    $areas = $db->fetchAll("SELECT id, name, code, workshop_id FROM areas WHERE status = 'active' ORDER BY name");
    $machineTypes = $db->fetchAll("SELECT id, name, code FROM machine_types WHERE status = 'active' ORDER BY name");
    $equipmentGroups = $db->fetchAll("SELECT id, name, machine_type_id FROM equipment_groups WHERE status = 'active' ORDER BY name");
    $users = $db->fetchAll("SELECT id, full_name, email FROM users WHERE status = 'active' ORDER BY full_name");
    
    successResponse([
        'industries' => $industries,
        'workshops' => $workshops,
        'lines' => $lines,
        'areas' => $areas,
        'machine_types' => $machineTypes,
        'equipment_groups' => $equipmentGroups,
        'users' => $users
    ]);
}

/**
 * Delete equipment
 */
function deleteEquipment() {
    global $db;
    
    requirePermission('equipment', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM equipment WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy thiết bị');
    }
    
    // Check if equipment has related data that should prevent deletion
    // (You might want to add checks for maintenance records, work orders, etc.)
    
    try {
        $db->beginTransaction();
        
        // Delete associated files
        if ($currentData['image_path'] && file_exists($currentData['image_path'])) {
            unlink($currentData['image_path']);
        }
        
        if ($currentData['manual_path'] && file_exists($currentData['manual_path'])) {
            unlink($currentData['manual_path']);
        }
        
        // Hard delete
        $sql = "DELETE FROM equipment WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'equipment', "Xóa thiết bị: {$currentData['name']} ({$currentData['code']})", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('equipment', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa thiết bị thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa thiết bị: ' . $e->getMessage());
    }
}

/**
 * Toggle equipment status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('equipment', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM equipment WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy thiết bị');
    }
    
    // Define status cycle: active -> maintenance -> inactive -> broken -> active
    $statusCycle = [
        'active' => 'maintenance',
        'maintenance' => 'inactive', 
        'inactive' => 'broken',
        'broken' => 'active'
    ];
    
    $newStatus = $statusCycle[$currentData['status']] ?? 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE equipment SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = getStatusText($newStatus);
        logActivity('update', 'equipment', "Thay đổi trạng thái thiết bị: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus, 'new_status_text' => getStatusText($newStatus)], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}

/**
 * Bulk update status
 */
function bulkUpdateStatus() {
    global $db;
    
    requirePermission('equipment', 'edit');
    
    $ids = $_POST['ids'] ?? [];
    $status = $_POST['status'] ?? '';
    
    if (empty($ids) || !is_array($ids)) {
        throw new Exception('Danh sách thiết bị không hợp lệ');
    }
    
    if (!in_array($status, ['active', 'inactive', 'maintenance', 'broken'])) {
        throw new Exception('Trạng thái không hợp lệ');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $db->beginTransaction();
        
        // Get current data for logging
        $currentData = $db->fetchAll("SELECT id, name, code FROM equipment WHERE id IN ($placeholders)", $ids);
        
        // Update status
        $sql = "UPDATE equipment SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
        $params = array_merge([$status], $ids);
        $db->execute($sql, $params);
        
        // Log activity for each equipment
        foreach ($currentData as $equipment) {
            $statusText = getStatusText($status);
            logActivity('bulk_update', 'equipment', "Cập nhật hàng loạt trạng thái: {$equipment['name']} - $statusText", getCurrentUser()['id']);
        }
        
        $db->commit();
        
        $count = count($ids);
        successResponse([], "Cập nhật trạng thái thành công cho $count thiết bị");
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật hàng loạt: ' . $e->getMessage());
    }
}

/**
 * Bulk delete equipment
 */
function bulkDelete() {
    global $db;
    
    requirePermission('equipment', 'delete');
    
    $ids = $_POST['ids'] ?? [];
    
    if (empty($ids) || !is_array($ids)) {
        throw new Exception('Danh sách thiết bị không hợp lệ');
    }
    
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $db->beginTransaction();
        
        // Get current data for logging and file cleanup
        $currentData = $db->fetchAll("SELECT id, name, code, image_path, manual_path FROM equipment WHERE id IN ($placeholders)", $ids);
        
        // Delete associated files
        foreach ($currentData as $equipment) {
            if ($equipment['image_path'] && file_exists($equipment['image_path'])) {
                unlink($equipment['image_path']);
            }
            
            if ($equipment['manual_path'] && file_exists($equipment['manual_path'])) {
                unlink($equipment['manual_path']);
            }
        }
        
        // Delete equipment
        $sql = "DELETE FROM equipment WHERE id IN ($placeholders)";
        $db->execute($sql, $ids);
        
        // Log activity for each equipment
        foreach ($currentData as $equipment) {
            logActivity('bulk_delete', 'equipment', "Xóa hàng loạt thiết bị: {$equipment['name']} ({$equipment['code']})", getCurrentUser()['id']);
        }
        
        $db->commit();
        
        $count = count($ids);
        successResponse([], "Xóa thành công $count thiết bị");
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa hàng loạt: ' . $e->getMessage());
    }
}

/**
 * Export equipment to Excel
 */
function exportEquipment() {
    global $db;
    
    requirePermission('equipment', 'export');
    
    // Use the same filters as the list function
    $search = trim($_GET['search'] ?? '');
    $industryId = $_GET['industry_id'] ?? '';
    $workshopId = $_GET['workshop_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $criticality = $_GET['criticality'] ?? '';
    
    // Build query (reuse logic from getEquipmentList)
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(e.name LIKE ? OR e.code LIKE ? OR e.manufacturer LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($industryId)) {
        $whereConditions[] = "e.industry_id = ?";
        $params[] = $industryId;
    }
    
    if (!empty($workshopId)) {
        $whereConditions[] = "e.workshop_id = ?";
        $params[] = $workshopId;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "e.status = ?";
        $params[] = $status;
    }
    
    if (!empty($criticality)) {
        $whereConditions[] = "e.criticality = ?";
        $params[] = $criticality;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            e.code as 'Mã thiết bị',
            e.name as 'Tên thiết bị',
            i.name as 'Ngành',
            w.name as 'Xưởng',
            pl.name as 'Line sản xuất',
            a.name as 'Khu vực',
            mt.name as 'Dòng máy',
            eg.name as 'Cụm thiết bị',
            e.manufacturer as 'Nhà sản xuất',
            e.model as 'Model',
            e.serial_number as 'Số seri',
            e.manufacture_year as 'Năm sản xuất',
            e.installation_date as 'Ngày lắp đặt',
            e.warranty_expiry as 'Hết bảo hành',
            e.maintenance_frequency_days as 'Chu kỳ bảo trì (ngày)',
            e.criticality as 'Mức độ quan trọng',
            CASE 
                WHEN e.status = 'active' THEN 'Hoạt động'
                WHEN e.status = 'inactive' THEN 'Ngưng hoạt động'
                WHEN e.status = 'maintenance' THEN 'Bảo trì'
                WHEN e.status = 'broken' THEN 'Hỏng'
                ELSE e.status
            END as 'Trạng thái',
            u1.full_name as 'Người quản lý chính',
            u2.full_name as 'Người quản lý phụ',
            e.location_details as 'Vị trí chi tiết',
            e.specifications as 'Thông số kỹ thuật',
            e.notes as 'Ghi chú',
            e.created_at as 'Ngày tạo'
            
        FROM equipment e
        LEFT JOIN industries i ON e.industry_id = i.id
        LEFT JOIN workshops w ON e.workshop_id = w.id
        LEFT JOIN production_lines pl ON e.line_id = pl.id
        LEFT JOIN areas a ON e.area_id = a.id
        LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
        LEFT JOIN equipment_groups eg ON e.equipment_group_id = eg.id
        LEFT JOIN users u1 ON e.owner_user_id = u1.id
        LEFT JOIN users u2 ON e.backup_owner_user_id = u2.id
        
        $whereClause
        ORDER BY e.name
    ";
    
    $data = $db->fetchAll($sql, $params);
    
    // Export to Excel (simple CSV format)
    $filename = 'equipment_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Headers
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>