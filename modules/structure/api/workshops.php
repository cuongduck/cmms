<?php
/**
 * Workshops API - /modules/structure/api/workshops.php
 * CRUD operations for Workshops module
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
            getWorkshopsList();
            break;
        case 'get':
            getWorkshop();
            break;
        case 'check_code':
            checkCode();
            break;
        case 'get_industries':
            getIndustries();
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
            createWorkshop();
            break;
        case 'update':
            updateWorkshop();
            break;
        case 'delete':
            deleteWorkshop();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get workshops list with filters and pagination
 */
function getWorkshopsList() {
    global $db;
    
    requirePermission('structure', 'view');
    
    // Get parameters
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    $industryId = (int)($_GET['industry_id'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC');
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'code', 'created_at', 'status', 'industry_name'];
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
        $whereConditions[] = "(w.name LIKE ? OR w.code LIKE ? OR w.description LIKE ? OR i.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "w.status = ?";
        $params[] = $status;
    }
    
    if ($industryId > 0) {
        $whereConditions[] = "w.industry_id = ?";
        $params[] = $industryId;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                FROM workshops w 
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
    } else {
        $orderBy = 'w.' . $sortBy;
    }
    
    // Get data
    $sql = "SELECT w.id, w.name, w.code, w.description, w.status, w.created_at, w.updated_at,
                   w.industry_id, i.name as industry_name, i.code as industry_code
            FROM workshops w 
            JOIN industries i ON w.industry_id = i.id 
            $whereClause 
            ORDER BY $orderBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $workshops = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($workshops as &$workshop) {
        $workshop['created_at_formatted'] = formatDateTime($workshop['created_at']);
        $workshop['updated_at_formatted'] = formatDateTime($workshop['updated_at']);
        $workshop['status_text'] = getStatusText($workshop['status']);
        $workshop['status_class'] = getStatusClass($workshop['status']);
        
        // Get production lines count
        $linesCount = $db->fetch(
            "SELECT COUNT(*) as count FROM production_lines WHERE workshop_id = ?", 
            [$workshop['id']]
        )['count'];
        $workshop['lines_count'] = $linesCount;
        
        // Get equipment count
        $equipmentCount = $db->fetch(
            "SELECT COUNT(*) as count FROM equipment WHERE workshop_id = ?", 
            [$workshop['id']]
        )['count'];
        $workshop['equipment_count'] = $equipmentCount;
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
        'workshops' => $workshops,
        'pagination' => $pagination,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'industry_id' => $industryId,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);
}

/**
 * Get single workshop
 */
function getWorkshop() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT w.*, i.name as industry_name, i.code as industry_code
            FROM workshops w 
            JOIN industries i ON w.industry_id = i.id 
            WHERE w.id = ?";
    $workshop = $db->fetch($sql, [$id]);
    
    if (!$workshop) {
        throw new Exception('Không tìm thấy xưởng');
    }
    
    // Get related data
    $workshop['lines_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM production_lines WHERE workshop_id = ?", 
        [$id]
    )['count'];
    
    $workshop['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE workshop_id = ?", 
        [$id]
    )['count'];
    
    successResponse($workshop);
}

/**
 * Check if code exists
 */
function checkCode() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $code = trim($_GET['code'] ?? '');
    $industryId = (int)($_GET['industry_id'] ?? 0);
    $excludeId = (int)($_GET['exclude_id'] ?? 0);
    
    if (empty($code)) {
        throw new Exception('Mã code không được trống');
    }
    
    if (!$industryId) {
        throw new Exception('Industry ID không hợp lệ');
    }
    
    $sql = "SELECT id FROM workshops WHERE code = ? AND industry_id = ?";
    $params = [$code, $industryId];
    
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
 * Create new workshop
 */
function createWorkshop() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $industryId = (int)($_POST['industry_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên xưởng không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên xưởng không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã xưởng không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã xưởng không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã xưởng chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$industryId) {
        $errors[] = 'Vui lòng chọn ngành';
    } else {
        // Check if industry exists and is active
        $industry = $db->fetch("SELECT id FROM industries WHERE id = ? AND status = 'active'", [$industryId]);
        if (!$industry) {
            $errors[] = 'Ngành không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within industry
    if (empty($errors)) {
        $sql = "SELECT id FROM workshops WHERE code = ? AND industry_id = ?";
        if ($db->fetch($sql, [$code, $industryId])) {
            $errors[] = 'Mã xưởng đã tồn tại trong ngành này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO workshops (name, code, industry_id, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $industryId,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $workshopId = $db->lastInsertId();
        
        // Get industry name for logging
        $industry = $db->fetch("SELECT name FROM industries WHERE id = ?", [$industryId]);
        
        // Log activity
        logActivity('create', 'workshops', "Tạo xưởng: $name ($code) - Ngành: {$industry['name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('workshops', $workshopId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'industry_id' => $industryId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $workshopId], 'Tạo xưởng thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo xưởng: ' . $e->getMessage());
    }
}
/**
 * Update workshop
 */
function updateWorkshop() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM workshops WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy xưởng');
    }
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $industryId = (int)($_POST['industry_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên xưởng không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên xưởng không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã xưởng không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã xưởng không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã xưởng chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!$industryId) {
        $errors[] = 'Vui lòng chọn ngành';
    } else {
        // Check if industry exists and is active
        $industry = $db->fetch("SELECT id FROM industries WHERE id = ? AND status = 'active'", [$industryId]);
        if (!$industry) {
            $errors[] = 'Ngành không hợp lệ hoặc không hoạt động';
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness within industry (exclude current record)
    if (empty($errors)) {
        $sql = "SELECT id FROM workshops WHERE code = ? AND industry_id = ? AND id != ?";
        if ($db->fetch($sql, [$code, $industryId, $id])) {
            $errors[] = 'Mã xưởng đã tồn tại trong ngành này';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE workshops 
                SET name = ?, code = ?, industry_id = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $industryId, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Get industry name for logging
        $industry = $db->fetch("SELECT name FROM industries WHERE id = ?", [$industryId]);
        
        // Log activity
        logActivity('update', 'workshops', "Cập nhật xưởng: $name ($code) - Ngành: {$industry['name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('workshops', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'industry_id' => $industryId,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật xưởng thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật xưởng: ' . $e->getMessage());
    }
}

/**
 * Delete workshop
 */
function deleteWorkshop() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT w.*, i.name as industry_name 
                              FROM workshops w 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE w.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy xưởng');
    }
    
    // Check if workshop has production lines
    $linesCount = $db->fetch(
        "SELECT COUNT(*) as count FROM production_lines WHERE workshop_id = ?", 
        [$id]
    )['count'];
    
    if ($linesCount > 0) {
        throw new Exception('Không thể xóa xưởng này vì đang có ' . $linesCount . ' line sản xuất thuộc xưởng này');
    }
    
    // Check if workshop has equipment
    $equipmentCount = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment WHERE workshop_id = ?", 
        [$id]
    )['count'];
    
    if ($equipmentCount > 0) {
        throw new Exception('Không thể xóa xưởng này vì đang có ' . $equipmentCount . ' thiết bị thuộc xưởng này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete
        $sql = "DELETE FROM workshops WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'workshops', "Xóa xưởng: {$currentData['name']} ({$currentData['code']}) - Ngành: {$currentData['industry_name']}", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('workshops', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa xưởng thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa xưởng: ' . $e->getMessage());
    }
}

/**
 * Toggle workshop status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT w.*, i.name as industry_name 
                              FROM workshops w 
                              JOIN industries i ON w.industry_id = i.id 
                              WHERE w.id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy xưởng');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE workshops SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'workshops', "Thay đổi trạng thái xưởng: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}
?>