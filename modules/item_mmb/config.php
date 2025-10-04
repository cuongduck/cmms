<?php
/**
 * Item MMB Module Configuration
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';

// Module settings
define('ITEM_MMB_MODULE', 'item_mmb');
define('ITEM_MMB_PER_PAGE', 50);

/**
 * Get all items with filters
 */
function getItemsMMB($filters = []) {
    global $db;
    
    $sql = "SELECT * FROM item_mmb WHERE 1=1";
    $params = [];
    
    // Search filter
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (ITEM_CODE LIKE ? OR ITEM_NAME LIKE ? OR VENDOR_NAME LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    // Vendor filter
    if (!empty($filters['vendor'])) {
        $sql .= " AND VENDOR_NAME = ?";
        $params[] = $filters['vendor'];
    }
    
    // Sort
    $sortField = $filters['sort'] ?? 'TIME_UPDATE';
    $sortOrder = $filters['order'] ?? 'DESC';
    $sql .= " ORDER BY {$sortField} {$sortOrder}";
    
    // Pagination
    if (isset($filters['limit'])) {
        $offset = ($filters['page'] - 1) * $filters['limit'];
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $filters['limit'];
        $params[] = $offset;
    }
    
    return $db->fetchAll($sql, $params);
}

/**
 * Get total count for pagination
 */
function getItemsMMBCount($filters = []) {
    global $db;
    
    $sql = "SELECT COUNT(*) as count FROM item_mmb WHERE 1=1";
    $params = [];
    
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (ITEM_CODE LIKE ? OR ITEM_NAME LIKE ? OR VENDOR_NAME LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($filters['vendor'])) {
        $sql .= " AND VENDOR_NAME = ?";
        $params[] = $filters['vendor'];
    }
    
    $result = $db->fetch($sql, $params);
    return $result['count'];
}

/**
 * Get item by ID
 */
function getItemMMB($id) {
    global $db;
    return $db->fetch("SELECT * FROM item_mmb WHERE ID = ?", [$id]);
}

/**
 * Create new item
 */
function createItemMMB($data) {
    global $db;
    
    // Validation
    if (empty($data['ITEM_CODE'])) {
        return ['success' => false, 'message' => 'Mã item không được để trống'];
    }
    
    if (empty($data['ITEM_NAME'])) {
        return ['success' => false, 'message' => 'Tên item không được để trống'];
    }
    
    // Check duplicate
    $existing = $db->fetch("SELECT ID FROM item_mmb WHERE ITEM_CODE = ?", [$data['ITEM_CODE']]);
    if ($existing) {
        return ['success' => false, 'message' => 'Mã item đã tồn tại'];
    }
    
    $sql = "INSERT INTO item_mmb (ITEM_CODE, ITEM_NAME, UOM, UNIT_PRICE, VENDOR_ID, VENDOR_NAME, TIME_UPDATE) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $data['ITEM_CODE'],
        $data['ITEM_NAME'],
        $data['UOM'] ?? null,
        $data['UNIT_PRICE'] ?? null,
        $data['VENDOR_ID'] ?? null,
        $data['VENDOR_NAME'] ?? null
    ];
    
    try {
        $db->execute($sql, $params);
        $id = $db->lastInsertId();
        
        logActivity('create_item_mmb', 'item_mmb', "Created item: {$data['ITEM_CODE']}");
        
        return ['success' => true, 'message' => 'Tạo item thành công', 'id' => $id];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Update item
 */
function updateItemMMB($id, $data) {
    global $db;
    
    // Validation
    if (empty($data['ITEM_CODE'])) {
        return ['success' => false, 'message' => 'Mã item không được để trống'];
    }
    
    if (empty($data['ITEM_NAME'])) {
        return ['success' => false, 'message' => 'Tên item không được để trống'];
    }
    
    // Check duplicate (exclude current)
    $existing = $db->fetch("SELECT ID FROM item_mmb WHERE ITEM_CODE = ? AND ID != ?", 
                          [$data['ITEM_CODE'], $id]);
    if ($existing) {
        return ['success' => false, 'message' => 'Mã item đã tồn tại'];
    }
    
    $sql = "UPDATE item_mmb SET 
            ITEM_CODE = ?, ITEM_NAME = ?, UOM = ?, UNIT_PRICE = ?, 
            VENDOR_ID = ?, VENDOR_NAME = ?, TIME_UPDATE = NOW() 
            WHERE ID = ?";
    
    $params = [
        $data['ITEM_CODE'],
        $data['ITEM_NAME'],
        $data['UOM'] ?? null,
        $data['UNIT_PRICE'] ?? null,
        $data['VENDOR_ID'] ?? null,
        $data['VENDOR_NAME'] ?? null,
        $id
    ];
    
    try {
        $db->execute($sql, $params);
        
        logActivity('update_item_mmb', 'item_mmb', "Updated item ID: {$id}");
        
        return ['success' => true, 'message' => 'Cập nhật thành công'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Delete item
 */
function deleteItemMMB($id) {
    global $db;
    
    $item = getItemMMB($id);
    if (!$item) {
        return ['success' => false, 'message' => 'Không tìm thấy item'];
    }
    
    try {
        $db->execute("DELETE FROM item_mmb WHERE ID = ?", [$id]);
        
        logActivity('delete_item_mmb', 'item_mmb', "Deleted item ID: {$id}");
        
        return ['success' => true, 'message' => 'Xóa thành công'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Get unique vendors for filter
 */
function getVendorsMMB() {
    global $db;
    return $db->fetchAll("SELECT DISTINCT VENDOR_NAME FROM item_mmb WHERE VENDOR_NAME IS NOT NULL ORDER BY VENDOR_NAME");
}

/**
 * Export to Excel
 */
function exportItemsMMBToExcel($filters = []) {
    $items = getItemsMMB($filters);
    
    $filename = 'item_mmb_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['ID', 'Mã Item', 'Tên Item', 'Đơn vị', 'Đơn giá', 'Mã NCC', 'Tên NCC', 'Ngày cập nhật']);
    
    // Data
    foreach ($items as $item) {
        fputcsv($output, [
            $item['ID'],
            $item['ITEM_CODE'],
            $item['ITEM_NAME'],
            $item['UOM'],
            $item['UNIT_PRICE'],
            $item['VENDOR_ID'],
            $item['VENDOR_NAME'],
            $item['TIME_UPDATE']
        ]);
    }
    
    fclose($output);
    exit;
}
?>