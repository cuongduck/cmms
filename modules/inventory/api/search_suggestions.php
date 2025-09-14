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
    
    // Search in inventory items với logic BOM mới
    $sql = "SELECT DISTINCT 
        o.ItemCode as value,
        o.Itemname as label,
        'Mã vật tư' as type,
        p.part_name,
        CASE 
            WHEN bi.part_id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    LEFT JOIN bom_items bi ON p.id = bi.part_id
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
    
    jsonResponse(['suggestions' => $suggestions]);
    
} catch (Exception $e) {
    error_log("Search suggestions error: " . $e->getMessage());
    jsonResponse(['suggestions' => []]);
}
?>