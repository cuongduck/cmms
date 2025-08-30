<?php
/**
 * Export API Handler
 * /modules/bom/api/export.php
 * API endpoint cho Export operations
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

// Check login and permission
requireLogin();
requirePermission('bom', 'export');

$action = $_GET['action'] ?? '';
$format = $_GET['format'] ?? 'excel';

try {
    switch ($action) {
        case 'bom':
            exportBOM();
            break;
            
        case 'bom_detail':
            exportBOMDetail();
            break;
            
        case 'export_all':
            exportAllBOMs();
            break;
            
        case 'parts':
            exportParts();
            break;
            
        case 'stock_report':
            exportStockReport();
            break;
            
        case 'shortage_report':
            exportShortageReport();
            break;
            
        default:
            throw new Exception('Invalid export action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo "Export Error: " . $e->getMessage();
    exit;
}

/**
 * Export single BOM
 */
function exportBOM() {
    global $db, $format;
    
    $bomId = intval($_GET['id'] ?? 0);
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    // Get BOM details
    $bom = getBOMDetails($bomId);
    if (!$bom) {
        throw new Exception('BOM not found');
    }
    
    $filename = 'BOM_' . $bom['bom_code'] . '_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportBOMToExcel($bom, $filename);
    } elseif ($format === 'pdf') {
        exportBOMToPDF($bom, $filename);
    } else {
        throw new Exception('Unsupported format');
    }
}

/**
 * Export BOM detail with stock info
 */
function exportBOMDetail() {
    global $db, $format;
    
    $bomId = intval($_GET['id'] ?? 0);
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    $bom = getBOMDetails($bomId);
    if (!$bom) {
        throw new Exception('BOM not found');
    }
    
    $filename = 'BOM_Detail_' . $bom['bom_code'] . '_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportBOMDetailToExcel($bom, $filename);
    } else {
        throw new Exception('Unsupported format for detailed export');
    }
}

/**
 * Export all BOMs with filters
 */
function exportAllBOMs() {
    global $db, $format;
    
    $filters = [
        'machine_type' => $_GET['machine_type'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    // Get BOMs with filters
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
            ORDER BY mb.created_at DESC";
    
    $boms = $db->fetchAll($sql, $params);
    
    $filename = 'BOM_List_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportBOMListToExcel($boms, $filename);
    } elseif ($format === 'csv') {
        exportBOMListToCSV($boms, $filename);
    } else {
        throw new Exception('Unsupported format');
    }
}

/**
 * Export parts list
 */
function exportParts() {
    global $db, $format;
    
    $filters = [
        'category' => $_GET['category'] ?? '',
        'stock_status' => $_GET['stock_status'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    $parts = getPartsList($filters);
    
    $filename = 'Parts_List_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportPartsToExcel($parts, $filename);
    } elseif ($format === 'csv') {
        exportPartsToCSV($parts, $filename);
    } else {
        throw new Exception('Unsupported format');
    }
}

/**
 * Export stock report
 */
function exportStockReport() {
    global $db, $format;
    
    $bomId = intval($_GET['bom_id'] ?? 0);
    
    if ($bomId) {
        // Stock report for specific BOM
        $bom = getBOMDetails($bomId);
        if (!$bom) {
            throw new Exception('BOM not found');
        }
        
        $filename = 'Stock_Report_' . $bom['bom_code'] . '_' . date('Y-m-d');
        exportBOMStockReport($bom, $filename);
    } else {
        // General stock report
        $sql = "SELECT p.*, 
                       COALESCE(oh.Onhand, 0) as stock_quantity,
                       COALESCE(oh.UOM, p.unit) as stock_unit,
                       COALESCE(oh.OH_Value, 0) as stock_value,
                       CASE 
                           WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                           WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                           ELSE 'OK'
                       END as stock_status
                FROM parts p
                LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
                LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
                ORDER BY p.part_code";
        
        $stockData = $db->fetchAll($sql);
        
        $filename = 'Stock_Report_All_' . date('Y-m-d');
        exportGeneralStockReport($stockData, $filename);
    }
}

/**
 * Export shortage report
 */
function exportShortageReport() {
    global $db, $format;
    
    $bomId = intval($_GET['bom_id'] ?? 0);
    
    if ($bomId) {
        // Shortage for specific BOM
        $bom = getBOMDetails($bomId);
        if (!$bom) {
            throw new Exception('BOM not found');
        }
        
        $shortageItems = array_filter($bom['items'], function($item) {
            return in_array(strtolower($item['stock_status']), ['low', 'out of stock']);
        });
        
        $filename = 'Shortage_Report_' . $bom['bom_code'] . '_' . date('Y-m-d');
        exportBOMShortageReport($shortageItems, $bom, $filename);
    } else {
        // General shortage report
        $sql = "SELECT p.*, 
                       COALESCE(oh.Onhand, 0) as stock_quantity,
                       COALESCE(oh.UOM, p.unit) as stock_unit,
                       (p.min_stock - COALESCE(oh.Onhand, 0)) as shortage_qty,
                       (p.min_stock - COALESCE(oh.Onhand, 0)) * p.unit_price as shortage_value
                FROM parts p
                LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
                LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
                WHERE (COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0)
                   OR COALESCE(oh.Onhand, 0) = 0
                ORDER BY p.part_code";
        
        $shortageData = $db->fetchAll($sql);
        
        $filename = 'Shortage_Report_All_' . date('Y-m-d');
        exportGeneralShortageReport($shortageData, $filename);
    }
}

/**
 * Export BOM to Excel
 */
function exportBOMToExcel($bom, $filename) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Start output buffering
    ob_start();
    
    // Create simple Excel format using HTML table (can be opened by Excel)
    echo '<?xml version="1.0"?>
    <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:o="urn:schemas-microsoft-com:office:office"
     xmlns:x="urn:schemas-microsoft-com:office:excel"
     xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
     xmlns:html="http://www.w3.org/TR/REC-html40">
    <Worksheet ss:Name="BOM">
    <Table>';
    
    // BOM Header
    echo '<Row>
        <Cell><Data ss:Type="String">Mã BOM:</Data></Cell>
        <Cell><Data ss:Type="String">' . htmlspecialchars($bom['bom_code']) . '</Data></Cell>
    </Row>
    <Row>
        <Cell><Data ss:Type="String">Tên BOM:</Data></Cell>
        <Cell><Data ss:Type="String">' . htmlspecialchars($bom['bom_name']) . '</Data></Cell>
    </Row>
    <Row>
        <Cell><Data ss:Type="String">Dòng máy:</Data></Cell>
        <Cell><Data ss:Type="String">' . htmlspecialchars($bom['machine_type_name']) . '</Data></Cell>
    </Row>
    <Row>
        <Cell><Data ss:Type="String">Ngày xuất:</Data></Cell>
        <Cell><Data ss:Type="String">' . date('d/m/Y H:i') . '</Data></Cell>
    </Row>
    <Row></Row>';
    
    // Table headers
    echo '<Row>
        <Cell><Data ss:Type="String">STT</Data></Cell>
        <Cell><Data ss:Type="String">Mã linh kiện</Data></Cell>
        <Cell><Data ss:Type="String">Tên linh kiện</Data></Cell>
        <Cell><Data ss:Type="String">Số lượng</Data></Cell>
        <Cell><Data ss:Type="String">Đơn vị</Data></Cell>
        <Cell><Data ss:Type="String">Đơn giá</Data></Cell>
        <Cell><Data ss:Type="String">Thành tiền</Data></Cell>
        <Cell><Data ss:Type="String">Độ ưu tiên</Data></Cell>
        <Cell><Data ss:Type="String">Vị trí</Data></Cell>
    </Row>';
    
    // BOM Items
    $totalCost = 0;
    foreach ($bom['items'] as $index => $item) {
        $totalCost += $item['total_cost'];
        
        echo '<Row>
            <Cell><Data ss:Type="Number">' . ($index + 1) . '</Data></Cell>
            <Cell><Data ss:Type="String">' . htmlspecialchars($item['part_code']) . '</Data></Cell>
            <Cell><Data ss:Type="String">' . htmlspecialchars($item['part_name']) . '</Data></Cell>
            <Cell><Data ss:Type="Number">' . $item['quantity'] . '</Data></Cell>
            <Cell><Data ss:Type="String">' . htmlspecialchars($item['unit']) . '</Data></Cell>
            <Cell><Data ss:Type="Number">' . $item['unit_price'] . '</Data></Cell>
            <Cell><Data ss:Type="Number">' . $item['total_cost'] . '</Data></Cell>
            <Cell><Data ss:Type="String">' . htmlspecialchars($item['priority']) . '</Data></Cell>
            <Cell><Data ss:Type="String">' . htmlspecialchars($item['position']) . '</Data></Cell>
        </Row>';
    }
    
    // Total row
    echo '<Row>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String">Tổng cộng:</Data></Cell>
        <Cell><Data ss:Type="Number">' . $totalCost . '</Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
        <Cell><Data ss:Type="String"></Data></Cell>
    </Row>';
    
    echo '</Table>
    </Worksheet>
    </Workbook>';
    
    // Output the content
    ob_end_flush();
}

/**
 * Export BOM List to CSV
 */
function exportBOMListToCSV($boms, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM (Byte Order Mark) for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'Mã BOM',
        'Tên BOM', 
        'Dòng máy',
        'Mã dòng máy',
        'Phiên bản',
        'Số linh kiện',
        'Tổng chi phí',
        'Ngày tạo',
        'Người tạo'
    ]);
    
    // Data
    foreach ($boms as $bom) {
        fputcsv($output, [
            $bom['bom_code'],
            $bom['bom_name'],
            $bom['machine_type_name'],
            $bom['machine_type_code'],
            $bom['version'],
            $bom['total_items'],
            $bom['total_cost'],
            formatDateTime($bom['created_at']),
            $bom['created_by_name']
        ]);
    }
    
    fclose($output);
}

/**
 * Export Parts to CSV
 */
function exportPartsToCSV($parts, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'Mã linh kiện',
        'Tên linh kiện',
        'Danh mục',
        'Đơn vị',
        'Đơn giá',
        'Tồn kho',
        'Trạng thái kho',
        'Mức tối thiểu',
        'Nhà cung cấp',
        'Số lần sử dụng'
    ]);
    
    // Data
    foreach ($parts as $part) {
        fputcsv($output, [
            $part['part_code'],
            $part['part_name'],
            $part['category'],
            $part['unit'],
            $part['unit_price'],
            $part['stock_quantity'],
            getStockStatusText($part['stock_status']),
            $part['min_stock'],
            $part['supplier_name'],
            $part['usage_count']
        ]);
    }
    
    fclose($output);
}

/**
 * Export BOM Stock Report
 */
function exportBOMStockReport($bom, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    // Headers
    fputcsv($output, [
        'Mã linh kiện',
        'Tên linh kiện',
        'Cần thiết',
        'Đơn vị',
        'Tồn kho hiện tại',
        'Trạng thái',
        'Thiếu hụt',
        'Giá trị thiếu hụt'
    ]);
    
    // Data
    foreach ($bom['items'] as $item) {
        $shortage = max(0, $item['quantity'] - $item['stock_quantity']);
        $shortageValue = $shortage * $item['unit_price'];
        
        fputcsv($output, [
            $item['part_code'],
            $item['part_name'],
            $item['quantity'],
            $item['unit'],
            $item['stock_quantity'],
            getStockStatusText($item['stock_status']),
            $shortage,
            $shortageValue
        ]);
    }
    
    fclose($output);
}

// Helper function to get stock status text for export
function getStockStatusText($status) {
    $texts = [
        'OK' => 'Đủ hàng',
        'Low' => 'Sắp hết', 
        'Out of Stock' => 'Hết hàng'
    ];
    
    return $texts[$status] ?? $status;
}
?>