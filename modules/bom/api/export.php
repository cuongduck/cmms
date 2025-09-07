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
                   COUNT(bi.id) as total_items,
                   SUM(bi.quantity * p.unit_price) as total_cost,
                   u.fullname as created_by_name
            FROM machine_bom mb
            LEFT JOIN machine_types mt ON mb.machine_type_id = mt.id
            LEFT JOIN bom_items bi ON mb.id = bi.bom_id
            LEFT JOIN parts p ON bi.part_id = p.id
            LEFT JOIN users u ON mb.created_by = u.id
            WHERE $whereClause
            GROUP BY mb.id
            ORDER BY mb.bom_code";
    
    $boms = $db->fetchAll($sql, $params);
    
    $filename = 'All_BOMs_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportAllBOMsToExcel($boms, $filename);
    } elseif ($format === 'csv') {
        exportAllBOMsToCSV($boms, $filename);
    } else {
        throw new Exception('Unsupported format');
    }
}

/**
 * Export parts with filters
 */
function exportParts() {
    global $db, $format;
    
    $filters = [
        'category' => $_GET['category'] ?? '',
        'stock_status' => $_GET['stock_status'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    // Build WHERE conditions
    $whereConditions = ['1=1'];
    $params = [];
    
    if (!empty($filters['category'])) {
        $whereConditions[] = 'p.category = ?';
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['stock_status'])) {
        if ($filters['stock_status'] === 'ok') {
            $whereConditions[] = 'COALESCE(oh.Onhand, 0) >= p.min_stock OR p.min_stock = 0';
        } elseif ($filters['stock_status'] === 'low') {
            $whereConditions[] = 'COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0';
        } elseif ($filters['stock_status'] === 'out') {
            $whereConditions[] = 'COALESCE(oh.Onhand, 0) = 0';
        }
    }
    
    if (!empty($filters['search'])) {
        $whereConditions[] = '(p.part_code LIKE ? OR p.part_name LIKE ? OR p.description LIKE ?)';
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get parts data
    $sql = "SELECT p.*, 
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   (SELECT COUNT(*) FROM bom_items bi WHERE bi.part_id = p.id) as usage_count,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status,
                   ps.supplier_name
            FROM parts p
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            LEFT JOIN part_suppliers ps ON p.id = ps.part_id AND ps.is_preferred = 1
            WHERE $whereClause
            ORDER BY p.part_code";
    
    $parts = $db->fetchAll($sql, $params);
    
    $filename = 'Parts_' . date('Y-m-d');
    
    if ($format === 'excel') {
        exportPartsToExcel($parts, $filename);
    } elseif ($format === 'csv') {
        exportPartsToCSV($parts, $filename);
    } elseif ($format === 'pdf') {
        exportPartsToPDF($parts, $filename);
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
    if (!$bomId) {
        throw new Exception('BOM ID is required');
    }
    
    $bom = getBOMDetails($bomId);
    if (!$bom) {
        throw new Exception('BOM not found');
    }
    
    $filename = 'Stock_Report_' . $bom['bom_code'] . '_' . date('Y-m-d');
    
    if ($format === 'csv') {
        exportBOMStockReport($bom, $filename);
    } else {
        throw new Exception('Unsupported format for stock report');
    }
}

/**
 * Export shortage report
 */
function exportShortageReport() {
    global $db, $format;
    
    $bomId = intval($_GET['bom_id'] ?? 0);
    $partId = intval($_GET['part_id'] ?? 0);
    
    if (!$bomId && !$partId) {
        throw new Exception('BOM ID or Part ID is required');
    }
    
    $whereConditions = [];
    $params = [];
    
    if ($bomId) {
        $whereConditions[] = 'bi.bom_id = ?';
        $params[] = $bomId;
    }
    
    if ($partId) {
        $whereConditions[] = 'bi.part_id = ?';
        $params[] = $partId;
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "SELECT bi.*, p.part_code, p.part_name, p.unit, p.unit_price, p.min_stock,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock AND p.min_stock > 0 THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM bom_items bi
            JOIN parts p ON bi.part_id = p.id
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            $whereClause
            ORDER BY p.part_code";
    
    $bom['items'] = $db->fetchAll($sql, $params);
    
    $filename = 'Shortage_Report_' . date('Y-m-d');
    
    if ($format === 'csv') {
        exportBOMStockReport($bom, $filename);
    } else {
        throw new Exception('Unsupported format for shortage report');
    }
}

/**
 * Export all BOMs to Excel
 */
function exportAllBOMsToExcel($boms, $filename) {
    // Implementation same as original (not shown as it was truncated)
}

/**
 * Export all BOMs to CSV
 */
function exportAllBOMsToCSV($boms, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
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

/**
 * Helper function to get stock status text for export
 */
function getStockStatusText($status) {
    $texts = [
        'OK' => 'Đủ hàng',
        'Low' => 'Sắp hết', 
        'Out of Stock' => 'Hết hàng'
    ];
    
    return $texts[$status] ?? $status;
}

/**
 * Placeholder for getBOMDetails (not provided in original)
 */
function getBOMDetails($bomId) {
    global $db;
    // Placeholder implementation - replace with actual if needed
    $sql = "SELECT mb.*, mt.name as machine_type_name, mt.code as machine_type_code
            FROM machine_bom mb
            LEFT JOIN machine_types mt ON mb.machine_type_id = mt.id
            WHERE mb.id = ?";
    $bom = $db->fetch($sql, [$bomId]);
    
    if ($bom) {
        $bom['items'] = $db->fetchAll(
            "SELECT bi.*, p.part_code, p.part_name, p.unit, p.unit_price
             FROM bom_items bi
             JOIN parts p ON bi.part_id = p.id
             WHERE bi.bom_id = ?",
            [$bomId]
        );
    }
    
    return $bom;
}

/**
 * Placeholder for exportBOMToExcel
 */
function exportBOMToExcel($bom, $filename) {
    // Implementation same as original (not shown as it was truncated)
}

/**
 * Placeholder for exportBOMToPDF
 */
function exportBOMToPDF($bom, $filename) {
    // Implementation same as original (not shown as it was truncated)
}

/**
 * Placeholder for exportBOMDetailToExcel
 */
function exportBOMDetailToExcel($bom, $filename) {
    // Implementation same as original (not shown as it was truncated)
}

/**
 * Placeholder for exportPartsToExcel
 */
function exportPartsToExcel($parts, $filename) {
    // Implementation same as original (not shown as it was truncated)
}

/**
 * Placeholder for exportPartsToPDF
 */
function exportPartsToPDF($parts, $filename) {
    // Implementation same as original (not shown as it was truncated)
}
?>