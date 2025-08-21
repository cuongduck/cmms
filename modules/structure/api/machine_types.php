<?php
/**
 * Machine Types API - /modules/structure/api/machine_types.php
 * CRUD operations for Machine Types module
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
        case 'get_industries':
            getIndustries();
            break;
        case 'get_workshops':
            getWorkshops();
            break;
        case 'get_lines':
            getLines();
            break;
        case 'get_areas':
            getAreas();
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
    $industryId = (int)($_GET['industry_id'] ?? 0);
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    $lineId = (int)($_GET['line_id'] ?? 0);
    $areaId = (int)($_GET['area_id'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'industry_name', 'workshop_name', 'line_name', 'area_name'];
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
        $whereConditions[] = "(mt.name LIKE ? OR mt.code LIKE ? OR mt.description LIKE ? OR a.name LIKE ? OR pl.name LIKE ? OR w.name LIKE ? OR i.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "mt.status = ?";
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
        $whereConditions[] = "mt.line_id = ?";
        $params[] = $lineId;
    }
    
    if ($areaId > 0) {
        $whereConditions[] = "mt.area_id = ?";
        $params[] = $areaId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                FROM machine_types mt 
                JOIN workshops w ON mt.workshop_id = w.id 
                JOIN industries i ON w.industry_id = i.id 
                LEFT JOIN production_lines pl ON mt.line_id = pl.id 
                LEFT JOIN areas a ON mt.area_id = a.id 
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
    } elseif ($sortBy === 'area_name') {
        $orderBy = 'a.name';
    } else {
        $orderBy = 'mt.' . $sortBy;
    }
    
    // Get data
    $sql = "SELECT mt.id, mt.name, mt.code, mt.description, mt.status, mt.created_at, mt.updated_at,
                   mt.workshop_id, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code,
                   mt.line_id, pl.name as line_name, pl.code as line_code,
                   mt.area_id, a.name as area_name, a.code as area_code
            FROM machine_types mt 
            JOIN workshops w ON mt.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            LEFT JOIN production_lines pl ON mt.line_id = pl.id 
            LEFT JOIN areas a ON mt.area_id = a.id 
            $whereClause 
            ORDER BY $orderBy $sortOrder 
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
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'line_id' => $lineId,
            'area_id' => $areaId,
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
    
    $sql = "SELECT mt.*, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code,
                   pl.name as line_name, pl.code as line_code,
                   a.name as area_name, a.code as area_code
            FROM machine_types mt 
            JOIN workshops w ON mt.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            LEFT JOIN production_lines pl ON mt.line_id = pl.id 
            LEFT JOIN areas a ON mt.area_id = a.id 
            WHERE mt.id = ?";
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
    $workshopId = (int)($_GET['workshop_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Mã code không được trống');
    }
    
    if (!$workshopId) {
        throw new Exception('Workshop ID không hợp lệ');
    }
    
    $sql = "SELECT id FROM machine_types WHERE code = ? AND workshop_id = ?";
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
 * Get areas for dropdown
 */
function getAreas() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $lineId = (int)($_GET['line_id'] ?? 0);
    
    $sql = "SELECT a.id, a.name, a.code, a.line_id, 
                   pl.name as line_name, pl.code as line_code,
                   pl.workshop_id, w.name as workshop_name, w.code as workshop_code,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM areas a 
            JOIN production_lines pl ON a.line_id = pl.id 
            JOIN workshops w ON pl.workshop_id = w.id 
            JOIN industries i ON w.industry_id = i.id 
            WHERE a.status = 'active'";
    $params = [];
    
    if ($lineId > 0) {
        $sql .= " AND a.line_id = ?";
        $params[] = $lineId;
    }
    
    $sql .= " ORDER BY i.name, w.name, pl.name, a.name";
    
    $areas = $db->fetchAll($sql, $params);
    
    successResponse(['areas' => $areas]);
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
    $industryId = (int)($_POST['industry_id'] ?? 0);
    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $lineId = !empty($_POST['line_id']) ? (int)$_POST['line_id'] : null;
    $areaId = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;
    $description = trim($_POST['description'] ?? '');
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
    
    if (!$industryId) {
        $errors[] = 'Vui lòng chọn ngành';
    }
    
    if (!$workshopId) {
        $errors[] = 'Vui lòng chọn xưởng';
    } else {
        // Validate hierarchy consistency
        $workshop = $db->fetch("SELECT w.*, i.name as industry_name 
                               FROM workshops w 
                               JOIN industries i ON w.industry_id = i.id 
                               WHERE w.id = ? AND w.status = 'active' AND w.industry_id = ?", 
                               [$workshopId, $industryId]);
        if (!$workshop) {
            $errors[] = 'Xưởng không hợp lệ hoặc không thuộc ngành đã chọn';
        }
    }
    
    // Validate line if specified
    if ($lineId) {
        $line = $db->fetch("SELECT id FROM production_lines WHERE id = ? AND workshop_id = ? AND status = 'active'", 
                          [$lineId, $workshopId]);
        if (!$line) {
            $errors[] = 'Line sản xuất không hợp lệ hoặc không thuộc xưởng đã chọn';
        }
    }
    
    // Validate area if specified
    if ($areaId) {
        if (!$lineId) {
            $errors[] = 'Vui lòng chọn line sản xuất trước khi chọn khu vực';
        } else {
            $area = $db->fetch("SELECT id FROM areas WHERE id = ? AND line_id = ? AND status = 'active'", 
                              [$areaId, $lineId]);
            if (!$area) {
                $errors[] = 'Khu vực không hợp lệ hoặc không thuộc line đã chọn';
            }
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
        $sql = "SELECT id FROM machine_types WHERE code = ? AND workshop_id = ?";
        if ($db->fetch($sql, [$code, $workshopId])) {
            $errors[] = 'Mã dòng máy đã tồn tại trong xưởng này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO machine_types (name, code, industry_id, workshop_id, line_id, area_id, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $industryId,
            $workshopId,
            $lineId,
            $areaId,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $machineTypeId = $db->lastInsertId();
        
        // Build hierarchy info for logging
        $hierarchyInfo = "{$workshop['industry_name']} - {$workshop['name']}";
        if ($lineId && $areaId) {
            $lineArea = $db->fetch("SELECT pl.name as line_name, a.name as area_name 
                                   FROM production_lines pl 
                                   LEFT JOIN areas a ON a.line_id = pl.id AND a.id = ? 
                                   WHERE pl.id = ?", [$areaId, $lineId]);
            if ($lineArea) {
                $hierarchyInfo .= " - {$lineArea['line_name']} - {$lineArea['area_name']}";
            }
        } elseif ($lineId) {
            $line = $db->fetch("SELECT name FROM production_lines WHERE id = ?", [$lineId]);
            if ($line) {
                $hierarchyInfo .= " - {$line['name']}";
            }
        }
        
        // Log activity
        logActivity('create', 'machine_types', "Tạo dòng máy: $name ($code) - $hierarchyInfo", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('machine_types', $machineTypeId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'line_id' => $lineId,
            'area_id' => $areaId,
            'description' => $description,
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
    $industryId = (int)($_POST['industry_id'] ?? 0);
    $workshopId = (int)($_POST['workshop_id'] ?? 0);
    $lineId = !empty($_POST['line_id']) ? (int)$_POST['line_id'] : null;
    $areaId = !empty($_POST['area_id']) ? (int)$_POST['area_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation (same as create, but with exclude current record for code check)
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
    
    if (!$industryId) {
        $errors[] = 'Vui lòng chọn ngành';
    }
    
    if (!$workshopId) {
        $errors[] = 'Vui lòng chọn xưởng';
    } else {
        // Validate hierarchy consistency
        $workshop = $db->fetch("SELECT w.*, i.name as industry_name 
                               FROM workshops w 
                               JOIN industries i ON w.industry_id = i.id 
                               WHERE w.id = ? AND w.status = 'active' AND w.industry_id = ?", 
                               [$workshopId, $industryId]);
        if (!$workshop) {
            $errors[] = 'Xưởng không hợp lệ hoặc không thuộc ngành đã chọn';
        }
    }
    
    // Validate line if specified
    if ($lineId) {
        $line = $db->fetch("SELECT id FROM production_lines WHERE id = ? AND workshop_id = ? AND status = 'active'", 
                          [$lineId, $workshopId]);
        if (!$line) {
            $errors[] = 'Line sản xuất không hợp lệ hoặc không thuộc xưởng đã chọn';
        }
    }
    
    // Validate area if specified
    if ($areaId) {
        if (!$lineId) {
            $errors[] = 'Vui lòng chọn line sản xuất trước khi chọn khu vực';
        } else {
            $area = $db->fetch("SELECT id FROM areas WHERE id = ? AND line_id = ? AND status = 'active'", 
                              [$areaId, $lineId]);
            if (!$area) {
                $errors[] = 'Khu vực không hợp lệ hoặc không thuộc line đã chọn';
            }
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
        $sql = "SELECT id FROM machine_types WHERE code = ? AND workshop_id = ? AND id != ?";
        if ($db->fetch($sql, [$code, $workshopId, $id])) {
            $errors[] = 'Mã dòng máy đã tồn tại trong xưởng này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE machine_types 
                SET name = ?, code = ?, industry_id = ?, workshop_id = ?, line_id = ?, area_id = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $industryId, $workshopId, $lineId, $areaId, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Build hierarchy info for logging
        $hierarchyInfo = "{$workshop['industry_name']} - {$workshop['name']}";
        if ($lineId && $areaId) {
            $lineArea = $db->fetch("SELECT pl.name as line_name, a.name as area_name 
                                   FROM production_lines pl 
                                   LEFT JOIN areas a ON a.line_id = pl.id AND a.id = ? 
                                   WHERE pl.id = ?", [$areaId, $lineId]);
            if ($lineArea) {
                $hierarchyInfo .= " - {$lineArea['line_name']} - {$lineArea['area_name']}";
            }
        } elseif ($lineId) {
            $line = $db->fetch("SELECT name FROM production_lines WHERE id = ?", [$lineId]);
            if ($line) {
                $hierarchyInfo .= " - {$line['name']}";
            }
        }
        
        // Log activity
        logActivity('update', 'machine_types', "Cập nhật dòng máy: $name ($code) - $hierarchyInfo", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('machine_types', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'industry_id' => $industryId,
            'workshop_id' => $workshopId,
            'line_id' => $lineId,
            'area_id' => $areaId,
            'description' => $description,
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
    
    // Get current data with full hierarchy info
    $currentData = $db->fetch("SELECT mt.*, w.name as workshop_name, w.code as workshop_code,
                                      i.name as industry_name, i.code as industry_code,
                                      pl.name as line_name, pl.code as line_code,
                                      a.name as area_name, a.code as area_code
                              FROM machine_types mt 
                              JOIN workshops w ON mt.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              LEFT JOIN production_lines pl ON mt.line_id = pl.id 
                              LEFT JOIN areas a ON mt.area_id = a.id 
                              WHERE mt.id = ?", [$id]);
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
        
        // Build hierarchy info for logging
        $hierarchyInfo = "{$currentData['industry_name']} - {$currentData['workshop_name']}";
        if ($currentData['line_name'] && $currentData['area_name']) {
            $hierarchyInfo .= " - {$currentData['line_name']} - {$currentData['area_name']}";
        } elseif ($currentData['line_name']) {
            $hierarchyInfo .= " - {$currentData['line_name']}";
        }
        
        // Log activity
        logActivity('delete', 'machine_types', "Xóa dòng máy: {$currentData['name']} ({$currentData['code']}) - $hierarchyInfo", getCurrentUser()['id']);
        
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
    
    // Get current data with hierarchy info
    $currentData = $db->fetch("SELECT mt.*, w.name as workshop_name, i.name as industry_name,
                                      pl.name as line_name, a.name as area_name
                              FROM machine_types mt 
                              JOIN workshops w ON mt.workshop_id = w.id 
                              JOIN industries i ON w.industry_id = i.id 
                              LEFT JOIN production_lines pl ON mt.line_id = pl.id 
                              LEFT JOIN areas a ON mt.area_id = a.id 
                              WHERE mt.id = ?", [$id]);
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
?>