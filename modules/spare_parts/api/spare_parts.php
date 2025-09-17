<?php
/**
 * Spare Parts API Handler
 * /modules/spare_parts/api/spare_parts.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

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
            handleList();
            break;
        
        case 'details':
            handleGetDetails();
            break;
        
        case 'save':
            handleSave();
            break;
        
        case 'update':
            handleUpdate();
            break;
        
        case 'delete':
            handleDelete();
            break;
        
        case 'check_stock':
            handleCheckStock();
            break;
        
        case 'get_reorder_list':
            handleGetReorderList();
            break;
        case 'detect_category':
            handleDetectCategory();
            break;   
        case 'reclassify':
            handleReclassify();
            break;
    
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Spare Parts API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}

function handleList() {
    $filters = [
        'category' => $_GET['category'] ?? '',
        'manager' => $_GET['manager'] ?? '',
        'stock_status' => $_GET['stock_status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 20)))
    ];
    
    $spareParts = getSpareParts($filters);
    
    // Simple pagination (can be enhanced)
    $offset = ($filters['page'] - 1) * $filters['limit'];
    $paginatedParts = array_slice($spareParts, $offset, $filters['limit']);
    
    $pagination = [
        'current_page' => $filters['page'],
        'total_pages' => ceil(count($spareParts) / $filters['limit']),
        'per_page' => $filters['limit'],
        'total_items' => count($spareParts)
    ];
    
    successResponse(['parts' => $paginatedParts, 'pagination' => $pagination]);
}

function handleGetDetails() {
    global $db;
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $sql = "SELECT sp.*, 
                   COALESCE(oh.Onhand, 0) as current_stock,
                   COALESCE(oh.UOM, sp.unit) as stock_unit,
                   COALESCE(oh.OH_Value, 0) as stock_value,
                   COALESCE(oh.Price, sp.standard_cost) as current_price,
                   u1.full_name as manager_name,
                   u2.full_name as backup_manager_name,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN 'Reorder'
                       WHEN COALESCE(oh.Onhand, 0) < sp.min_stock THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM spare_parts sp
            LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
            LEFT JOIN users u1 ON sp.manager_user_id = u1.id
            LEFT JOIN users u2 ON sp.backup_manager_user_id = u2.id
            WHERE sp.id = ?";
    
    $part = $db->fetch($sql, [$id]);
    
    if (!$part) {
        throw new Exception('Spare part not found');
    }
    
    successResponse($part);
}

function handleSave() {
    requirePermission('spare_parts', 'create');
    global $db;
    
    $data = [
        'item_code' => strtoupper(trim($_POST['item_code'] ?? '')),
        'item_name' => trim($_POST['item_name'] ?? ''),
        'category' => autoDetectCategory(trim($_POST['item_name'] ?? '')),
        'unit' => trim($_POST['unit'] ?? 'Cái'),
        'min_stock' => floatval($_POST['min_stock'] ?? 0),
        'max_stock' => floatval($_POST['max_stock'] ?? 0),
        'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
        'standard_cost' => floatval($_POST['standard_cost'] ?? 0),
        'manager_user_id' => !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        'backup_manager_user_id' => !empty($_POST['backup_manager_user_id']) ? intval($_POST['backup_manager_user_id']) : null,
        'supplier_code' => trim($_POST['supplier_code'] ?? ''),
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'lead_time_days' => intval($_POST['lead_time_days'] ?? 0),
        'storage_location' => trim($_POST['storage_location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'specifications' => trim($_POST['specifications'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_critical' => isset($_POST['is_critical']) ? 1 : 0
    ];
    
    // Validation
    if (empty($data['item_code'])) {
        throw new Exception('Mã vật tư không được để trống');
    }
    
    if (empty($data['item_name'])) {
        throw new Exception('Tên vật tư không được để trống');
    }
        error_log("Auto-detected category for '{$data['item_name']}': {$data['category']}");

    if ($data['min_stock'] <= 0) {
        throw new Exception('Mức tồn tối thiểu phải lớn hơn 0');
    }
    
    // Auto-set reorder_point if not provided
    if ($data['reorder_point'] <= 0) {
        $data['reorder_point'] = $data['min_stock'];
    }
    
    // Check duplicate item_code
    $existing = $db->fetch("SELECT id FROM spare_parts WHERE item_code = ?", [$data['item_code']]);
    if ($existing) {
        throw new Exception('Mã vật tư đã tồn tại');
    }
    if (empty($data['category'])) {
    $data['category'] = autoDetectCategory($data['item_name']);
}


    
    $db->beginTransaction();
    
    try {
        $sql = "INSERT INTO spare_parts 
                (item_code, item_name, category, unit, min_stock, max_stock, reorder_point, 
                 standard_cost, manager_user_id, backup_manager_user_id, supplier_code, supplier_name,
                 lead_time_days, storage_location, description, specifications, notes, is_critical, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['item_code'], $data['item_name'], $data['category'], $data['unit'],
            $data['min_stock'], $data['max_stock'], $data['reorder_point'], $data['standard_cost'],
            $data['manager_user_id'], $data['backup_manager_user_id'], $data['supplier_code'], $data['supplier_name'],
            $data['lead_time_days'], $data['storage_location'], $data['description'], $data['specifications'],
            $data['notes'], $data['is_critical'], getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $id = $db->lastInsertId();
        
        // Log activity
        logActivity('create_spare_part', 'spare_parts', "Created spare part: {$data['item_code']} - {$data['item_name']}");
        
        $db->commit();
        
        successResponse(['id' => $id], 'Tạo spare part thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleUpdate() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
        $currentData = $db->fetch("SELECT item_name, category FROM spare_parts WHERE id = ?", [$id]);

    $data = [
        'item_code' => strtoupper(trim($_POST['item_code'] ?? '')),
        'item_name' => trim($_POST['item_name'] ?? ''),
        'unit' => trim($_POST['unit'] ?? 'Cái'),
        'min_stock' => floatval($_POST['min_stock'] ?? 0),
        'max_stock' => floatval($_POST['max_stock'] ?? 0),
        'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
        'standard_cost' => floatval($_POST['standard_cost'] ?? 0),
        'manager_user_id' => !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        'backup_manager_user_id' => !empty($_POST['backup_manager_user_id']) ? intval($_POST['backup_manager_user_id']) : null,
        'supplier_code' => trim($_POST['supplier_code'] ?? ''),
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'lead_time_days' => intval($_POST['lead_time_days'] ?? 0),
        'storage_location' => trim($_POST['storage_location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'specifications' => trim($_POST['specifications'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_critical' => isset($_POST['is_critical']) ? 1 : 0
    ];
    // Tự động phân loại lại nếu tên thay đổi hoặc được yêu cầu
    $forceReclassify = isset($_POST['force_reclassify']) && $_POST['force_reclassify'] == '1';
    if ($forceReclassify || $data['item_name'] !== $currentData['item_name']) {
        $data['category'] = autoDetectCategory($data['item_name']);
        error_log("Re-classified category for '{$data['item_name']}': {$data['category']}");
    } else {
        // Giữ category hiện tại nếu tên không đổi
        $data['category'] = $currentData['category'];
    }
    // Validation
    if (empty($data['item_code'])) {
        throw new Exception('Mã vật tư không được để trống');
    }
    
    if (empty($data['item_name'])) {
        throw new Exception('Tên vật tư không được để trống');
    }
    
    if ($data['min_stock'] <= 0) {
        throw new Exception('Mức tồn tối thiểu phải lớn hơn 0');
    }
    
    // Auto-set reorder_point if not provided
    if ($data['reorder_point'] <= 0) {
        $data['reorder_point'] = $data['min_stock'];
    }
    
    // Check duplicate item_code (exclude current record)
    $existing = $db->fetch("SELECT id FROM spare_parts WHERE item_code = ? AND id != ?", [$data['item_code'], $id]);
    if ($existing) {
        throw new Exception('Mã vật tư đã tồn tại');
    }
    // Trong function handleUpdate(), tương tự:
    if (empty($data['category'])) {
    $data['category'] = autoDetectCategory($data['item_name']);
}
    
    $db->beginTransaction();
    
    try {
       $sql = "UPDATE spare_parts SET 
                item_code = ?, item_name = ?, category = ?, unit = ?, min_stock = ?, max_stock = ?, 
                reorder_point = ?, standard_cost = ?, manager_user_id = ?, backup_manager_user_id = ?, 
                supplier_code = ?, supplier_name = ?, lead_time_days = ?, storage_location = ?, 
                description = ?, specifications = ?, notes = ?, is_critical = ?, updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $data['item_code'], $data['item_name'], $data['category'], $data['unit'],
            $data['min_stock'], $data['max_stock'], $data['reorder_point'], $data['standard_cost'],
            $data['manager_user_id'], $data['backup_manager_user_id'], $data['supplier_code'], $data['supplier_name'],
            $data['lead_time_days'], $data['storage_location'], $data['description'], $data['specifications'],
            $data['notes'], $data['is_critical'], $id
        ];
        
        $db->execute($sql, $params);
        
        // Log activity
        logActivity('update_spare_part', 'spare_parts', "Updated spare part: {$data['item_code']} - {$data['item_name']}");
        
        $db->commit();
        
        successResponse([], 'Cập nhật spare part thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleDelete() {
    requirePermission('spare_parts', 'delete');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    // Get part info before delete
    $part = $db->fetch("SELECT item_code, item_name FROM spare_parts WHERE id = ?", [$id]);
    if (!$part) {
        throw new Exception('Spare part not found');
    }
    
    $db->beginTransaction();
    
    try {
        // Soft delete - set is_active = 0
        $db->execute("UPDATE spare_parts SET is_active = 0, updated_at = NOW() WHERE id = ?", [$id]);
        
        // Log activity
        logActivity('delete_spare_part', 'spare_parts', "Deleted spare part: {$part['item_code']} - {$part['item_name']}");
        
        $db->commit();
        
        successResponse([], 'Xóa spare part thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleCheckStock() {
    global $db;
    
    $itemCode = trim($_GET['item_code'] ?? '');
    if (!$itemCode) {
        throw new Exception('Item code is required');
    }
    
    $sql = "SELECT ItemCode, Itemname, Onhand, UOM, Price, OH_Value FROM onhand WHERE ItemCode = ?";
    $stock = $db->fetch($sql, [$itemCode]);
    
    successResponse($stock);
}

function handleGetReorderList() {
    $reorderList = getReorderList();
    successResponse($reorderList);
}
function handleDetectCategory() {
    $itemName = trim($_GET['item_name'] ?? '');
    if (empty($itemName)) {
        throw new Exception('Item name is required');
    }
    
    $category = autoDetectCategory($itemName);
    $confidence = calculateCategoryConfidence($itemName, $category);
    
    successResponse([
        'category' => $category,
        'confidence' => $confidence,
        'suggestions' => getSimilarCategories($itemName)
    ]);
}

function calculateCategoryConfidence($itemName, $detectedCategory) {
    global $sparePartsConfig;
    
    $itemName = strtolower(trim($itemName));
    $keywords = $sparePartsConfig['auto_categorization'][$detectedCategory] ?? [];
    
    $maxScore = 0;
    $totalPossibleScore = strlen($itemName);
    
    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim($keyword));
        if (strpos($itemName, $keyword) !== false) {
            $score = strlen($keyword);
            if (strpos($itemName, $keyword) === 0) $score += 5;
            if ($itemName === $keyword) $score += 10;
            $maxScore = max($maxScore, $score);
        }
    }
    
    return min(100, round(($maxScore / max($totalPossibleScore, 1)) * 100));
}

function getSimilarCategories($itemName, $limit = 3) {
    global $sparePartsConfig;
    
    $itemName = strtolower(trim($itemName));
    $categoryScores = [];
    
    foreach ($sparePartsConfig['auto_categorization'] as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($itemName, strtolower($keyword)) !== false) {
                $score += strlen($keyword);
            }
        }
        if ($score > 0) {
            $categoryScores[$category] = $score;
        }
    }
    
    arsort($categoryScores);
    return array_slice(array_keys($categoryScores), 0, $limit);
}
function handleReclassify() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    // Lấy thông tin part hiện tại
    $part = $db->fetch("SELECT item_name, category FROM spare_parts WHERE id = ?", [$id]);
    if (!$part) {
        throw new Exception('Spare part not found');
    }
    
    // Phân loại lại
    $newCategory = autoDetectCategory($part['item_name']);
    
    // Cập nhật
    $db->execute("UPDATE spare_parts SET category = ?, updated_at = NOW() WHERE id = ?", [$newCategory, $id]);
    
    // Log activity
    logActivity('reclassify_spare_part', 'spare_parts', "Reclassified part ID {$id}: '{$part['category']}' → '{$newCategory}'");
    
    successResponse([
        'old_category' => $part['category'],
        'new_category' => $newCategory,
        'confidence' => calculateCategoryConfidence($part['item_name'], $newCategory)
    ], "Phân loại lại thành công: {$newCategory}");
}
?>