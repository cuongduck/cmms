<?php
/**
 * BOM API Handler - Fixed Version
 * /modules/bom/api/bom.php
 * API endpoint cho BOM operations
 */

header('Content-Type: application/json; charset=utf-8');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in JSON response

try {
    require_once '../../../config/config.php';
    require_once '../../../config/database.php';
    require_once '../../../config/auth.php';
    require_once '../../../config/functions.php';
    require_once '../config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Configuration error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check login
try {
    requireLogin();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleBOMList();
            break;
            
        case 'getDetails':
            handleGetBOMDetails();
            break;
            
        case 'save':
            handleSaveBOM();
            break;
            
        case 'update':
            handleUpdateBOM();
            break;
            
        case 'delete':
            handleDeleteBOM();
            break;
            
        case 'generateCode':
            handleGenerateBOMCode();
            break;
            
        case 'copy':
            handleCopyBOM();
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Get BOM list with filters and pagination
 */
function handleBOMList() {
    global $db;
    
    $filters = [
        'machine_type' => $_GET['machine_type'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 20)))
    ];
    
    $offset = ($filters['page'] - 1) * $filters['limit'];
    
    // Build WHERE conditions
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($filters['machine_type'])) {
        $whereConditions[] = 'mb.machine_type_id = ?';
        $params[] = $filters['machine_type'];
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = '(mb.bom_name LIKE ? OR mb.bom_code LIKE ? OR mt.name LIKE ?)';
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count first
    try {
        $countSql = "SELECT COUNT(DISTINCT mb.id) as total 
                     FROM machine_bom mb 
                     JOIN machine_types mt ON mb.machine_type_id = mt.id 
                     WHERE $whereClause";
        
        $totalResult = $db->fetch($countSql, $params);
        $totalItems = intval($totalResult['total']);
    } catch (Exception $e) {
        // If query fails, return empty result
        echo json_encode([
            'success' => true,
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'per_page' => $filters['limit'],
                'has_previous' => false,
                'has_next' => false
            ]
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Get BOM list
    try {
        $sql = "SELECT mb.id, mb.bom_name, mb.bom_code, mb.version, mb.description,
                       mb.created_at, mb.updated_at,
                       mt.name as machine_type_name, mt.code as machine_type_code,
                       u.full_name as created_by_name,
                       COUNT(DISTINCT bi.id) as total_items,
                       COALESCE(SUM(bi.quantity * COALESCE(p.unit_price, 0)), 0) as total_cost
                FROM machine_bom mb
                JOIN machine_types mt ON mb.machine_type_id = mt.id  
                LEFT JOIN users u ON mb.created_by = u.id
                LEFT JOIN bom_items bi ON mb.id = bi.bom_id
                LEFT JOIN parts p ON bi.part_id = p.id
                WHERE $whereClause
                GROUP BY mb.id, mb.bom_name, mb.bom_code, mb.version, mb.description,
                         mb.created_at, mb.updated_at, mt.name, mt.code, u.full_name
                ORDER BY mb.created_at DESC
                LIMIT ? OFFSET ?";
        
        $queryParams = array_merge($params, [$filters['limit'], $offset]);
        $boms = $db->fetchAll($sql, $queryParams);
        
        // Ensure boms is always an array
        if (!is_array($boms)) {
            $boms = [];
        }
        
        // Format data
        $formattedBoms = [];
        foreach ($boms as $bom) {
            $formattedBoms[] = [
                'id' => intval($bom['id']),
                'bom_name' => $bom['bom_name'],
                'bom_code' => $bom['bom_code'],
                'version' => $bom['version'],
                'description' => $bom['description'],
                'machine_type_name' => $bom['machine_type_name'],
                'machine_type_code' => $bom['machine_type_code'],
                'created_by_name' => $bom['created_by_name'],
                'total_items' => intval($bom['total_items']),
                'total_cost' => floatval($bom['total_cost']),
                'created_at' => $bom['created_at'],
                'updated_at' => $bom['updated_at']
            ];
        }
        
    } catch (Exception $e) {
        // If main query fails, return empty result with error message
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
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
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $formattedBoms, // This should be an array
        'pagination' => $pagination
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Get BOM details by ID
 */
function handleGetBOMDetails() {
    requirePermission('bom', 'view');
    
    $bomId = intval($_GET['id'] ?? 0);
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    $bom = getBOMDetails($bomId);
    if (!$bom) {
        throw new Exception('BOM not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => ['bom' => $bom]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Save new BOM
 */
function handleSaveBOM() {
    requirePermission('bom', 'create');
    global $db;
    
    $data = [
        'machine_type_id' => intval($_POST['machine_type_id'] ?? 0),
        'bom_name' => trim($_POST['bom_name'] ?? ''),
        'bom_code' => trim($_POST['bom_code'] ?? ''),
        'version' => trim($_POST['version'] ?? '1.0'),
        'description' => trim($_POST['description'] ?? ''),
        'effective_date' => $_POST['effective_date'] ?? null,
        'items' => $_POST['items'] ?? []
    ];
    
    // Validate data
    $errors = validateBOMData($data);
    if (!empty($errors)) {
        throw new Exception(implode(', ', $errors));
    }
    
    // Generate BOM code if empty
    if (empty($data['bom_code'])) {
        $data['bom_code'] = generateBOMCode($data['machine_type_id']);
    }
    
    // Check for duplicate BOM code
    if (isCodeExists('machine_bom', $data['bom_code'])) {
        throw new Exception('Mã BOM đã tồn tại');
    }
    
    $db->beginTransaction();
    
    try {
        // Insert BOM
        $sql = "INSERT INTO machine_bom (machine_type_id, bom_name, bom_code, version, description, effective_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['machine_type_id'],
            $data['bom_name'],
            $data['bom_code'],
            $data['version'],
            $data['description'],
            $data['effective_date'] ?: null,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $bomId = $db->lastInsertId();
        
        // Insert BOM items
        if (!empty($data['items'])) {
            $itemSql = "INSERT INTO bom_items (bom_id, part_id, quantity, unit, position, priority, maintenance_interval) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            foreach ($data['items'] as $item) {
                if (empty($item['part_id']) || empty($item['quantity'])) {
                    continue;
                }
                
                $itemParams = [
                    $bomId,
                    intval($item['part_id']),
                    floatval($item['quantity']),
                    $item['unit'] ?? '',
                    $item['position'] ?? '',
                    $item['priority'] ?? 'Medium',
                    !empty($item['maintenance_interval']) ? intval($item['maintenance_interval']) : null
                ];
                
                $db->execute($itemSql, $itemParams);
            }
        }
        
        // Log activity
        logActivity('create_bom', 'bom', "Created BOM: {$data['bom_name']}");
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tạo BOM thành công',
            'data' => ['bom_id' => $bomId]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Update existing BOM
 */
function handleUpdateBOM() {
    requirePermission('bom', 'edit');
    // Implementation similar to save but with UPDATE
    echo json_encode([
        'success' => false,
        'message' => 'Update function not implemented yet'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Delete BOM
 */
function handleDeleteBOM() {
    requirePermission('bom', 'delete');
    global $db;
    
    $bomId = intval($_POST['id'] ?? 0);
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    // Get BOM info before deletion
    $bom = $db->fetch("SELECT * FROM machine_bom WHERE id = ?", [$bomId]);
    if (!$bom) {
        throw new Exception('BOM not found');
    }
    
    $db->beginTransaction();
    
    try {
        // Delete BOM items first
        $db->execute("DELETE FROM bom_items WHERE bom_id = ?", [$bomId]);
        
        // Delete BOM
        $db->execute("DELETE FROM machine_bom WHERE id = ?", [$bomId]);
        
        // Log activity
        logActivity('delete_bom', 'bom', "Deleted BOM: {$bom['bom_name']}");
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Xóa BOM thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Generate BOM code
 */
function handleGenerateBOMCode() {
    requirePermission('bom', 'create');
    
    $machineTypeId = intval($_POST['machine_type_id'] ?? 0);
    if (!$machineTypeId) {
        throw new Exception('Machine Type ID is required');
    }
    
    $code = generateBOMCode($machineTypeId);
    if (!$code) {
        throw new Exception('Unable to generate BOM code');
    }
    
    echo json_encode([
        'success' => true,
        'data' => ['code' => $code]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Copy BOM to create new one
 */
function handleCopyBOM() {
    requirePermission('bom', 'create');
    // Implementation for copy functionality
    echo json_encode([
        'success' => false,
        'message' => 'Copy function not implemented yet'
    ], JSON_UNESCAPED_UNICODE);
}
?>