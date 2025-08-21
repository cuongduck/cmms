<?php
/**
 * Industries API - /modules/structure/api/industries.php
 * CRUD operations for Industries module
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
            getIndustriesList();
            break;
        case 'get':
            getIndustry();
            break;
        case 'check_code':
            checkCode();
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
            createIndustry();
            break;
        case 'update':
            updateIndustry();
            break;
        case 'delete':
            deleteIndustry();
            break;
        case 'toggle_status':
            toggleStatus();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Get industries list with filters and pagination
 */
function getIndustriesList() {
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
    $countSql = "SELECT COUNT(*) as total FROM industries $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($total / $limit);
    
    // Get data
    $sql = "SELECT id, name, code, description, status, created_at, updated_at 
            FROM industries 
            $whereClause 
            ORDER BY $sortBy $sortOrder 
            LIMIT $limit OFFSET $offset";
    
    $industries = $db->fetchAll($sql, $params);
    
    // Format data
    foreach ($industries as &$industry) {
        $industry['created_at_formatted'] = formatDateTime($industry['created_at']);
        $industry['updated_at_formatted'] = formatDateTime($industry['updated_at']);
        $industry['status_text'] = getStatusText($industry['status']);
        $industry['status_class'] = getStatusClass($industry['status']);
        
        // Get workshop count
        $workshopCount = $db->fetch(
            "SELECT COUNT(*) as count FROM workshops WHERE industry_id = ?", 
            [$industry['id']]
        )['count'];
        $industry['workshop_count'] = $workshopCount;
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
        'industries' => $industries,
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
 * Get single industry
 */
function getIndustry() {
    global $db;
    
    requirePermission('structure', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    $sql = "SELECT * FROM industries WHERE id = ?";
    $industry = $db->fetch($sql, [$id]);
    
    if (!$industry) {
        throw new Exception('Không tìm thấy ngành');
    }
    
    // Get related data
    $industry['workshop_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM workshops WHERE industry_id = ?", 
        [$id]
    )['count'];
    
    $industry['equipment_count'] = $db->fetch(
        "SELECT COUNT(*) as count FROM equipment e 
         JOIN workshops w ON e.workshop_id = w.id 
         WHERE w.industry_id = ?", 
        [$id]
    )['count'];
    
    successResponse($industry);
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
    
    $sql = "SELECT id FROM industries WHERE code = ?";
    $params = [$code];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $exists = $db->fetch($sql, $params);
    
    successResponse(['exists' => (bool)$exists]);
}

/**
 * Create new industry
 */
function createIndustry() {
    global $db;
    
    requirePermission('structure', 'create');
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên ngành không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên ngành không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã ngành không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã ngành không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã ngành chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness
    if (empty($errors)) {
        if (isCodeExists('industries', $code)) {
            $errors[] = 'Mã ngành đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO industries (name, code, description, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $name,
            $code,
            $description,
            $status,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $industryId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'industries', "Tạo ngành: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('industries', $industryId, 'create', null, [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse(['id' => $industryId], 'Tạo ngành thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi tạo ngành: ' . $e->getMessage());
    }
}

/**
 * Update industry
 */
function updateIndustry() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM industries WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy ngành');
    }
    
    // Get and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Tên ngành không được trống';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Tên ngành không được quá 255 ký tự';
    }
    
    if (empty($code)) {
        $errors[] = 'Mã ngành không được trống';
    } elseif (strlen($code) > 10) {
        $errors[] = 'Mã ngành không được quá 10 ký tự';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $errors[] = 'Mã ngành chỉ được chứa chữ hoa, số và dấu gạch dưới';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    if (strlen($description) > 1000) {
        $errors[] = 'Mô tả không được quá 1000 ký tự';
    }
    
    // Check code uniqueness (exclude current record)
    if (empty($errors)) {
        if (isCodeExists('industries', $code, $id)) {
            $errors[] = 'Mã ngành đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        errorResponse(implode(', ', $errors));
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE industries 
                SET name = ?, code = ?, description = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [$name, $code, $description, $status, $id];
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update', 'industries', "Cập nhật ngành: $name ($code)", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('industries', $id, 'update', $currentData, [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'status' => $status
        ]);
        
        $db->commit();
        
        successResponse([], 'Cập nhật ngành thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi cập nhật ngành: ' . $e->getMessage());
    }
}

/**
 * Delete industry
 */
function deleteIndustry() {
    global $db;
    
    requirePermission('structure', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM industries WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy ngành');
    }
    
    // Check if industry has workshops
    $workshopCount = $db->fetch(
        "SELECT COUNT(*) as count FROM workshops WHERE industry_id = ?", 
        [$id]
    )['count'];
    
    if ($workshopCount > 0) {
        throw new Exception('Không thể xóa ngành này vì đang có ' . $workshopCount . ' xưởng thuộc ngành này');
    }
    
    try {
        $db->beginTransaction();
        
        // Hard delete since 'deleted' status doesn't exist
        $sql = "DELETE FROM industries WHERE id = ?";
        $db->execute($sql, [$id]);
        
        // Log activity
        logActivity('delete', 'industries', "Xóa ngành: {$currentData['name']} ({$currentData['code']})", getCurrentUser()['id']);
        
        // Create audit trail
        createAuditTrail('industries', $id, 'delete', $currentData, null);
        
        $db->commit();
        
        successResponse([], 'Xóa ngành thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi xóa ngành: ' . $e->getMessage());
    }
}

/**
 * Toggle industry status
 */
function toggleStatus() {
    global $db;
    
    requirePermission('structure', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID không hợp lệ');
    }
    
    // Get current data
    $currentData = $db->fetch("SELECT * FROM industries WHERE id = ?", [$id]);
    if (!$currentData) {
        throw new Exception('Không tìm thấy ngành');
    }
    
    $newStatus = $currentData['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE industries SET status = ?, updated_at = NOW() WHERE id = ?";
        $db->execute($sql, [$newStatus, $id]);
        
        // Log activity
        $statusText = $newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa';
        logActivity('update', 'industries', "Thay đổi trạng thái ngành: {$currentData['name']} - $statusText", getCurrentUser()['id']);
        
        $db->commit();
        
        successResponse(['new_status' => $newStatus], 'Thay đổi trạng thái thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Lỗi khi thay đổi trạng thái: ' . $e->getMessage());
    }
}
?>