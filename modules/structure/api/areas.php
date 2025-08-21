<?php
/**
 * Areas API - /modules/structure/api/areas.php
 * CRUD operations for Areas module
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
            getAreasList();
            break;
        case 'get':
            getArea();
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
        case 'get_lines':
            getLines();
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
            createArea();
            break;
        case 'update':
            updateArea();
            break;
        case 'delete':
            deleteArea();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get areas list with filters and pagination
 */
function getAreasList() {
    global $db;
    
    requirePermission('structure', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $industryId = (int)($_GET['industry_id'] ?? 0);
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    $lineId = (int)($_GET['line_id'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'industry_name', 'workshop_name', 'line_name'];
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
        $whereConditions[] = "(a.name LIKE ? OR a.code LIKE ? OR a.description LIKE ? OR pl.name LIKE ? OR w.name LIKE ? OR i.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "a.status = ?";
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
    
    if ($lineId > 0) {
        $whereConditions[] = "a.line_id = ?";
        $params[] = $lineId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                FROM areas a 
                JOIN production_lines pl ON a.line_id = pl.id 
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
    } elseif ($sortBy === 'line_name') {
        $orderBy = 'pl.name';
    } else {
        $orderBy = 'a.' . $sortBy;
    }
    
    // Get data
    $sql = "SELECT a.id, a.name, a.code, a.description, a.status, a.created_at, a.updated_at,
                   a.line_id, pl.name as line_name, pl.code as line_code,
                   pl.workshop_id, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM areas a 
            JOIN production_lines pl ON a.line_id = pl.id 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            $whereClause 
            ORDER BY $orderBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $areas = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($areas as &$area) {
        $area['created_at_formatted'] = formatDateTime($area['created_at']);
        $area['updated_at_formatted'] = formatDateTime($area['updated_at']);
        $area['status_text'] = getStatusText($area['status']);
        $area['status_class'] = getStatusClass($area['status']);
        
        // Get machine types count
        $machineTypesCount = $db->fetch(
            "SELECT COUNT(*) as count FROM machine_types WHERE area_id = ?", 
            [$area['id']]
        )['count'];
        $area['machine_types_count'] = $machineTypesCount;
        
        // Get equipment count
        $equipmentCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment WHERE area_id = ?", 
            [$area['id']]
        )['count'];
        $area['equipment_count'] = $equipmentCount;
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
        'areas' => $areas,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'line_id' => $lineId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single area
 */
function getArea() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT a.*, pl.name as line_name, pl.code as line_code,
                   pl.workshop_id, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM areas a 
            JOIN production_lines pl ON a.line_id = pl.id 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            WHERE a.id = ?";
    $area = $db->fetch($sql, [$id]);
    
    if (!$area) {
        throw new Exception('Không tìm thấy khu vực');
    }
    
    // Get related data
    $area['machine_types_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM machine_types WHERE area_id = ?", 
        [$id]
    )['count'];
    
    $area['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE area_id = ?", 
        [$id]
    )['count'];
    
    successResponse($area);
}

/**
 * Check if code exists
 */
function checkCode() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $code = trim($_GET['code'] ?? '');
    $lineId = (int)($_GET['line_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Mã code không được trống');
    }
    
    if (!$lineId) {
        throw new Exception('Line ID không hợp lệ');
    }
    
    $sql = "SELECT id FROM areas WHERE code = ? AND line_id = ?";
    $params = [$code, $lineId];
    
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
 * Get production lines for dropdown
 */
function getLines() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    
    $sql = "SELECT pl.id, pl.name, pl.code, pl.workshop_id, 
                   w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM production_lines pl 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            WHERE pl.status = 'active'";
    $params = [];
    
    if ($workshopId > 0) {
        $sql .= " AND pl.workshop_id = ?";
        $params[] = $workshopId;
    }
    
    $sql .= " ORDER BY i.name, w.name, pl.name";
    
    $lines = $db->fetchAll($sql, $params);
    
    successResponse(['lines' => $lines]);
}

/**
 * Create new area
 */
function createArea() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $lineId = (int)($_POST['line_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên khu vực không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên khu vực không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã khu vực không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã khu vực không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã khu vực chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$lineId) {
        $errors[] = 'Vui lòng chọn line sản xuất';
    } else {
        // Check if line exists and is active with full hierarchy
        $line = $db->fetch("SELECT pl.*, w.name as workshop_name, i.name as industry_name 
                           FROM production_lines pl 
                           JOIN workshops w ON pl.workshop_id = w.id 
                           JOIN industries i ON w.industry_id = i.id 
                           WHERE pl.id = ? AND pl.status = 'active'", [$lineId]);
        if (!$line) {
            $errors[] = 'Line sản xuất không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within line
    if (empty($errors)) {
        $sql = "SELECT id FROM areas WHERE code = ? AND line_id = ?";
        if ($db->fetch($sql, [$code, $lineId])) {
            $errors[] = 'Mã khu vực đã tồn tại trong line này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO areas (name, code, line_id, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $lineId,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $areaId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'areas', "Tạo khu vực: $name ($code) - Line: {$line['name']} - Xưởng: {$line['workshop_name']} - Ngành: {$line['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('areas', $areaId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'line_id' => $lineId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $areaId], 'Tạo khu vực thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo khu vực: ' . $e->getMessage());
    }
}
/**
 * Update area
 */
function updateArea() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM areas WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy khu vực');
    }
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $lineId = (int)($_POST['line_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên khu vực không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên khu vực không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã khu vực không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã khu vực không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã khu vực chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$lineId) {
        $errors[] = 'Vui lòng chọn line sản xuất';
    } else {
        // Check if line exists and is active with full hierarchy
        $line = $db->fetch("SELECT pl.*, w.name as workshop_name, i.name as industry_name 
                           FROM production_lines pl 
                           JOIN workshops w ON pl.workshop_id = w.id 
                           JOIN industries i ON w.industry_id = i.id 
                           WHERE pl.id = ? AND pl.status = 'active'", [$lineId]);
        if (!$line) {
            $errors[] = 'Line sản xuất không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within line (exclude current record)
    if (empty($errors)) {
        $sql = "SELECT id FROM areas WHERE code = ? AND line_id = ? AND id != ?";
        if ($db->fetch($sql, [$code, $lineId, $id])) {
            $errors[] = 'Mã khu vực đã tồn tại trong line này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE areas 
                SET name = ?, code = ?, line_id = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $lineId, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update', 'areas', "Cập nhật khu vực: $name ($code) - Line: {$line['name']} - Xưởng: {$line['workshop_name']} - Ngành: {$line['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('areas', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'line_id' => $lineId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật khu vực thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật khu vực: ' . $e->getMessage());
    }
}

/**
 * Delete area
 */
function deleteArea() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data with full hierarchy info
    $currentData = $db->fetch("SELECT a.*, pl.name as line_name, pl.code as line_code,
                                      w.name as workshop_name, w.code as workshop_code,
                                      i.name as industry_name, i.code as industry_code
                              FROM areas a 
                              JOIN production_lines pl ON a.line_id = pl.id 
                              JOIN workshops w ON pl.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE a.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy khu vực');
    }
    
    // Check if area has machine types
    $machineTypesCount = $db->fetch(
        "SELECT COUNT(*) as count FROM machine_types WHERE area_id = ?", 
        [$id]
    )['count'];
    
    if ($machineTypesCount > 0) {
        throw new Exception('Không thể xóa khu vực này vì đang có ' . $machineTypesCount . ' dòng máy thuộc khu vực này');
    }
    
    // Check if area has equipment
    $equipmentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE area_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentCount > 0) {
        throw new Exception('Không thể xóa khu vực này vì đang có ' . $equipmentCount . ' thiết bị thuộc khu vực này');
    }
    
    // Check if area has equipment groups
    $equipmentGroupsCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment_groups eg 
         JOIN machine_types mt ON eg.machine_type_id = mt.id 
         WHERE mt.area_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentGroupsCount > 0) {
        throw new Exception('Không thể xóa khu vực này vì đang có ' . $equipmentGroupsCount . ' cụm thiết bị thuộc khu vực này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete
        $sql = "DELETE FROM areas WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'areas', "Xóa khu vực: {$currentData['name']} ({$currentData['code']}) - Line: {$currentData['line_name']} - Xưởng: {$currentData['workshop_name']} - Ngành: {$currentData['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('areas', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa khu vực thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa khu vực: ' . $e->getMessage());
    }
}

/**
 * Toggle area status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data with hierarchy info
    $currentData = $db->fetch("SELECT a.*, pl.name as line_name, w.name as workshop_name, i.name as industry_name 
                              FROM areas a 
                              JOIN production_lines pl ON a.line_id = pl.id 
                              JOIN workshops w ON pl.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE a.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy khu vực');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE areas SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'areas', "Thay đổi trạng thái khu vực: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}
?>