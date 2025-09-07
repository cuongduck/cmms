<?php
/**
 * Inventory Statistics API
 * /modules/inventory/api/stats.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('inventory', 'view');

try {
    // Get current statistics
    $stats = [
        'total_items' => $db->fetch("SELECT COUNT(*) as count FROM onhand")['count'],
        'in_bom_items' => $db->fetch("SELECT COUNT(DISTINCT o.ItemCode) as count FROM onhand o JOIN parts p ON o.ItemCode = p.part_code")['count'],
        'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand WHERE Onhand <= 0")['count'],
        'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand o LEFT JOIN parts p ON o.ItemCode = p.part_code WHERE p.min_stock > 0 AND o.Onhand < p.min_stock AND o.Onhand > 0")['count'],
        'total_value' => $db->fetch("SELECT SUM(OH_Value) as total FROM onhand")['total'] ?? 0,
        'excess_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand o LEFT JOIN parts p ON o.ItemCode = p.part_code WHERE p.max_stock > 0 AND o.Onhand > p.max_stock")['count']
    ];
    
    // Get stock distribution
    $stockDistribution = [
        'normal' => $stats['total_items'] - $stats['out_of_stock'] - $stats['low_stock'] - $stats['excess_stock'],
        'low_stock' => $stats['low_stock'],
        'out_of_stock' => $stats['out_of_stock'],
        'excess_stock' => $stats['excess_stock']
    ];
    
    // Get category breakdown
    $categorySql = "SELECT 
        COALESCE(p.category, 'Không phân loại') as category,
        COUNT(*) as count,
        SUM(o.OH_Value) as total_value
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    GROUP BY COALESCE(p.category, 'Không phân loại')
    ORDER BY count DESC
    LIMIT 10";
    
    $categoryBreakdown = $db->fetchAll($categorySql);
    
    // Get recent transaction summary
    $transactionSql = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN TransactedQty > 0 THEN TransactedQty ELSE 0 END) as total_in,
        SUM(CASE WHEN TransactedQty < 0 THEN ABS(TransactedQty) ELSE 0 END) as total_out,
        MAX(TransactionDate) as last_transaction
    FROM transaction 
    WHERE TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $transactionSummary = $db->fetch($transactionSql);
    
    // Get top items by value
    $topItemsSql = "SELECT 
        o.ItemCode,
        o.Itemname,
        o.Onhand,
        o.OH_Value,
        p.part_name,
        CASE 
            WHEN p.id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE o.OH_Value > 0
    ORDER BY o.OH_Value DESC
    LIMIT 10";
    
    $topItems = $db->fetchAll($topItemsSql);
    
    // Get items needing attention (low stock + out of stock)
    $attentionItemsSql = "SELECT 
        o.ItemCode,
        o.Itemname,
        o.Onhand,
        o.UOM,
        p.min_stock,
        p.part_name,
        CASE
            WHEN o.Onhand <= 0 THEN 'Hết hàng'
            WHEN p.min_stock > 0 AND o.Onhand < p.min_stock THEN 'Thiếu hàng'
            ELSE 'Bình thường'
        END as status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE o.Onhand <= 0 OR (p.min_stock > 0 AND o.Onhand < p.min_stock)
    ORDER BY 
        CASE WHEN o.Onhand <= 0 THEN 1 ELSE 2 END,
        o.Onhand ASC
    LIMIT 20";
    
    $attentionItems = $db->fetchAll($attentionItemsSql);
    
    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'stock_distribution' => $stockDistribution,
        'category_breakdown' => $categoryBreakdown,
        'transaction_summary' => $transactionSummary,
        'top_items' => $topItems,
        'attention_items' => $attentionItems,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Stats API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi tải thống kê'
    ], 500);
}
?>