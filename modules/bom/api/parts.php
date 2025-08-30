<?php
/**
 * Parts API Handler
 * /modules/bom/api/parts.php
 * API endpoint cho Parts operations
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

// Check login
requireLogin();

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
            
        case 'search':
            handleSearchParts();
            break;
            
        case 'check_stock':
            handleCheckStock();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
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
    $havingConditions = [];
    
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
    
    // Base query with stock information
    $baseQuery = "FROM parts p
                  LEFT JOIN bom_items bi ON p.id = bi.part_id
                  LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
                  LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
                  WHERE $whereClause";
    
    // Add stock status filter to HAVING clause
    if (!empty($filters['stock_status'])) {
        switch ($filters['stock_status']) {
            case 'low':
                $havingConditions[] = 'stock_status = \'Low\'';
                break;
            case 'out':
                $havingConditions[] = 'stock_status = \'Out of Stock\'';
                break;
            case 'ok':
                $havingConditions[] = 'stock_status = \'OK\'';
                break;
        }
    }
    
    $havingClause = !empty($havingConditions) ? 'HAVING ' . implode(' AND ', $havingConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM (
                    SELECT p.id 
                    $baseQuery
                    GROUP BY p.id
                    $havingClause
                ) as subquery";
    
    $totalResult = $db->fetch($countSql, $params);
    $totalItems = $totalResult['total'];
    
    // Get parts list
    $sql = "SELECT p.*, 
                   COUNT(bi.id) as usage_count,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            $baseQuery
            GROUP BY p.id
            $havingClause
            ORDER BY p.part_code
            LIMIT ? OFFSET ?";
    
    $params = array_merge($params, [$filters['limit'], $offset]);
    $parts = $db->fetchAll($sql, $params);
    
    // Pagination info
    $totalPages = ceil($totalItems / $filters['limit']);
    $pagination = [
        'current_page' => $filters['page'],
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $filters['limit'],
        'has_previous' => $filters['page'] > 1,
        'has_next' => $filters['page'] < $totalPages
    ];
    
    successResponse([
        'parts' => $parts,
        'pagination' => $pagination
    ]);
}

/**
 * Get simple parts list (for dropdowns)
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
 * Get part details by ID
 */
function handleGetPartDetails() {
    requirePermission('bom', 'view');
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
            LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
            LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
            WHERE p.id = ?";
    
    $part = $db->fetch($sql, [$partId]);
    if (!$part) {
        throw new Exception('Part not found');
    }
    
    // Get suppliers
    $suppliers = $db->fetchAll(
        "SELECT * FROM part_suppliers WHERE part_id = ? ORDER BY is_preferred DESC, supplier_name",
        [$partId]
    );
    
    // Get usage in BOMs
    $usage = $db->fetchAll(
        "SELECT mb.id, mb.bom_name, mb.bom_code, mt.name as machine_type_name,
                bi.quantity, bi.unit, bi.priority
         FROM bom_items bi
         JOIN machine_bom mb ON bi.bom_id = mb.id
         JOIN machine_types mt ON mb.machine_type_id = mt.id
         WHERE bi.part_id = ?
         ORDER BY mb.bom_name",
        [$partId]
    );
    
    $part['suppliers'] = $suppliers;
    $part['usage'] = $usage;
    
    successResponse(['part' => $part]);
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
    
    // Check for duplicate part code (excluding current part)
    if (isCodeExists('parts', $data['part_code'], $partId, [])) {
        throw new Exception('Mã linh kiện đã tồn tại');
    }
    
    $db->beginTransaction();
    
    try {
        // Update part
        $sql = "UPDATE parts 
                SET part_code = ?, part_name = ?, description = ?, unit = ?, category = ?, 
                    specifications = ?, manufacturer = ?, supplier_code = ?, supplier_name = ?, 
                    unit_price = ?, min_stock = ?, max_stock = ?, lead_time = ?, notes = ?, 
                    updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $data['part_code'], $data['part_name'], $data['description'], $data['unit'],
            $data['category'], $data['specifications'], $data['manufacturer'], 
            $data['supplier_code'], $data['supplier_name'], $data['unit_price'],
            $data['min_stock'], $data['max_stock'], $data['lead_time'], 
            $data['notes'], $partId
        ];
        
        $db->execute($sql, $params);
        
        // Update main supplier if changed
        if (!empty($data['supplier_code']) && !empty($data['supplier_name'])) {
            // Check if supplier exists
            $existingSupplier = $db->fetch(
                "SELECT id FROM part_suppliers WHERE part_id = ? AND supplier_code = ?",
                [$partId, $data['supplier_code']]
            );
            
            if ($existingSupplier) {
                // Update existing supplier
                $db->execute(
                    "UPDATE part_suppliers SET supplier_name = ?, unit_price = ? WHERE id = ?",
                    [$data['supplier_name'], $data['unit_price'], $existingSupplier['id']]
                );
            } else {
                // Add new supplier as preferred
                $db->execute(
                    "UPDATE part_suppliers SET is_preferred = 0 WHERE part_id = ?",
                    [$partId]
                );
                
                $db->execute(
                    "INSERT INTO part_suppliers (part_id, supplier_code, supplier_name, unit_price, is_preferred) 
                     VALUES (?, ?, ?, ?, 1)",
                    [$partId, $data['supplier_code'], $data['supplier_name'], $data['unit_price']]
                );
            }
        }
        
        // Log activity
        createAuditTrail('parts', $partId, 'updated', $currentPart, $data);
        logActivity('update_part', 'bom', "Updated part: {$data['part_code']} - {$data['part_name']}");
        
        $db->commit();
        
        successResponse(['part_id' => $partId], 'Cập nhật linh kiện thành công');
        
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
    
    // Get part info before deletion
    $part = $db->fetch("SELECT * FROM parts WHERE id = ?", [$partId]);
    if (!$part) {
        throw new Exception('Part not found');
    }
    
    // Check if part is used in any BOM
    $usage = $db->fetch("SELECT COUNT(*) as count FROM bom_items WHERE part_id = ?", [$partId]);
    if ($usage['count'] > 0) {
        throw new Exception('Không thể xóa linh kiện đang được sử dụng trong BOM');
    }
    
    $db->beginTransaction();
    
    try {
        // Delete part suppliers first
        $db->execute("DELETE FROM part_suppliers WHERE part_id = ?", [$partId]);
        
        // Delete inventory mapping
        $db->execute("DELETE FROM part_inventory_mapping WHERE part_id = ?", [$partId]);
        
        // Delete part
        $db->execute("DELETE FROM parts WHERE id = ?", [$partId]);
        
        // Log activity
        logActivity('delete_part', 'bom', "Deleted part: {$part['part_code']} - {$part['part_name']}");
        
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
    
    // Build update fields
    if (!empty($_POST['category'])) {
        $updates[] = 'category = ?';
        $params[] = $_POST['category'];
    }
    
    if (!empty($_POST['supplier_name'])) {
        $updates[] = 'supplier_name = ?';
        $params[] = $_POST['supplier_name'];
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
        
        $affectedRows = $db->rowCount($sql, $whereParams);
        
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
 * Search parts (for autocomplete)
 */
function handleSearchParts() {
    global $db;
    
    $query = trim($_GET['q'] ?? '');
    $limit = min(50, max(5, intval($_GET['limit'] ?? 10)));
    
    if (strlen($query) < 2) {
        successResponse([]);
    }
    
    $sql = "SELECT p.id, p.part_code, p.part_name, p.unit, p.unit_price, p.category,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
            LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
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
            LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
            LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
            WHERE p.id IN ($placeholders)";
    
    $stocks = $db->fetchAll($sql, $partIds);
    
    successResponse($stocks);
}
?>