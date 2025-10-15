<?php
/**
 * Purchase Request API - FIXED VERSION
 * /modules/spare_parts/api/purchase_request.php
 * Xuất Excel trực tiếp không qua PHPSpreadsheet
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

// Check login
try {
    requireLogin();
} catch (Exception $e) {
    http_response_code(401);
    die('Authentication required');
}

// Get request body
$input = null;
if (isset($_POST['data'])) {
    $input = json_decode($_POST['data'], true);
} else {
    $input = json_decode(file_get_contents('php://input'), true);
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'export_excel':
            handleExportExcel($input);
            break;
        
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Purchase Request API Error: " . $e->getMessage());
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}

function handleExportExcel($input) {
    $items = $input['items'] ?? [];
    
    if (empty($items)) {
        throw new Exception('Không có vật tư nào được chọn');
    }
    
    // Generate filename
    $prNumber = 'PR' . date('YmdHis');
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $prNumber . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Start XML Excel format
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    // Styles
    echo '<Styles>' . "\n";
    
    // Header style
    echo '<Style ss:ID="header">' . "\n";
    echo '<Font ss:Bold="1" ss:Size="11"/>' . "\n";
    echo '<Interior ss:Color="#E0E0E0" ss:Pattern="Solid"/>' . "\n";
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    
    // Data style with borders
    echo '<Style ss:ID="data">' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    
    // Number style (right align)
    echo '<Style ss:ID="number">' . "\n";
    echo '<Alignment ss:Horizontal="Right"/>' . "\n";
    echo '<NumberFormat ss:Format="#,##0"/>' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    
    // Center style
    echo '<Style ss:ID="center">' . "\n";
    echo '<Alignment ss:Horizontal="Center"/>' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    
    // Total style
    echo '<Style ss:ID="total">' . "\n";
    echo '<Font ss:Bold="1"/>' . "\n";
    echo '<Alignment ss:Horizontal="Right"/>' . "\n";
    echo '<NumberFormat ss:Format="#,##0"/>' . "\n";
    echo '</Style>' . "\n";
    
    echo '</Styles>' . "\n";
    
    // Worksheet
    echo '<Worksheet ss:Name="Order">' . "\n";
    echo '<Table>' . "\n";
    
    // Column widths
    echo '<Column ss:Width="50"/>' . "\n";   // Line
    echo '<Column ss:Width="120"/>' . "\n";  // Item
    echo '<Column ss:Width="300"/>' . "\n";  // Description
    echo '<Column ss:Width="80"/>' . "\n";   // Quantity
    echo '<Column ss:Width="60"/>' . "\n";   // UOM
    echo '<Column ss:Width="100"/>' . "\n";  // Price
    echo '<Column ss:Width="120"/>' . "\n";  // Amount
    echo '<Column ss:Width="100"/>' . "\n";  // Requester
    
    // Header row
    $headers = ['Line', 'Item', 'Description', 'Quantity', 'UOM', 'Price', 'Amount', 'Requester'];
    echo '<Row ss:Height="25">' . "\n";
    foreach ($headers as $header) {
        echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Data rows
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item['amount'];
        
        echo '<Row>' . "\n";
        
        // Line
        echo '<Cell ss:StyleID="center"><Data ss:Type="Number">' . $item['line'] . '</Data></Cell>' . "\n";
        
        // Item Code
        echo '<Cell ss:StyleID="data"><Data ss:Type="String">' . htmlspecialchars($item['item_code']) . '</Data></Cell>' . "\n";
        
        // Description
        echo '<Cell ss:StyleID="data"><Data ss:Type="String">' . htmlspecialchars($item['item_name']) . '</Data></Cell>' . "\n";
        
        // Quantity
        echo '<Cell ss:StyleID="number"><Data ss:Type="Number">' . $item['quantity'] . '</Data></Cell>' . "\n";
        
        // UOM
        echo '<Cell ss:StyleID="center"><Data ss:Type="String">' . htmlspecialchars($item['uom']) . '</Data></Cell>' . "\n";
        
        // Price
        echo '<Cell ss:StyleID="number"><Data ss:Type="Number">' . $item['price'] . '</Data></Cell>' . "\n";
        
        // Amount
        echo '<Cell ss:StyleID="number"><Data ss:Type="Number">' . $item['amount'] . '</Data></Cell>' . "\n";
        
        // Requester
        echo '<Cell ss:StyleID="center"><Data ss:Type="String">' . htmlspecialchars($item['requester']) . '</Data></Cell>' . "\n";
        
        echo '</Row>' . "\n";
    }
    
    // Total row
    echo '<Row>' . "\n";
    echo '<Cell ss:Index="6" ss:StyleID="total"><Data ss:Type="String">TOTAL:</Data></Cell>' . "\n";
    echo '<Cell ss:StyleID="total"><Data ss:Type="Number">' . $totalAmount . '</Data></Cell>' . "\n";
    echo '<Cell/>' . "\n";
    echo '</Row>' . "\n";
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>';
    
    // Log activity
    global $db;
    $itemCodes = array_column($items, 'item_code');
    $logMessage = "Created purchase request {$prNumber} with " . count($items) . " items: " . implode(', ', array_slice($itemCodes, 0, 5));
    if (count($itemCodes) > 5) {
        $logMessage .= ' and ' . (count($itemCodes) - 5) . ' more';
    }
    
    try {
        if (function_exists('logActivity')) {
            logActivity('create_purchase_request', 'spare_parts', $logMessage);
        }
    } catch (Exception $e) {
        // Ignore logging errors
    }
    
    exit;
}
?>