<?php
/**
 * Item Details API - Fixed Version
 */

// Đặt error handling ở đầu để tránh output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0); // Tắt hiển thị lỗi để không làm hỏng JSON

try {
    require_once '../../../config/config.php';
    require_once '../../../config/database.php';
    require_once '../../../config/auth.php';

    header('Content-Type: application/json; charset=utf-8');

    // Kiểm tra session trước
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $itemCode = $_GET['item_code'] ?? '';

    if (empty($itemCode)) {
        echo json_encode(['success' => false, 'message' => 'Mã vật tư không hợp lệ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Get item details với logic BOM mới
    $sql = "SELECT 
        o.*,
        p.id as part_id,
        p.part_code,
        p.part_name,
        p.description as part_description,
        p.category,
        p.specifications,
        p.manufacturer,
        p.supplier_name,
        p.unit_price as part_unit_price,
        p.min_stock,
        p.max_stock,
        p.lead_time,
        p.notes as part_notes,
        CASE 
            WHEN bi.part_id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status,
        CASE
            WHEN COALESCE(o.Onhand, 0) <= 0 THEN 'Hết hàng'
            WHEN p.min_stock > 0 AND COALESCE(o.Onhand, 0) < p.min_stock THEN 'Thiếu hàng'
            WHEN p.max_stock > 0 AND COALESCE(o.Onhand, 0) > p.max_stock THEN 'Dư thừa'
            ELSE 'Bình thường'
        END as stock_status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    LEFT JOIN bom_items bi ON p.id = bi.part_id
    WHERE o.ItemCode = ?";
    
    $item = $db->fetch($sql, [$itemCode]);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy vật tư'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get recent transactions (10 giao dịch gần nhất)
    $transactionSql = "SELECT * FROM transaction 
                      WHERE ItemCode = ? 
                      ORDER BY TransactionDate DESC 
                      LIMIT 10";
    $recentTransactions = $db->fetchAll($transactionSql, [$itemCode]);
    
    // Get transaction summary
    $summarySql = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN COALESCE(TransactedQty, 0) > 0 THEN TransactedQty ELSE 0 END) as total_in,
        SUM(CASE WHEN COALESCE(TransactedQty, 0) < 0 THEN ABS(TransactedQty) ELSE 0 END) as total_out,
        SUM(CASE WHEN COALESCE(TransactedQty, 0) > 0 THEN COALESCE(TotalAmount, 0) ELSE 0 END) as total_value_in,
        SUM(CASE WHEN COALESCE(TransactedQty, 0) < 0 THEN ABS(COALESCE(TotalAmount, 0)) ELSE 0 END) as total_value_out,
        MAX(TransactionDate) as last_transaction_date
    FROM transaction 
    WHERE ItemCode = ?";
    $summary = $db->fetch($summarySql, [$itemCode]);
    
    // Get BOM usage if applicable
    $bomUsage = [];
    if ($item['part_id']) {
        $bomSql = "SELECT 
            mb.bom_name,
            mb.bom_code,
            bi.quantity,
            bi.unit,
            bi.position,
            bi.priority,
            bi.maintenance_interval,
            mt.name as machine_type_name
        FROM bom_items bi
        JOIN machine_bom mb ON bi.bom_id = mb.id
        JOIN machine_types mt ON mb.machine_type_id = mt.id
        WHERE bi.part_id = ?
        ORDER BY mt.name, mb.bom_name";
        $bomUsage = $db->fetchAll($bomSql, [$item['part_id']]);
    }
    
    // Capture HTML output
    ob_start();
    include 'item_details_template.php'; // Tách HTML ra file riêng
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'item' => $item,
        'summary' => $summary,
        'bom_usage' => $bomUsage
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in item details API: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>