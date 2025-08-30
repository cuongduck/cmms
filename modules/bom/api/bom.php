<?php
/**
 * BOM API Handler
 * /modules/bom/api/bom.php
 * API endpoint cho BOM operations
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
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    errorResponse($e->getMessage());
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
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM machine_bom mb 
                 JOIN machine_types mt ON mb.machine_type_id = mt.id 
                 WHERE $whereClause";
    
    $totalResult = $db->fetch($countSql, $params);
    $totalItems = $totalResult['total'];
    
    // Get BOM list
    $sql = "SELECT mb.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name,
                   COUNT(bi.id) as total_items,
                   SUM(bi.quantity * p.unit_price) as total_cost
            FROM machine_bom mb
            JOIN machine_types mt ON mb.machine_type_id = mt.id  
            LEFT JOIN users u ON mb.created_by = u.id
            LEFT JOIN bom_items bi ON mb.id = bi.bom_id
            LEFT JOIN parts p ON bi.part_id = p.id
            WHERE $whereClause
            GROUP BY mb.id 
            ORDER BY mb.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params = array_merge($params, [$filters['limit'], $offset]);
    $boms = $db->fetchAll($sql, $params);
    
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
        'boms' => $boms,
        'pagination' => $pagination
    ]);
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
    
    successResponse(['bom' => $bom]);
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
        errorResponse(implode(', ', $errors));
    }
    
    // Generate BOM code if empty
    if (empty($data['bom_code'])) {
        $data['bom_code'] = generateBOMCode($data['machine_type_id']);
    }
    
    // Check for duplicate BOM code
    if (isCodeExists('machine_bom', $data['bom_code'])) {
        errorResponse('Mã BOM đã tồn tại');
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
        createAuditTrail('machine_bom', $bomId, 'created', null, $data);
        logActivity('create_bom', 'bom', "Created BOM: {$data['bom_name']}");
        
        $db->commit();
        
        successResponse(['bom_id' => $bomId], 'Tạo BOM thành công');
        
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
    global $db;
    
    $bomId = intval($_POST['bom_id'] ?? 0);
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    // Get current BOM data
    $currentBOM = getBOMDetails($bomId);
    if (!$currentBOM) {
        throw new Exception('BOM not found');
    }
    
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
        errorResponse(implode(', ', $errors));
    }
    
    // Check for duplicate BOM code (excluding current BOM)
    if (isCodeExists('machine_bom', $data['bom_code'], $bomId)) {
        errorResponse('Mã BOM đã tồn tại');
    }
    
    $db->beginTransaction();
    
    try {
        // Update BOM
        $sql = "UPDATE machine_bom 
                SET machine_type_id = ?, bom_name = ?, bom_code = ?, version = ?, 
                    description = ?, effective_date = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $params = [
            $data['machine_type_id'],
            $data['bom_name'],
            $data['bom_code'],
            $data['version'],
            $data['description'],
            $data['effective_date'] ?: null,
            $bomId
        ];
        
        $db->execute($sql, $params);
        
        // Delete existing items
        $db->execute("DELETE FROM bom_items WHERE bom_id = ?", [$bomId]);
        
        // Insert updated items
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
        
        // Log changes
        $historyData = [
            'action' => 'updated',
            'changes' => [
                'old' => $currentBOM,
                'new' => $data
            ]
        ];
        
        $db->execute(
            "INSERT INTO bom_history (bom_id, action, changes, user_id) VALUES (?, ?, ?, ?)",
            [$bomId, 'updated', json_encode($historyData, JSON_UNESCAPED_UNICODE), getCurrentUser()['id']]
        );
        
        createAuditTrail('machine_bom', $bomId, 'updated', $currentBOM, $data);
        logActivity('update_bom', 'bom', "Updated BOM: {$data['bom_name']}");
        
        $db->commit();
        
        successResponse(['bom_id' => $bomId], 'Cập nhật BOM thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
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
        // Delete BOM items first (cascade should handle this, but be explicit)
        $db->execute("DELETE FROM bom_items WHERE bom_id = ?", [$bomId]);
        
        // Delete BOM
        $db->execute("DELETE FROM machine_bom WHERE id = ?", [$bomId]);
        
        // Log activity
        logActivity('delete_bom', 'bom', "Deleted BOM: {$bom['bom_name']}");
        
        $db->commit();
        
        successResponse([], 'Xóa BOM thành công');
        
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
    
    successResponse(['code' => $code]);
}

/**
 * Copy BOM to create new one
 */
function handleCopyBOM() {
    requirePermission('bom', 'create');
    global $db;
    
    $sourceBomId = intval($_POST['source_bom_id'] ?? 0);
    if (!$sourceBomId) {
        throw new Exception('Source BOM ID is required');
    }
    
    // Get source BOM
    $sourceBOM = getBOMDetails($sourceBomId);
    if (!$sourceBOM) {
        throw new Exception('Source BOM not found');
    }
    
    $db->beginTransaction();
    
    try {
        // Generate new BOM code
        $newCode = generateBOMCode($sourceBOM['machine_type_id']);
        
        // Insert new BOM
        $sql = "INSERT INTO machine_bom (machine_type_id, bom_name, bom_code, version, description, effective_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $sourceBOM['machine_type_id'],
            $sourceBOM['bom_name'] . ' (Copy)',
            $newCode,
            '1.0', // Reset version
            $sourceBOM['description'] . ' (Copied from ' . $sourceBOM['bom_code'] . ')',
            date('Y-m-d'),
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $newBomId = $db->lastInsertId();
        
        // Copy BOM items
        if (!empty($sourceBOM['items'])) {
            $itemSql = "INSERT INTO bom_items (bom_id, part_id, quantity, unit, position, priority, maintenance_interval) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            foreach ($sourceBOM['items'] as $item) {
                $itemParams = [
                    $newBomId,
                    $item['part_id'],
                    $item['quantity'],
                    $item['unit'],
                    $item['position'],
                    $item['priority'],
                    $item['maintenance_interval']
                ];
                
                $db->execute($itemSql, $itemParams);
            }
        }
        
        // Log activity
        logActivity('copy_bom', 'bom', "Copied BOM: {$sourceBOM['bom_name']} -> {$newCode}");
        
        $db->commit();
        
        successResponse(['bom_id' => $newBomId, 'bom_code' => $newCode], 'Sao chép BOM thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>