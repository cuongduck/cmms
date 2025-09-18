<?php
/**
 * Transactions Export API
 * /modules/transactions/api/export.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

requirePermission('inventory', 'export');

$exportType = $_GET['export'] ?? 'excel';
$search = $_GET['search'] ?? '';
$brandy = $_GET['brandy'] ?? '';
$requester = $_GET['requester'] ?? '';
$month = $_GET['month'] ?? date('Y-m');
$view_type = $_GET['view_type'] ?? 'month';

function getBrandyName($brandy) {
    $brands = [
        'IN0000' => 'Mì',
        'ID0000' => 'CSD', 
        'FS0000' => 'Mắm',
        'WPBB20' => 'PET',
        'WPNA30' => 'Nêm',
        'RB0000' => 'Phở',
        '0' => 'Bảo trì chung'
    ];
    return $brands[$brandy] ?? 'Khác';
}

try {
    // Build the same SQL query as in index.php - SỬA LẠI
    if ($view_type === 'year') {
        $year = substr($month, 0, 4);
        $dateFilter = "YEAR(TransactionDate) = ?";
        $dateParams = [$year];
    } else {
        $dateFilter = "DATE_FORMAT(TransactionDate, '%Y-%m') = ?";
        $dateParams = [$month];
    }

    $sql = "SELECT 
        t.Number as 'Số phiếu',
        DATE_FORMAT(t.TransactionDate, '%d/%m/%Y %H:%i') as 'Ngày giao dịch',
        t.ItemCode as 'Mã vật tư',
        t.ItemDesc as 'Tên vật tư',
        t.TransactedQty as 'Số lượng xuất',
        t.UOM as 'Đơn vị tính',
        FORMAT(t.Price, 0) as 'Đơn giá (VNĐ)',
        FORMAT(t.TotalAmount, 0) as 'Tổng tiền (VNĐ)',
        CASE 
            WHEN t.Brandy = 'IN0000' THEN 'Mì'
            WHEN t.Brandy = 'ID0000' THEN 'CSD'
            WHEN t.Brandy = 'FS0000' THEN 'Mắm'
            WHEN t.Brandy = 'WPBB20' THEN 'PET'
            WHEN t.Brandy = 'WPNA30' THEN 'Nêm'
            WHEN t.Brandy = 'RB0000' THEN 'Phở'
            WHEN t.Brandy = '0' THEN 'Bảo trì chung'
            ELSE 'Khác'
        END as 'Ngành hàng',
        t.Locator as 'Kho',
        t.Department as 'Bộ phận',
        t.Requester as 'Người yêu cầu',
        t.Reason as 'Lý do xuất',
        t.Comment as 'Ghi chú',
        t.Status as 'Trạng thái'
    FROM transaction t 
    WHERE t.TransactionType = 'Issue' 
    AND t.Status IN ('Posted', 'Approved') 
    AND $dateFilter";
    
    $params = $dateParams;
    
    // Apply filters
    if (!empty($search)) {
        $sql .= " AND (t.ItemCode LIKE ? OR t.ItemDesc LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm]);
    }
    
    if (!empty($brandy)) {
        $sql .= " AND t.Brandy = ?";
        $params[] = $brandy;
    }
    
    if (!empty($requester)) {
        $sql .= " AND t.Requester LIKE ?";
        $params[] = '%' . $requester . '%';
    }
    
    $sql .= " ORDER BY t.TransactionDate DESC, t.ID DESC";
    
    $data = $db->fetchAll($sql, $params);
    
    if (empty($data)) {
        die('Không có dữ liệu để xuất');
    }
    
    switch ($exportType) {
        case 'excel':
            exportToExcel($data, $month, $view_type);
            break;
        case 'csv':
            exportToCSV($data, $month, $view_type);
            break;
        default:
            die('Định dạng xuất không hợp lệ');
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die('Có lỗi xảy ra khi xuất dữ liệu');
}

function exportToExcel($data, $month, $view_type) {
    $period = $view_type === 'year' ? "Nam_$month" : "Thang_" . str_replace('-', '_', $month);
    $filename = "giao_dich_xuat_kho_$period.xlsx";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    
    // Title
    echo '<tr><td colspan="' . count(array_keys($data[0])) . '" style="text-align: center; font-weight: bold; font-size: 16px;">';
    echo 'BÁO CÁO GIAO DỊCH XUẤT KHO - ' . strtoupper($period);
    echo '</td></tr>';
    echo '<tr><td colspan="' . count(array_keys($data[0])) . '" style="text-align: center;">';
    echo 'Ngày xuất: ' . date('d/m/Y H:i:s');
    echo '</td></tr>';
    echo '<tr><td colspan="' . count(array_keys($data[0])) . '"></td></tr>';
    
    // Headers
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
    
    echo '</table></body></html>';
    exit;
}

function exportToCSV($data, $month, $view_type) {
    $period = $view_type === 'year' ? "nam_$month" : "thang_" . str_replace('-', '_', $month);
    $filename = "giao_dich_xuat_kho_$period.csv";
    
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