<?php
/**
 * Equipment Groups API - /modules/structure/api/equipment_groups.php
 * CRUD operations for Equipment Groups module
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
            getEquipmentGroupsList();
            break;
        case 'get':
            getEquipmentGroup();
            break;
        case 'by_machine_type':
            getGroupsByMachineType();
            break;
        case 'check_code':
            checkCode();
            break;
        case 'export':
            exportEquipmentGroups();
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
        case 'create':
            createEquipmentGroup();
            break;
        case 'update':
            updateEquipmentGroup();
            break;
        case 'delete':
            deleteEquipmentGroup();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get equipment groups list with filters and pagination
 */
function getEquipmentGroupsList() {
    global $db;
    
    requirePermission('structure', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $machineTypeId = (int)($_GET['machine_type_id'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'machine_type_name'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'name';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'ASC';
    }
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(eg.name LIKE ? OR eg.code LIKE ? OR eg.description LIKE ? OR mt.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "eg.status = ?";
        $params[] = $status;
    }
    
    if ($machineTypeId > 0) {
        $whereConditions[] = "eg.machine_type_id = ?";
        $params[] = $machineTypeId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Adjust sort field for SQL
    if ($sortBy === 'machine_type_name') {
        $sortBy = 'mt.name';
    } else {
        $sortBy = 'eg.' . $sortBy;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                FROM equipment_groups eg 
                JOIN machine_types mt ON eg.machine_type_id = mt.id 
                $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($total / $limit);
    
    // Get data
    $sql = "SELECT eg.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name
            FROM equipment_groups eg 
            JOIN machine_types mt ON eg.machine_type_id = mt.id
            LEFT JOIN users u ON eg.created_by = u.id
            $whereClause 
            ORDER BY $sortBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $equipmentGroups = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($equipmentGroups as &$group) {
        $group['created_at_formatted'] = formatDateTime($group['created_at']);
        $group['updated_at_formatted'] = formatDateTime($group['updated_at']);
        $group['status_text'] = getStatusText($group['status']);
        $group['status_class'] = getStatusClass($group['status']);
        
        // Get equipment count
        $equipmentCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment WHERE equipment_group_id = ?", 
            [$group['id']]
        )['count'];
        $group['equipment_count'] = $equipmentCount;
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
        'equipment_groups' => $equipmentGroups,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'machine_type_id' => $machineTypeId,
            'sort_by' => $_GET['sort_by'] ?? 'name',
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single equipment group
 */
function getEquipmentGroup() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT eg.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name
            FROM equipment_groups eg 
            JOIN machine_types mt ON eg.machine_type_id = mt.id
            LEFT JOIN users u ON eg.created_by = u.id
            WHERE eg.id = ?";
    $equipmentGroup = $db->fetch($sql, [$id]);
    
    if (!$equipmentGroup) {
        throw new Exception('Không tìm thấy cụm thiết bị');
    }
    
    // Get related data
    $equipmentGroup['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE equipment_group_id = ?", 
        [$id]
    )['count'];
    
    // Get equipment list
    $equipmentGroup['equipment_list'] = $db->fetchAll(
        "SELECT id, code, name, status FROM equipment WHERE equipment_group_id = ? ORDER BY code", 
        [$id]
    );
    
    successResponse($equipmentGroup);
}

/**
 * Get equipment groups by machine type
 */
function getGroupsByMachineType() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $machineTypeId = (int)($_GET['machine_type_id'] ?? 0);
    if (!$machineTypeId) {
        throw new Exception('Machine Type ID không hợp lệ');
    }
    
    $sql = "SELECT id, code, name, description, status
            FROM equipment_groups 
            WHERE machine_type_id = ? AND status = 'active' 
            ORDER BY name";
    
    $groups = $db->fetchAll($sql, [$machineTypeId]);
    
    successResponse($groups);
}

/**
 * Check if code exists for specific machine type
 */
function checkCode() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $code = trim($_GET['code'] ?? '');
    $machineTypeId = (int)($_GET['machine_type_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code) || !$machineTypeId) {
        throw new Exception('Thiếu thông tin kiểm tra');
    }
    
    $sql = "SELECT id FROM equipment_groups WHERE machine_type_id = ? AND code = ?";
    $params = [$machineTypeId, $code];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $exists = $db->fetch($sql, $params);
    
    successResponse(['exists' => (bool)$exists]);
}

/**
 * Create new equipment group
 */
function createEquipmentGroup() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $machineTypeId = (int)($_POST['machine_type_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (!$machineTypeId) {
        $errors[] = 'Vui lòng chọn dòng máy';
    } else {
        // Check if machine type exists
        $machineType = $db->fetch("SELECT id FROM machine_types WHERE id = ? AND status = 'active'", [$machineTypeId]);
        if (!$machineType) {
            $errors[] = 'Dòng máy không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (empty($name)) {
        $errors[] = 'Tên cụm thiết bị không được trống';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Tên cụm thiết bị không được quá 100 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã cụm thiết bị không được trống';
    } elseif (strlen($code) > 20) {
        $errors[] = 'Mã cụm thiết bị không được quá 20 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã cụm thiết bị chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within machine type
    if (empty($errors) && $machineTypeId && $code) {
        $sql = "SELECT id FROM equipment_groups WHERE machine_type_id = ? AND code = ?";
        if ($db->fetch($sql, [$machineTypeId, $code])) {
            $errors[] = 'Mã cụm thiết bị đã tồn tại trong dòng máy này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO equipment_groups (machine_type_id, name, code, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $machineTypeId,
            $name,
            $code,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $equipmentGroupId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'equipment_groups', "Tạo cụm thiết bị: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('equipment_groups', $equipmentGroupId, 'create', null, [
            'machine_type_id' => $machineTypeId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $equipmentGroupId], 'Tạo cụm thiết bị thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo cụm thiết bị: ' . $e->getMessage());
    }
}

/**
 * Update equipment group
 */
function updateEquipmentGroup() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM equipment_groups WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy cụm thiết bị');
    }
    
    // Get and validate input
    $machineTypeId = (int)($_POST['machine_type_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (!$machineTypeId) {
        $errors[] = 'Vui lòng chọn dòng máy';
    } else {
        // Check if machine type exists
        $machineType = $db->fetch("SELECT id FROM machine_types WHERE id = ? AND status = 'active'", [$machineTypeId]);
        if (!$machineType) {
            $errors[] = 'Dòng máy không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (empty($name)) {
        $errors[] = 'Tên cụm thiết bị không được trống';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Tên cụm thiết bị không được quá 100 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã cụm thiết bị không được trống';
    } elseif (strlen($code) > 20) {
        $errors[] = 'Mã cụm thiết bị không được quá 20 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã cụm thiết bị chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within machine type (exclude current record)
    if (empty($errors) && $machineTypeId && $code) {
        $sql = "SELECT id FROM equipment_groups WHERE machine_type_id = ? AND code = ? AND id != ?";
        if ($db->fetch($sql, [$machineTypeId, $code, $id])) {
            $errors[] = 'Mã cụm thiết bị đã tồn tại trong dòng máy này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE equipment_groups 
                SET machine_type_id = ?, name = ?, code = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$machineTypeId, $name, $code, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update', 'equipment_groups', "Cập nhật cụm thiết bị: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('equipment_groups', $id, 'update', $currentData, [
            'machine_type_id' => $machineTypeId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật cụm thiết bị thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật cụm thiết bị: ' . $e->getMessage());
    }
}

/**
 * Delete equipment group
 */
function deleteEquipmentGroup() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM equipment_groups WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy cụm thiết bị');
    }
    
    // Check if equipment group has equipment
    $equipmentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE equipment_group_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentCount > 0) {
        throw new Exception('Không thể xóa cụm thiết bị này vì đang có ' . $equipmentCount . ' thiết bị thuộc cụm này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete
        $sql = "DELETE FROM equipment_groups WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'equipment_groups', "Xóa cụm thiết bị: {$currentData['name']} ({$currentData['code']})", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('equipment_groups', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa cụm thiết bị thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa cụm thiết bị: ' . $e->getMessage());
    }
}

/**
 * Toggle equipment group status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM equipment_groups WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy cụm thiết bị');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE equipment_groups SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'equipment_groups', "Thay đổi trạng thái cụm thiết bị: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}

/**
 * Export equipment groups to Excel
 */
function exportEquipmentGroups() {
    global $db;
    
    requirePermission('structure', 'export');
    
    // Get filters
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $machineTypeId = (int)($_GET['machine_type_id'] ?? 0);
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(eg.name LIKE ? OR eg.code LIKE ? OR eg.description LIKE ? OR mt.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "eg.status = ?";
        $params[] = $status;
    }
    
    if ($machineTypeId > 0) {
        $whereConditions[] = "eg.machine_type_id = ?";
        $params[] = $machineTypeId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get all data
    $sql = "SELECT eg.code, eg.name, eg.description, eg.status, 
                   mt.name as machine_type_name, mt.code as machine_type_code,
                   eg.created_at, eg.updated_at
            FROM equipment_groups eg 
            JOIN machine_types mt ON eg.machine_type_id = mt.id
            $whereClause 
            ORDER BY mt.name, eg.name";
    $data = $db->fetchAll($sql, $params);
    
    // Format data for export
    $exportData = [];
    foreach ($data as $row) {
        $exportData[] = [
            'Mã cụm thiết bị' => $row['code'],
            'Tên cụm thiết bị' => $row['name'],
            'Mô tả' => $row['description'],
            'Dòng máy' => $row['machine_type_name'],
            'Mã dòng máy' => $row['machine_type_code'],
            'Trạng thái' => $row['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động',
            'Ngày tạo' => formatDateTime($row['created_at']),
            'Cập nhật' => formatDateTime($row['updated_at'])
        ];
    }
    
    $headers = array_keys($exportData[0] ?? []);
    
    // Export to Excel (simple CSV format)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="equipment_groups_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, $headers);
    
    // Data
    foreach ($exportData as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
?>