<?php
/**
 * Purchase Request API Handler
 * /modules/spare_parts/api/purchase_request.php
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
        
        case 'approve':
            handleApprove();
            break;
        
        case 'reject':
            handleReject();
            break;
        
        case 'list':
            handleList();
            break;
        
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Purchase Request API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}

function handleCreate() {
    requirePermission('spare_parts', 'purchase_request');
    global $db;
    
    $itemCode = trim($_POST['item_code'] ?? '');
    $requestedQty = floatval($_POST['requested_qty'] ?? 0);
    $priority = $_POST['priority'] ?? 'Medium';
    $reason = trim($_POST['reason'] ?? '');
    
    // Validation
    if (empty($itemCode)) {
        throw new Exception('Vui lòng chọn vật tư');
    }
    
    if ($requestedQty <= 0) {
        throw new Exception('Số lượng phải lớn hơn 0');
    }
    
    // Get spare part info
    $sparePart = $db->fetch("
        SELECT sp.*, COALESCE(oh.Onhand, 0) as current_stock
        FROM spare_parts sp
        LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
        WHERE sp.item_code = ? AND sp.is_active = 1
    ", [$itemCode]);
    
    if (!$sparePart) {
        throw new Exception('Không tìm thấy vật tư');
    }
    
    $db->beginTransaction();
    
    try {
        $requestCode = generatePurchaseRequestCode();
        $estimatedCost = $requestedQty * $sparePart['standard_cost'];
        
        $sql = "INSERT INTO purchase_requests 
                (request_code, item_code, requested_qty, unit, current_stock, min_stock, 
                 estimated_cost, priority, reason, requested_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $requestCode, $itemCode, $requestedQty, $sparePart['unit'],
            $sparePart['current_stock'], $sparePart['min_stock'], $estimatedCost,
            $priority, $reason, getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $requestId = $db->lastInsertId();
        
        // Log activity
        logActivity('create_purchase_request', 'spare_parts', "Created purchase request: $requestCode for $itemCode");
        
        $db->commit();
        
        successResponse(['id' => $requestId, 'code' => $requestCode], 'Tạo đề xuất mua hàng thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleApprove() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $db->execute("
        UPDATE purchase_requests 
        SET status = 'approved', approved_by = ?, updated_at = NOW() 
        WHERE id = ?
    ", [getCurrentUser()['id'], $id]);
    
    logActivity('approve_purchase_request', 'spare_parts', "Approved purchase request ID: $id");
    
    successResponse([], 'Đã duyệt đề xuất mua hàng');
}

function handleReject() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    $reason = trim($_POST['reject_reason'] ?? '');
    
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    $db->execute("
        UPDATE purchase_requests 
        SET status = 'rejected', approved_by = ?, reason = CONCAT(COALESCE(reason, ''), '\n\nLý do từ chối: ', ?), updated_at = NOW() 
        WHERE id = ?
    ", [getCurrentUser()['id'], $reason, $id]);
    
    logActivity('reject_purchase_request', 'spare_parts', "Rejected purchase request ID: $id");
    
    successResponse([], 'Đã từ chối đề xuất mua hàng');
}

function handleList() {
    global $db;
    
    $status = $_GET['status'] ?? '';
    $sql = "SELECT pr.*, sp.item_name, u.full_name as requested_by_name
            FROM purchase_requests pr
            JOIN spare_parts sp ON pr.item_code = sp.item_code
            LEFT JOIN users u ON pr.requested_by = u.id
            WHERE 1=1";
    
    $params = [];
    
    if ($status) {
        $sql .= " AND pr.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY pr.created_at DESC";
    
    $requests = $db->fetchAll($sql, $params);
    
    successResponse($requests);
}
?>