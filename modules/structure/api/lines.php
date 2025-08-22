<?php
/**
 * Production Lines API - /modules/structure/api/lines.php
 * CRUD operations for Production Lines module
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
            getLinesList();
            break;
        case 'get':
            getLine();
            break;
        case 'check_code':
            checkCode();
            break;
        case 'get_industries':
            getIndustries();
            break;
        case 'get_workshops':
            getWorkshops();
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
            createLine();
            break;
        case 'update':
            updateLine();
            break;
        case 'delete':
            deleteLine();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get production lines list with filters and pagination
 */
function getLinesList() {
    global $db;
    
    requirePermission('structure', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $industryId = (int)($_GET['industry_id'] ?? 0);
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'industry_name', 'workshop_name'];
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
        $whereConditions[] = "(pl.name LIKE ? OR pl.code LIKE ? OR pl.description LIKE ? OR w.name LIKE ? OR i.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "pl.status = ?";
        $params[] = $status;
    }
    
    if ($industryId > 0) {
        $whereConditions[] = "w.industry_id = ?";
        $params[] = $industryId;
    }
    
    if ($workshopId > 0) {
        $whereConditions[] = "pl.workshop_id = ?";
        $params[] = $workshopId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                FROM production_lines pl 
                JOIN workshops w ON pl.workshop_id = w.id 
                JOIN industries i ON w.industry_id = i.id 
                $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($total / $limit);
    
    // Handle sorting
    $orderBy = $sortBy;
    if ($sortBy === 'industry_name') {
        $orderBy = 'i.name';
    } elseif ($sortBy === 'workshop_name') {
        $orderBy = 'w.name';
    } else {
        $orderBy = 'pl.' . $sortBy;
    }
    
    // Get data
    $sql = "SELECT pl.id, pl.name, pl.code, pl.description, pl.status, pl.created_at, pl.updated_at,
                   pl.workshop_id, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM production_lines pl 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            $whereClause 
            ORDER BY $orderBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $lines = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($lines as &$line) {
        $line['created_at_formatted'] = formatDateTime($line['created_at']);
        $line['updated_at_formatted'] = formatDateTime($line['updated_at']);
        $line['status_text'] = getStatusText($line['status']);
        $line['status_class'] = getStatusClass($line['status']);
        
        // Get equipment count
        $equipmentCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment WHERE line_id = ?", 
            [$line['id']]
        )['count'];
        $line['equipment_count'] = $equipmentCount;
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
        'lines' => $lines,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single production line
 */
function getLine() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT pl.*, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM production_lines pl 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            WHERE pl.id = ?";
    $line = $db->fetch($sql, [$id]);
    
    if (!$line) {
        throw new Exception('Không tìm thấy line sản xuất');
    }
    
    // Get related data
    $line['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE line_id = ?", 
        [$id]
    )['count'];
    
    successResponse($line);
}

/**
 * Check if code exists
 */
function checkCode() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $code = trim($_GET['code'] ?? '');
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Mã code không được trống');
    }
    
    if (!$workshopId) {
        throw new Exception('Workshop ID không hợp lệ');
    }
    
    $sql = "SELECT id FROM production_lines WHERE code = ? AND workshop_id = ?";
    $params = [$code, $workshopId];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $exists = $db->fetch($sql, $params);
    
    successResponse(['exists' => (bool)$exists]);
}

/**
 * Get industries for dropdown
 */
function getIndustries() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $sql = "SELECT id, name, code FROM industries WHERE status = 'active' ORDER BY name";
    $industries = $db->fetchAll($sql);
    
    successResponse(['industries' => $industries]);
}

/**
 * Get workshops for dropdown
 */
function getWorkshops() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $industryId = (int)($_GET['industry_id'] ?? 0);
    
    $sql = "SELECT w.id, w.name, w.code, w.industry_id, i.name as industry_name, i.code as industry_code
            FROM workshops w 
            JOIN industries i ON w.industry_id = i.id 
            WHERE w.status = 'active'";
    $params = [];
    
    if ($industryId > 0) {
        $sql .= " AND w.industry_id = ?";
        $params[] = $industryId;
    }
    
    $sql .= " ORDER BY i.name, w.name";
    
    $workshops = $db->fetchAll($sql, $params);
    
    successResponse(['workshops' => $workshops]);
}

/**
 * Create new production line
 */
function createLine() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên line sản xuất không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên line sản xuất không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã line sản xuất không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã line sản xuất không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã line sản xuất chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$workshopId) {
        $errors[] = 'Vui lòng chọn xưởng';
    } else {
        // Check if workshop exists and is active
        $workshop = $db->fetch("SELECT w.*, i.name as industry_name FROM workshops w JOIN industries i ON w.industry_id = i.id WHERE w.id = ? AND w.status = 'active'", [$workshopId]);
        if (!$workshop) {
            $errors[] = 'Xưởng không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within workshop
    if (empty($errors)) {
        $sql = "SELECT id FROM production_lines WHERE code = ? AND workshop_id = ?";
        if ($db->fetch($sql, [$code, $workshopId])) {
            $errors[] = 'Mã line sản xuất đã tồn tại trong xưởng này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO production_lines (name, code, workshop_id, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $workshopId,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $lineId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'production_lines', "Tạo line sản xuất: $name ($code) - Xưởng: {$workshop['name']} - Ngành: {$workshop['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('production_lines', $lineId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'workshop_id' => $workshopId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $lineId], 'Tạo line sản xuất thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo line sản xuất: ' . $e->getMessage());
    }
}
/**
 * Update production line
 */
function updateLine() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM production_lines WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy line sản xuất');
    }
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên line sản xuất không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên line sản xuất không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã line sản xuất không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã line sản xuất không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã line sản xuất chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$workshopId) {
        $errors[] = 'Vui lòng chọn xưởng';
    } else {
        // Check if workshop exists and is active
        $workshop = $db->fetch("SELECT w.*, i.name as industry_name FROM workshops w JOIN industries i ON w.industry_id = i.id WHERE w.id = ? AND w.status = 'active'", [$workshopId]);
        if (!$workshop) {
            $errors[] = 'Xưởng không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within workshop (exclude current record)
    if (empty($errors)) {
        $sql = "SELECT id FROM production_lines WHERE code = ? AND workshop_id = ? AND id != ?";
        if ($db->fetch($sql, [$code, $workshopId, $id])) {
            $errors[] = 'Mã line sản xuất đã tồn tại trong xưởng này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE production_lines 
                SET name = ?, code = ?, workshop_id = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $workshopId, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update', 'production_lines', "Cập nhật line sản xuất: $name ($code) - Xưởng: {$workshop['name']} - Ngành: {$workshop['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('production_lines', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'workshop_id' => $workshopId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật line sản xuất thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật line sản xuất: ' . $e->getMessage());
    }
}

/**
 * Delete production line
 */
function deleteLine() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data with full hierarchy info
    $currentData = $db->fetch("SELECT pl.*, w.name as workshop_name, w.code as workshop_code,
                                      i.name as industry_name, i.code as industry_code
                              FROM production_lines pl 
                              JOIN workshops w ON pl.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE pl.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy line sản xuất');
    }
    
    // Check if line has equipment
    $equipmentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE line_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentCount > 0) {
        throw new Exception('Không thể xóa line sản xuất này vì đang có ' . $equipmentCount . ' thiết bị thuộc line này');
    }
    
    // Check if line has machine types
    $machineTypesCount = $db->fetch(
        "SELECT COUNT(*) as count FROM machine_types WHERE line_id = ?", 
        [$id]
    )['count'];
    
    if ($machineTypesCount > 0) {
        throw new Exception('Không thể xóa line sản xuất này vì đang có ' . $machineTypesCount . ' dòng máy thuộc line này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete
        $sql = "DELETE FROM production_lines WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'production_lines', "Xóa line sản xuất: {$currentData['name']} ({$currentData['code']}) - Xưởng: {$currentData['workshop_name']} - Ngành: {$currentData['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('production_lines', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa line sản xuất thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa line sản xuất: ' . $e->getMessage());
    }
}

/**
 * Toggle production line status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data with hierarchy info
    $currentData = $db->fetch("SELECT pl.*, w.name as workshop_name, i.name as industry_name 
                              FROM production_lines pl 
                              JOIN workshops w ON pl.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE pl.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy line sản xuất');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE production_lines SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'production_lines', "Thay đổi trạng thái line sản xuất: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}
?>