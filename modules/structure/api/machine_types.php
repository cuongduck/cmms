<?php
/**
 * Machine Types API - /modules/structure/api/machine_types.php
 * CRUD operations for Machine Types module - Simplified version
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
            getMachineTypesList();
            break;
        case 'get':
            getMachineType();
            break;
        case 'check_code':
            checkCode();
            break;
        case 'export':
            exportMachineTypes();
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
            createMachineType();
            break;
        case 'update':
            updateMachineType();
            break;
        case 'delete':
            deleteMachineType();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get machine types list with filters and pagination
 */
function getMachineTypesList() {
    global $db;
    
    requirePermission('structure', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status'];
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
        $whereConditions[] = "(name LIKE ? OR code LIKE ? OR description LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM machine_types $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($total / $limit);
    
    // Get data
    $sql = "SELECT id, name, code, description, specifications, status, created_at, updated_at
            FROM machine_types 
            $whereClause 
            ORDER BY $sortBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $machineTypes = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($machineTypes as &$machineType) {
        $machineType['created_at_formatted'] = formatDateTime($machineType['created_at']);
        $machineType['updated_at_formatted'] = formatDateTime($machineType['updated_at']);
        $machineType['status_text'] = getStatusText($machineType['status']);
        $machineType['status_class'] = getStatusClass($machineType['status']);
        
        // Get equipment groups count
        $equipmentGroupsCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment_groups WHERE machine_type_id = ?", 
            [$machineType['id']]
        )['count'];
        $machineType['equipment_groups_count'] = $equipmentGroupsCount;
        
        // Get equipment count
        $equipmentCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment WHERE machine_type_id = ?", 
            [$machineType['id']]
        )['count'];
        $machineType['equipment_count'] = $equipmentCount;
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
        'machine_types' => $machineTypes,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single machine type
 */
function getMachineType() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT * FROM machine_types WHERE id = ?";
    $machineType = $db->fetch($sql, [$id]);
    
    if (!$machineType) {
        throw new Exception('Không tìm thấy dòng máy');
    }
    
    // Get related data
    $machineType['equipment_groups_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment_groups WHERE machine_type_id = ?", 
        [$id]
    )['count'];
    
    $machineType['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE machine_type_id = ?", 
        [$id]
    )['count'];
    
    successResponse($machineType);
}

/**
 * Check if code exists
 */
function checkCode() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $code = trim($_GET['code'] ?? '');
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Mã code không được trống');
    }
    
    $sql = "SELECT id FROM machine_types WHERE code = ?";
    $params = [$code];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $exists = $db->fetch($sql, $params);
    
    successResponse(['exists' => (bool)$exists]);
}

/**
 * Create new machine type
 */
function createMachineType() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên dòng máy không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên dòng máy không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã dòng máy không được trống';
    } elseif (strlen($code) > 20) {
        $errors[] = 'Mã dòng máy không được quá 20 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã dòng máy chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    if (strlen($specifications) > 2000) {
        $errors[] = 'Thông số kỹ thuật không được quá 2000 ký tự';
    }
    
    // Check code uniqueness
    if (empty($errors)) {
        $sql = "SELECT id FROM machine_types WHERE code = ?";
        if ($db->fetch($sql, [$code])) {
            $errors[] = 'Mã dòng máy đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO machine_types (name, code, description, specifications, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $description,
            $specifications,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $machineTypeId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'machine_types', "Tạo dòng máy: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('machine_types', $machineTypeId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'specifications' => $specifications,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $machineTypeId], 'Tạo dòng máy thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo dòng máy: ' . $e->getMessage());
    }
}

/**
 * Update machine type
 */
function updateMachineType() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM machine_types WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy dòng máy');
    }
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên dòng máy không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên dòng máy không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã dòng máy không được trống';
    } elseif (strlen($code) > 20) {
        $errors[] = 'Mã dòng máy không được quá 20 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã dòng máy chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    if (strlen($specifications) > 2000) {
        $errors[] = 'Thông số kỹ thuật không được quá 2000 ký tự';
    }
    
    // Check code uniqueness (exclude current record)
    if (empty($errors)) {
        $sql = "SELECT id FROM machine_types WHERE code = ? AND id != ?";
        if ($db->fetch($sql, [$code, $id])) {
            $errors[] = 'Mã dòng máy đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE machine_types 
                SET name = ?, code = ?, description = ?, specifications = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $description, $specifications, $status, $id];
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update', 'machine_types', "Cập nhật dòng máy: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('machine_types', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'specifications' => $specifications,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật dòng máy thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật dòng máy: ' . $e->getMessage());
    }
}

/**
 * Delete machine type
 */
function deleteMachineType() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM machine_types WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy dòng máy');
    }
    
    // Check if machine type has equipment groups
    $equipmentGroupsCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment_groups WHERE machine_type_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentGroupsCount > 0) {
        throw new Exception('Không thể xóa dòng máy này vì đang có ' . $equipmentGroupsCount . ' cụm thiết bị thuộc dòng máy này');
    }
    
    // Check if machine type has equipment
    $equipmentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE machine_type_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentCount > 0) {
        throw new Exception('Không thể xóa dòng máy này vì đang có ' . $equipmentCount . ' thiết bị thuộc dòng máy này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete
        $sql = "DELETE FROM machine_types WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'machine_types', "Xóa dòng máy: {$currentData['name']} ({$currentData['code']})", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('machine_types', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa dòng máy thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa dòng máy: ' . $e->getMessage());
    }
}

/**
 * Toggle machine type status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM machine_types WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy dòng máy');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE machine_types SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'machine_types', "Thay đổi trạng thái dòng máy: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}

/**
 * Export machine types to Excel
 */
function exportMachineTypes() {
    global $db;
    
    requirePermission('structure', 'export');
    
    // Get all machine types
    $sql = "SELECT name, code, description, specifications, status, created_at, updated_at 
            FROM machine_types 
            ORDER BY name";
    $data = $db->fetchAll($sql);
    
    // Format data for export
    $exportData = [];
    foreach ($data as $row) {
        $exportData[] = [
            'Tên dòng máy' => $row['name'],
            'Mã dòng máy' => $row['code'],
            'Mô tả' => $row['description'],
            'Thông số kỹ thuật' => $row['specifications'],
            'Trạng thái' => $row['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động',
            'Ngày tạo' => formatDateTime($row['created_at']),
            'Cập nhật' => formatDateTime($row['updated_at'])
        ];
    }
    
    $headers = array_keys($exportData[0] ?? []);
    
    // Export to Excel (simple CSV format)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="machine_types_' . date('Y-m-d_H-i-s') . '.csv"');
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