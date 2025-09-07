<?php
/**
 * Parts API Handler - Rewritten Version without Mapping
 * /modules/bom/api/parts.php
 * API endpoint cho Parts operations
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';

// Helper function for checking duplicate codes
function checkPartCodeExists($code, $excludeId = null) {
    global $db;
    
    $sql = "SELECT id FROM parts WHERE part_code = ?";
    $params = [$code];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    return $db->fetch($sql, $params) !== false;
}

// Check login
try {
    requireLogin();
} catch (Exception $e) {
    errorResponse('Authentication required', 401);
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handlePartsList();
            break;
        
        case 'list_simple':
            handlePartsListSimple();
            break;
        
        case 'getDetails':
            handleGetPartDetails();
            break;
        
        case 'save':
            handleSavePart();
            break;
        
        case 'update':
            handleUpdatePart();
            break;
        
        case 'delete':
            handleDeletePart();
            break;
        
        case 'bulk_update':
            handleBulkUpdateParts();
            break;
        
        case 'bulk_delete':
            handleBulkDeleteParts();
            break;
        
        case 'search':
            handleSearchParts();
            break;
        
        case 'check_stock':
            handleCheckStock();
            break;
        
        case 'category_suppliers':
            handleCategorySuppliers();
            break;
        
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Parts API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}

/**
 * Get parts list with filters and pagination
 */
function handlePartsList() {
    global $db;
    
    $filters = [
        'category' => $_GET['category'] ?? '',
        'stock_status' => $_GET['stock_status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 20)))
    ];
    
    $offset = ($filters['page'] - 1) * $filters['limit'];
    
    // Build WHERE conditions
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($filters['category'])) {
        $whereConditions[] = 'p.category = ?';
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = '(p.part_code LIKE ? OR p.part_name LIKE ? OR p.description LIKE ?)';
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count first
    $countSql = "SELECT COUNT(*) as total FROM parts p WHERE $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $totalItems = $totalResult['total'];
    
    // Get parts list
    $sql = "SELECT p.*, 
                   0 as usage_count,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            WHERE $whereClause
            ORDER BY p.part_code
            LIMIT ? OFFSET ?";
    
    $params = array_merge($params, [$filters['limit'], $offset]);
    $parts = $db->fetchAll($sql, $params);
    
    // Pagination info
    $totalPages = ceil($totalItems / $filters['limit']);
    $pagination = [
        'current_page' => $filters['page'],
        'total_pages' => $totalPages,
        'per_page' => $filters['limit'],
        'total_items' => $totalItems
    ];
    
    successResponse(['parts' => $parts, 'pagination' => $pagination]);
}

/**
 * Get simple parts list
 */
function handlePartsListSimple() {
    global $db;
    
    $sql = "SELECT id, part_code, part_name, unit, unit_price, category
            FROM parts
            ORDER BY part_code";
    
    $parts = $db->fetchAll($sql);
    
    successResponse($parts);
}

/**
 * Get part details
 */
function handleGetPartDetails() {
    global $db;
    
    $partId = intval($_GET['id'] ?? 0);
    if (!$partId) {
        throw new Exception('Part ID is required');
    }
    
    $sql = "SELECT p.*, 
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            WHERE p.id = ?";
    
    $part = $db->fetch($sql, [$partId]);
    
    if (!$part) {
        throw new Exception('Part not found');
    }
    
    successResponse($part);
}

/**
 * Save new part
 */
function handleSavePart() {
    requirePermission('bom', 'create');
    global $db;
    
    $data = [
        'part_code' => strtoupper(trim($_POST['part_code'] ?? '')),
        'part_name' => trim($_POST['part_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'unit' => trim($_POST['unit'] ?? 'Cái'),
        'category' => trim($_POST['category'] ?? ''),
        'specifications' => trim($_POST['specifications'] ?? ''),
        'manufacturer' => trim($_POST['manufacturer'] ?? ''),
        'supplier_code' => trim($_POST['supplier_code'] ?? ''),
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'unit_price' => floatval($_POST['unit_price'] ?? 0),
        'min_stock' => floatval($_POST['min_stock'] ?? 0),
        'max_stock' => floatval($_POST['max_stock'] ?? 0),
        'lead_time' => intval($_POST['lead_time'] ?? 0),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validate required fields
    if (empty($data['part_code'])) {
        throw new Exception('Mã linh kiện không được để trống');
    }
    
    if (empty($data['part_name'])) {
        throw new Exception('Tên linh kiện không được để trống');
    }
    
    // Check for duplicate part code
    if (checkPartCodeExists($data['part_code'])) {
        throw new Exception('Mã linh kiện đã tồn tại');
    }
    
    $db->beginTransaction();
    
    try {
        // INSERT new part
        $sql = "INSERT INTO parts 
                (part_code, part_name, description, unit, category, 
                 specifications, manufacturer, supplier_code, supplier_name, 
                 unit_price, min_stock, max_stock, lead_time, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['part_code'], $data['part_name'], $data['description'], $data['unit'],
            $data['category'], $data['specifications'], $data['manufacturer'], 
            $data['supplier_code'], $data['supplier_name'], $data['unit_price'],
            $data['min_stock'], $data['max_stock'], $data['lead_time'], $data['notes']
        ];
        
        $db->execute($sql, $params);
        $partId = $db->lastInsertId();
        
        // Log activity
        logActivity('create_part', 'bom', "Created part: {$data['part_code']} - {$data['part_name']}");
        
        $db->commit();
        
        successResponse(['part_id' => $partId], 'Tạo linh kiện thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Update part
 */
function handleUpdatePart() {
    requirePermission('bom', 'edit');
    global $db;
    
    $partId = intval($_POST['part_id'] ?? 0);
    if (!$partId) {
        throw new Exception('Part ID is required');
    }
    
    $data = [
        'part_code' => strtoupper(trim($_POST['part_code'] ?? '')),
        'part_name' => trim($_POST['part_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'unit' => trim($_POST['unit'] ?? 'Cái'),
        'category' => trim($_POST['category'] ?? ''),
        'specifications' => trim($_POST['specifications'] ?? ''),
        'manufacturer' => trim($_POST['manufacturer'] ?? ''),
        'supplier_code' => trim($_POST['supplier_code'] ?? ''),
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'unit_price' => floatval($_POST['unit_price'] ?? 0),
        'min_stock' => floatval($_POST['min_stock'] ?? 0),
        'max_stock' => floatval($_POST['max_stock'] ?? 0),
        'lead_time' => intval($_POST['lead_time'] ?? 0),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validate required fields
    if (empty($data['part_code'])) {
        throw new Exception('Mã linh kiện không được để trống');
    }
    
    if (empty($data['part_name'])) {
        throw new Exception('Tên linh kiện không được để trống');
    }
    
    // Check for duplicate part code
    if (checkPartCodeExists($data['part_code'], $partId)) {
        throw new Exception('Mã linh kiện đã tồn tại');
    }
    
    $db->beginTransaction();
    
    try {
        // Update part
        $sql = "UPDATE parts SET 
                part_code = ?, part_name = ?, description = ?, unit = ?, category = ?, 
                specifications = ?, manufacturer = ?, supplier_code = ?, supplier_name = ?, 
                unit_price = ?, min_stock = ?, max_stock = ?, lead_time = ?, notes = ?, 
                updated_at = NOW() 
                WHERE id = ?";
        
        $params = [
            $data['part_code'], $data['part_name'], $data['description'], $data['unit'],
            $data['category'], $data['specifications'], $data['manufacturer'], 
            $data['supplier_code'], $data['supplier_name'], $data['unit_price'],
            $data['min_stock'], $data['max_stock'], $data['lead_time'], $data['notes'],
            $partId
        ];
        
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update_part', 'bom', "Updated part: {$data['part_code']} - {$data['part_name']}");
        
        $db->commit();
        
        successResponse([], 'Cập nhật linh kiện thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Delete part
 */
function handleDeletePart() {
    requirePermission('bom', 'delete');
    global $db;
    
    $partId = intval($_POST['id'] ?? 0);
    if (!$partId) {
        throw new Exception('Part ID is required');
    }
    
    $db->beginTransaction();
    
    try {
        $db->execute("DELETE FROM parts WHERE id = ?", [$partId]);
        
        // Log activity
        logActivity('delete_part', 'bom', "Deleted part ID: $partId");
        
        $db->commit();
        
        successResponse([], 'Xóa linh kiện thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Bulk update parts
 */
function handleBulkUpdateParts() {
    requirePermission('bom', 'edit');
    global $db;
    
    $partIds = json_decode($_POST['part_ids'] ?? '[]', true);
    if (empty($partIds) || !is_array($partIds)) {
        throw new Exception('Part IDs are required');
    }
    
    $updates = [];
    $params = [];
    
    if (isset($_POST['unit_price']) && $_POST['unit_price'] !== '') {
        $updates[] = 'unit_price = ?';
        $params[] = floatval($_POST['unit_price']);
    }
    
    if (isset($_POST['min_stock']) && $_POST['min_stock'] !== '') {
        $updates[] = 'min_stock = ?';
        $params[] = floatval($_POST['min_stock']);
    }
    
    if (isset($_POST['max_stock']) && $_POST['max_stock'] !== '') {
        $updates[] = 'max_stock = ?';
        $params[] = floatval($_POST['max_stock']);
    }
    
    if (empty($updates)) {
        throw new Exception('No fields to update');
    }
    
    $db->beginTransaction();
    
    try {
        // Build WHERE clause for selected parts
        $placeholders = str_repeat('?,', count($partIds) - 1) . '?';
        $whereParams = array_merge($params, $partIds);
        
        $sql = "UPDATE parts SET " . implode(', ', $updates) . ", updated_at = NOW() 
                WHERE id IN ($placeholders)";
        
        $db->execute($sql, $whereParams);
        
        $affectedRows = count($partIds);
        
        // Log activity
        logActivity('bulk_update_parts', 'bom', "Bulk updated $affectedRows parts");
        
        $db->commit();
        
        successResponse([], "Cập nhật thành công $affectedRows linh kiện");
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Bulk delete parts
 */
function handleBulkDeleteParts() {
    requirePermission('bom', 'delete');
    global $db;
    
    $partIds = json_decode($_POST['part_ids'] ?? '[]', true);
    if (empty($partIds) || !is_array($partIds)) {
        throw new Exception('Part IDs are required');
    }
    
    $db->beginTransaction();
    
    try {
        $placeholders = str_repeat('?,', count($partIds) - 1) . '?';
        
        // Delete parts
        $db->execute("DELETE FROM parts WHERE id IN ($placeholders)", $partIds);
        
        $affectedRows = count($partIds);
        
        // Log activity
        logActivity('bulk_delete_parts', 'bom', "Bulk deleted $affectedRows parts");
        
        $db->commit();
        
        successResponse([], "Xóa thành công $affectedRows linh kiện");
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Search parts (for autocomplete)
 */
function handleSearchParts() {
    global $db;
    
    $query = trim($_GET['q'] ?? '');
    $limit = min(50, max(5, intval($_GET['limit'] ?? 10)));
    
    if (strlen($query) < 2) {
        successResponse([]);
        return;
    }
    
    $sql = "SELECT p.id, p.part_code, p.part_name, p.unit, p.unit_price, p.category,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            WHERE p.part_code LIKE ? OR p.part_name LIKE ?
            ORDER BY p.part_code
            LIMIT ?";
    
    $searchTerm = '%' . $query . '%';
    $parts = $db->fetchAll($sql, [$searchTerm, $searchTerm, $limit]);
    
    successResponse($parts);
}

/**
 * Check stock for specific parts
 */
function handleCheckStock() {
    global $db;
    
    $partIds = json_decode($_POST['part_ids'] ?? '[]', true);
    if (empty($partIds) || !is_array($partIds)) {
        throw new Exception('Part IDs are required');
    }
    
    $placeholders = str_repeat('?,', count($partIds) - 1) . '?';
    
    $sql = "SELECT p.id, p.part_code, p.part_name, p.min_stock,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            WHERE p.id IN ($placeholders)";
    
    $stocks = $db->fetchAll($sql, $partIds);
    
    successResponse($stocks);
}

/**
 * Get suppliers by category
 */
function handleCategorySuppliers() {
    global $db;
    
    $category = trim($_GET['category'] ?? '');
    if (empty($category)) {
        successResponse(['suppliers' => []]);
        return;
    }
    
    $sql = "SELECT DISTINCT supplier_code as code, supplier_name as name 
            FROM parts 
            WHERE category = ? 
            AND supplier_code IS NOT NULL 
            AND supplier_name IS NOT NULL 
            ORDER BY supplier_name 
            LIMIT 20";
    
    $suppliers = $db->fetchAll($sql, [$category]);
    
    successResponse(['suppliers' => $suppliers]);
}
?>