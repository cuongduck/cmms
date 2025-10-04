<?php
/**
 * Item MMB API Handler
 * /modules/item_mmb/api/items.php
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
        case 'create':
            handleCreate();
            break;
        
        case 'update':
            handleUpdate();
            break;
        
        case 'update_field':
            handleUpdateField();
            break;
        
        case 'delete':
            handleDelete();
            break;
        
        case 'get':
            handleGet();
            break;
        
        case 'list':
            handleList();
            break;
        
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Item MMB API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}

function handleCreate() {
    requirePermission('item_mmb', 'create');
    
    $data = [
        'ITEM_CODE' => strtoupper(trim($_POST['item_code'] ?? '')),
        'ITEM_NAME' => trim($_POST['item_name'] ?? ''),
        'UOM' => trim($_POST['uom'] ?? ''),
        'UNIT_PRICE' => !empty($_POST['unit_price']) ? floatval($_POST['unit_price']) : null,
        'VENDOR_ID' => !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null,
        'VENDOR_NAME' => trim($_POST['vendor_name'] ?? '')
    ];
    
    $result = createItemMMB($data);
    
    if ($result['success']) {
        successResponse($result);
    } else {
        errorResponse($result['message']);
    }
}

function handleUpdate() {
    requirePermission('item_mmb', 'edit');
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $data = [
        'ITEM_CODE' => strtoupper(trim($_POST['item_code'] ?? '')),
        'ITEM_NAME' => trim($_POST['item_name'] ?? ''),
        'UOM' => trim($_POST['uom'] ?? ''),
        'UNIT_PRICE' => !empty($_POST['unit_price']) ? floatval($_POST['unit_price']) : null,
        'VENDOR_ID' => !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null,
        'VENDOR_NAME' => trim($_POST['vendor_name'] ?? '')
    ];
    
    $result = updateItemMMB($id, $data);
    
    if ($result['success']) {
        successResponse($result);
    } else {
        errorResponse($result['message']);
    }
}

function handleUpdateField() {
    requirePermission('item_mmb', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    // Validate field
    $allowedFields = ['ITEM_CODE', 'ITEM_NAME', 'UOM', 'UNIT_PRICE', 'VENDOR_ID', 'VENDOR_NAME'];
    if (!in_array($field, $allowedFields)) {
        throw new Exception('Invalid field');
    }
    
    // Get current data for validation
    $current = getItemMMB($id);
    if (!$current) {
        throw new Exception('Item not found');
    }
    
    // Validation
    if ($field === 'ITEM_CODE') {
        $value = strtoupper(trim($value));
        if (empty($value)) {
            throw new Exception('Mã item không được để trống');
        }
        
        // Check duplicate
        $existing = $db->fetch("SELECT ID FROM item_mmb WHERE ITEM_CODE = ? AND ID != ?", [$value, $id]);
        if ($existing) {
            throw new Exception('Mã item đã tồn tại');
        }
    }
    
    if ($field === 'ITEM_NAME' && empty(trim($value))) {
        throw new Exception('Tên item không được để trống');
    }
    
    if ($field === 'UNIT_PRICE') {
        $value = !empty($value) ? floatval($value) : null;
    }
    
    if ($field === 'VENDOR_ID') {
        $value = !empty($value) ? intval($value) : null;
    }
    
    // Update
    $sql = "UPDATE item_mmb SET {$field} = ?, TIME_UPDATE = NOW() WHERE ID = ?";
    $db->execute($sql, [$value, $id]);
    
    // Get updated data
    $updated = getItemMMB($id);
    
    logActivity('update_item_mmb_field', 'item_mmb', "Updated {$field} for item ID: {$id}");
    
    successResponse([
        'field' => $field,
        'value' => $updated[$field],
        'formatted_value' => formatFieldValue($field, $updated[$field]),
        'time_update' => formatDateTime($updated['TIME_UPDATE'])
    ], 'Cập nhật thành công');
}

function handleDelete() {
    requirePermission('item_mmb', 'delete');
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $result = deleteItemMMB($id);
    
    if ($result['success']) {
        successResponse($result);
    } else {
        errorResponse($result['message']);
    }
}

function handleGet() {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $item = getItemMMB($id);
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    successResponse($item);
}

function handleList() {
    $filters = [
        'search' => $_GET['search'] ?? '',
        'vendor' => $_GET['vendor'] ?? '',
        'sort' => $_GET['sort'] ?? 'ID',
        'order' => $_GET['order'] ?? 'DESC',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 20)))
    ];
    
    $items = getItemsMMB($filters);
    $total = getItemsMMBCount($filters);
    
    successResponse([
        'items' => $items,
        'pagination' => [
            'current_page' => $filters['page'],
            'per_page' => $filters['limit'],
            'total_items' => $total,
            'total_pages' => ceil($total / $filters['limit'])
        ]
    ]);
}

function formatFieldValue($field, $value) {
    switch ($field) {
        case 'UNIT_PRICE':
            return $value ? number_format($value, 2) : '';
        default:
            return $value;
    }
}
?>
