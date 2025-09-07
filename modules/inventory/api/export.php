<?php
/**
 * Inventory Export API
 * /modules/inventory/api/export.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

requirePermission('inventory', 'export');

$exportType = $_GET['export'] ?? 'excel';
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$bom_status = $_GET['bom_status'] ?? '';

try {
    // Build the same SQL query as in index.php
    $sql = "SELECT 
        o.ItemCode as 'Mã vật tư',
        o.Itemname as 'Tên vật tư',
        o.Locator as 'Vị trí kho',
        o.Lotnumber as 'Số Lot',
        o.Onhand as 'Tồn kho',
        o.UOM as 'ĐVT',
        o.Price as 'Đơn giá',
        o.OH_Value as 'Tổng giá trị',
        p.part_code as 'Mã BOM',
        p.part_name as 'Tên BOM',
        p.category as 'Phân loại',
        p.min_stock as 'Tồn kho tối thiểu',
        p.max_stock as 'Tồn kho tối đa',
        p.supplier_name as 'Nhà cung cấp',
        CASE 
            WHEN p.id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as 'Loại vật tư',
        CASE
            WHEN o.Onhand <= 0 THEN 'Hết hàng'
            WHEN p.min_stock > 0 AND o.Onhand < p.min_stock THEN 'Thiếu hàng'
            WHEN p.max_stock > 0 AND o.Onhand > p.max_stock THEN 'Dư thừa'
            ELSE 'Bình thường'
        END as 'Trạng thái tồn kho'
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if (!empty($search)) {
        $sql .= " AND (o.ItemCode LIKE ? OR o.Itemname LIKE ? OR p.part_name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($category)) {
        $sql .= " AND p.category = ?";
        $params[] = $category;
    }
    
    if (!empty($status)) {
        switch ($status) {
            case 'out_of_stock':
                $sql .= " AND o.Onhand <= 0";
                break;
            case 'low_stock':
                $sql .= " AND p.min_stock > 0 AND o.Onhand < p.min_stock AND o.Onhand > 0";
                break;
            case 'excess_stock':
                $sql .= " AND p.max_stock > 0 AND o.Onhand > p.max_stock";
                break;
            case 'normal':
                $sql .= " AND o.Onhand > 0 AND (p.min_stock <= 0 OR o.Onhand >= p.min_stock) AND (p.max_stock <= 0 OR o.Onhand <= p.max_stock)";
                break;
        }
    }
    
    if (!empty($bom_status)) {
        if ($bom_status === 'in_bom') {
            $sql .= " AND p.id IS NOT NULL";
        } else {
            $sql .= " AND p.id IS NULL";
        }
    }
    
    $sql .= " ORDER BY o.Itemname ASC";
    
    $data = $db->fetchAll($sql, $params);
    
    if (empty($data)) {
        die('Không có dữ liệu để xuất');
    }
    
    switch ($exportType) {
        case 'excel':
            exportToExcel($data);
            break;
        case 'csv':
            exportToCSV($data);
            break;
        default:
            die('Định dạng xuất không hợp lệ');
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die('Có lỗi xảy ra khi xuất dữ liệu');
}

function exportToExcel($data) {
    $filename = 'inventory_export_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Simple Excel export using HTML table format
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    
    // Headers
    if (!empty($data)) {
        echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
        foreach (array_keys($data[0]) as $header) {
            echo '<td>' . htmlspecialchars($header) . '</td>';
        }
        echo '</tr>';
        
        // Data rows
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell ?? '') . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table></body></html>';
    exit;
}

function exportToCSV($data) {
    $filename = 'inventory_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>