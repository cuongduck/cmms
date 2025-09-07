<?php
/**
 * Search Suggestions API
 * /modules/inventory/api/search_suggestions.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('inventory', 'view');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    jsonResponse(['suggestions' => []]);
}

try {
    $suggestions = [];
    $searchTerm = '%' . $query . '%';
    
    // Search in inventory items
    $sql = "SELECT DISTINCT 
        o.ItemCode as value,
        o.Itemname as label,
        'Mã vật tư' as type,
        p.part_name,
        CASE 
            WHEN p.id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE (o.ItemCode LIKE ? OR o.Itemname LIKE ?)
    ORDER BY 
        CASE WHEN o.ItemCode LIKE ? THEN 1 ELSE 2 END,
        o.Itemname
    LIMIT 10";
    
    $items = $db->fetchAll($sql, [$searchTerm, $searchTerm, $query . '%']);
    
    foreach ($items as $item) {
        $label = $item['label'];
        if ($item['part_name']) {
            $label .= ' (' . $item['part_name'] . ')';
        }
        
        $suggestions[] = [
            'value' => $item['value'],
            'label' => $label,
            'type' => $item['type'] . ' - ' . $item['bom_status']
        ];
    }
    
    // Search in categories
    if (count($suggestions) < 10) {
        $categorySql = "SELECT DISTINCT category as value, category as label, 'Phân loại' as type
                       FROM parts 
                       WHERE category LIKE ? AND category IS NOT NULL
                       ORDER BY category
                       LIMIT ?";
        
        $categories = $db->fetchAll($categorySql, [$searchTerm, 10 - count($suggestions)]);
        
        foreach ($categories as $category) {
            $suggestions[] = [
                'value' => $category['value'],
                'label' => $category['label'],
                'type' => $category['type']
            ];
        }
    }
    
    // Search in part names
    if (count($suggestions) < 10) {
        $partSql = "SELECT DISTINCT 
            p.part_code as value, 
            p.part_name as label, 
            'Tên BOM' as type
        FROM parts p
        WHERE p.part_name LIKE ?
        ORDER BY p.part_name
        LIMIT ?";
        
        $parts = $db->fetchAll($partSql, [$searchTerm, 10 - count($suggestions)]);
        
        foreach ($parts as $part) {
            $suggestions[] = [
                'value' => $part['value'],
                'label' => $part['label'],
                'type' => $part['type']
            ];
        }
    }
    
    jsonResponse(['suggestions' => $suggestions]);
    
} catch (Exception $e) {
    error_log("Search suggestions error: " . $e->getMessage());
    jsonResponse(['suggestions' => []]);
}
?>