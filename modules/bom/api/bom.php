<?php
/**
 * BOM API Handler
 * /modules/bom/api/bom.php
 * API endpoint cho BOM operations
 */

header('Content-Type: application/json; charset=utf-8');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
        
        if (!is_array($boms)) {
            $boms = [];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $boms,
            'pagination' => [
                'current_page' => $filters['page'],
                'total_pages' => ceil($totalItems / $filters['limit']),
                'total_items' => $totalItems,
                'per_page' => $filters['limit'],
                'has_previous' => $filters['page'] > 1,
                'has_next' => $filters['page'] < ceil($totalItems / $filters['limit'])
            ]
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        throw new Exception('Error fetching BOM list: ' . $e->getMessage());
    }
}

/**
 * Get BOM details
 */
function handleGetBOMDetails() {
    global $db;
    
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
        'data' => $bom
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Save new BOM
 */
function handleSaveBOM() {
    global $db;
    
    requirePermission('bom', 'create');
    
    $data = [
        'machine_type_id' => intval($_POST['machine_type_id'] ?? 0),
        'bom_name' => trim($_POST['bom_name'] ?? ''),
        'bom_code' => trim($_POST['bom_code'] ?? ''),
        'version' => trim($_POST['version'] ?? '1.0'),
        'description' => trim($_POST['description'] ?? ''),
        'items' => []
    ];
    
    // Process items
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['part_id']) && !empty($item['quantity'])) {
                $data['items'][] = [
                    'part_id' => intval($item['part_id']),
                    'quantity' => floatval($item['quantity']),
                    'unit' => trim($item['unit'] ?? 'Cái'),
                    'priority' => trim($item['priority'] ?? 'Medium')
                ];
            }
        }
    }
    
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
    $existingBom = $db->fetch("SELECT id FROM machine_bom WHERE bom_code = ?", [$data['bom_code']]);
    if ($existingBom) {
        throw new Exception('Mã BOM đã tồn tại: ' . $data['bom_code']);
    }
    
    $db->beginTransaction();
    
    try {
        // Insert BOM
        $sql = "INSERT INTO machine_bom (machine_type_id, bom_name, bom_code, version, description, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $currentUser = getCurrentUser();
        $params = [
            $data['machine_type_id'],
            $data['bom_name'],
            $data['bom_code'],
            $data['version'],
            $data['description'],
            $currentUser['id'] ?? null
        ];
        
        $db->execute($sql, $params);
        $bomId = $db->lastInsertId();
        
        if (!$bomId) {
            throw new Exception('Không thể tạo BOM - Database error');
        }
        
        // Insert BOM items
        if (!empty($data['items'])) {
            $itemSql = "INSERT INTO bom_items (bom_id, part_id, quantity, unit, priority, created_at) 
                       VALUES (?, ?, ?, ?, ?, NOW())";
            
            foreach ($data['items'] as $item) {
                $itemParams = [
                    $bomId,
                    $item['part_id'],
                    $item['quantity'],
                    $item['unit'],
                    $item['priority']
                ];
                
                $db->execute($itemSql, $itemParams);
            }
        }
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity('create_bom', 'bom', "Created BOM: {$data['bom_name']} ({$data['bom_code']})");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tạo BOM thành công',
            'data' => [
                'bom_id' => $bomId,
                'bom_code' => $data['bom_code'],
                'bom_name' => $data['bom_name']
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("BOM Save Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update existing BOM
 */
function handleUpdateBOM() {
    global $db;
    
    requirePermission('bom', 'edit');
    
    // Xử lý FormData cho nested arrays
    $rawInput = file_get_contents("php://input");
    $boundary = substr($rawInput, 0, strpos($rawInput, "\r\n"));
    if (empty($boundary)) {
        throw new Exception('Invalid FormData');
    }
    
    $blocks = array_slice(explode($boundary, $rawInput), 1);
    $data = [];
    $items = [];
    
    foreach ($blocks as $block) {
        if (trim($block) === '--') break;
        
        if (strpos($block, "name=\"") !== false) {
            preg_match('/name="([^"]*)"/', $block, $matches);
            $name = $matches[1];
            
            if (strpos($name, 'items[') === 0) {
                // Parse nested items
                preg_match('/items\[(\d+)\]\[([^\]]+)\]/', $name, $itemMatches);
                if (count($itemMatches) == 3) {
                    $index = $itemMatches[1];
                    $field = $itemMatches[2];
                    if (!isset($items[$index])) $items[$index] = [];
                    $items[$index][$field] = $this->extractValue($block);
                }
            } else {
                $data[$name] = $this->extractValue($block);
            }
        }
    }
    
    // Validate
    $data['bom_id'] = intval($data['bom_id'] ?? 0);
    $data['machine_type_id'] = intval($data['machine_type_id'] ?? 0);
    $data['bom_name'] = trim($data['bom_name'] ?? '');
    $data['bom_code'] = trim($data['bom_code'] ?? '');
    $data['version'] = trim($data['version'] ?? '1.0');
    $data['description'] = trim($data['description'] ?? '');
    $data['notes'] = trim($data['notes'] ?? '');
    
    // Process items
    $data['items'] = array_values($items); // Convert to indexed array
    
    if (empty($data['bom_id'])) {
        throw new Exception('BOM ID is required');
    }
    if (empty($data['machine_type_id'])) {
        throw new Exception('Vui lòng chọn dòng máy');
    }
    if (empty($data['bom_name'])) {
        throw new Exception('Vui lòng nhập tên BOM');
    }
    if (empty($data['items'])) {
        throw new Exception('BOM phải có ít nhất 1 linh kiện');
    }
    
    // Check duplicate code
    if (!empty($data['bom_code'])) {
        $existingBom = $db->fetch("SELECT id FROM machine_bom WHERE bom_code = ? AND id != ?", [$data['bom_code'], $data['bom_id']]);
        if ($existingBom) {
            throw new Exception('Mã BOM đã tồn tại: ' . $data['bom_code']);
        }
    }
    
    // Generate code if empty
    if (empty($data['bom_code'])) {
        $data['bom_code'] = generateBOMCode($data['machine_type_id']);
    }
    
    $db->beginTransaction();
    
    try {
        // Update BOM
        $sql = "UPDATE machine_bom SET machine_type_id = ?, bom_name = ?, bom_code = ?, version = ?, description = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
        $currentUser = getCurrentUser();
        $params = [
            $data['machine_type_id'], $data['bom_name'], $data['bom_code'], $data['version'],
            $data['description'], $data['notes'], $currentUser['id'] ?? null, $data['bom_id']
        ];
        $db->execute($sql, $params);
        
        // Delete old items
        $db->execute("DELETE FROM bom_items WHERE bom_id = ?", [$data['bom_id']]);
        
        // Insert new items
        $itemSql = "INSERT INTO bom_items (bom_id, part_id, quantity, unit, priority, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        foreach ($data['items'] as $item) {
            if (!empty($item['part_id']) && ($item['quantity'] ?? 0) > 0) {
                $itemParams = [
                    $data['bom_id'], intval($item['part_id']), floatval($item['quantity']),
                    trim($item['unit'] ?? 'Cái'), trim($item['priority'] ?? 'Medium')
                ];
                $db->execute($itemSql, $itemParams);
            }
        }
        
        if (function_exists('logActivity')) {
            logActivity('update_bom', 'bom', "Updated BOM: {$data['bom_name']} ({$data['bom_code']})");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật BOM thành công',
            'data' => ['bom_id' => $data['bom_id']]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("BOM Update Error: " . $e->getMessage() . " Data: " . print_r($data, true));
        throw $e;
    }
}

// Helper function to extract value from multipart block

/**
 * Delete BOM
 */
function handleDeleteBOM() {
    global $db;
    
    requirePermission('bom', 'delete');
    
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
    echo json_encode([
        'success' => false,
        'message' => 'Copy function not implemented yet'
    ], JSON_UNESCAPED_UNICODE);
}
?>