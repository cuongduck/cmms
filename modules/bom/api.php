<?php
// modules/bom/api.php - BOM API
require_once '../../auth/check_auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        listBOM();
        break;
    case 'detail':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getBOMDetail();
        break;
    case 'equipment_bom_detail':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getEquipmentBOMDetail();
        break;
    case 'create':
        checkApiPermission(['admin', 'to_truong']);
        createBOM();
        break;
    case 'update':
        checkApiPermission(['admin', 'to_truong']);
        updateBOM();
        break;
    case 'delete':
        checkApiPermission(['admin', 'to_truong']);
        deleteBOM();
        break;
    case 'get_dong_may_by_equipment':
        getDongMayByEquipment();
        break;
    case 'get_vat_tu':
        getVatTuList();
        break;
    case 'import':
        checkApiPermission(['admin', 'to_truong']);
        importBOM();
        break;
    case 'export':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        exportBOM();
        break;
    case 'download_template':
        downloadTemplate();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listBOM() {
    global $db;
    
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';
    $equipment_id = $_POST['equipment_id'] ?? '';
    $dong_may_id = $_POST['dong_may_id'] ?? '';
    $chung_loai = $_POST['chung_loai'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(vt.ma_item LIKE ? OR vt.ten_vat_tu LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "bom.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($dong_may_id)) {
        $where_conditions[] = "bom.id_dong_may = ?";
        $params[] = $dong_may_id;
    }
    
    if (!empty($chung_loai)) {
        $where_conditions[] = "vt.chung_loai = ?";
        $params[] = $chung_loai;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM bom_thiet_bi bom
                  JOIN thiet_bi tb ON bom.id_thiet_bi = tb.id
                  JOIN vat_tu vt ON bom.id_vat_tu = vt.id
                  $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated data
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT bom.*, tb.id_thiet_bi, tb.ten_thiet_bi, tb.id as id_thiet_bi_actual,
                   dm.ten_dong_may, vt.ma_item, vt.ten_vat_tu, vt.dvt, vt.gia, vt.chung_loai
            FROM bom_thiet_bi bom
            JOIN thiet_bi tb ON bom.id_thiet_bi = tb.id
            JOIN vat_tu vt ON bom.id_vat_tu = vt.id
            LEFT JOIN dong_may dm ON bom.id_dong_may = dm.id
            $where_clause
            ORDER BY tb.id_thiet_bi, dm.ten_dong_may, vt.ma_item
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bom_items = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $bom_items,
        'pagination' => $pagination
    ]);
}

function getBOMDetail() {
    global $db;
    
    $id = $_GET['id'];
    
    $sql = "SELECT bom.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   dm.ten_dong_may, vt.ma_item, vt.ten_vat_tu, vt.dvt, vt.gia, vt.chung_loai
            FROM bom_thiet_bi bom
            JOIN thiet_bi tb ON bom.id_thiet_bi = tb.id
            JOIN vat_tu vt ON bom.id_vat_tu = vt.id
            LEFT JOIN dong_may dm ON bom.id_dong_may = dm.id
            WHERE bom.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $bom = $stmt->fetch();
    
    if ($bom) {
        jsonResponse(['success' => true, 'data' => $bom]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy BOM'], 404);
    }
}

function getEquipmentBOMDetail() {
    global $db;
    
    $equipment_id = $_GET['equipment_id'];
    
    // Get equipment info
    $equipment_sql = "SELECT tb.*, x.ten_xuong, pl.ten_line
                      FROM thiet_bi tb
                      LEFT JOIN xuong x ON tb.id_xuong = x.id
                      LEFT JOIN production_line pl ON tb.id_line = pl.id
                      WHERE tb.id = ?";
    $equipment_stmt = $db->prepare($equipment_sql);
    $equipment_stmt->execute([$equipment_id]);
    $equipment = $equipment_stmt->fetch();
    
    if (!$equipment) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
    }
    
    // Get BOM items
    $bom_sql = "SELECT bom.*, dm.ten_dong_may, vt.ma_item, vt.ten_vat_tu, vt.dvt, vt.gia, vt.chung_loai
                FROM bom_thiet_bi bom
                JOIN vat_tu vt ON bom.id_vat_tu = vt.id
                LEFT JOIN dong_may dm ON bom.id_dong_may = dm.id
                WHERE bom.id_thiet_bi = ?
                ORDER BY dm.ten_dong_may, vt.ma_item";
    $bom_stmt = $db->prepare($bom_sql);
    $bom_stmt->execute([$equipment_id]);
    $bom_items = $bom_stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'equipment' => $equipment,
            'bom_items' => $bom_items
        ]
    ]);
}

function getDongMayByEquipment() {
    global $db;
    
    $equipment_id = $_GET['equipment_id'];
    
    $sql = "SELECT dm.id, dm.ten_dong_may
            FROM dong_may dm
            JOIN khu_vuc kv ON dm.id_khu_vuc = kv.id
            JOIN production_line pl ON kv.id_line = pl.id
            JOIN thiet_bi tb ON tb.id_line = pl.id
            WHERE tb.id = ?
            ORDER BY dm.ten_dong_may";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$equipment_id]);
    $dong_may = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $dong_may]);
}

function getVatTuList() {
    global $db;
    
    $stmt = $db->query("SELECT id, ma_item, ten_vat_tu, dvt, gia, chung_loai FROM vat_tu ORDER BY ma_item");
    $vat_tu = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $vat_tu]);
}

function createBOM() {
    global $db;
    
    try {
        $data = [
            'id_thiet_bi' => $_POST['id_thiet_bi'],
            'id_dong_may' => $_POST['id_dong_may'] ?: null,
            'id_vat_tu' => $_POST['id_vat_tu'],
            'so_luong' => $_POST['so_luong'],
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Validate required fields
        if (empty($data['id_thiet_bi']) || empty($data['id_vat_tu']) || empty($data['so_luong'])) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
        }
        
        // Check if combination already exists
        $check_sql = "SELECT id FROM bom_thiet_bi WHERE id_thiet_bi = ? AND id_vat_tu = ? AND (id_dong_may = ? OR (id_dong_may IS NULL AND ? IS NULL))";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([$data['id_thiet_bi'], $data['id_vat_tu'], $data['id_dong_may'], $data['id_dong_may']]);
        if ($check_stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Vật tư này đã có trong BOM của thiết bị'], 400);
        }
        
        // Get dong may name if provided
        $ten_dong_may = null;
        if ($data['id_dong_may']) {
            $dm_stmt = $db->prepare("SELECT ten_dong_may FROM dong_may WHERE id = ?");
            $dm_stmt->execute([$data['id_dong_may']]);
            $dong_may_data = $dm_stmt->fetch();
            $ten_dong_may = $dong_may_data ? $dong_may_data['ten_dong_may'] : null;
        }
        
        // Insert BOM item
        $sql = "INSERT INTO bom_thiet_bi (id_thiet_bi, id_dong_may, ten_dong_may, id_vat_tu, so_luong, ghi_chu) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['id_dong_may'], $ten_dong_may,
            $data['id_vat_tu'], $data['so_luong'], $data['ghi_chu']
        ]);
        
        logActivity($_SESSION['user_id'], 'create_bom', "Thêm vật tư vào BOM thiết bị ID: {$data['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Thêm vật tư vào BOM thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi thêm BOM: ' . $e->getMessage()], 500);
    }
}

function updateBOM() {
    global $db;
    
    try {
        $id = $_POST['id'];
        $data = [
            'id_thiet_bi' => $_POST['id_thiet_bi'],
            'id_dong_may' => $_POST['id_dong_may'] ?: null,
            'id_vat_tu' => $_POST['id_vat_tu'],
            'so_luong' => $_POST['so_luong'],
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Get dong may name if provided
        $ten_dong_may = null;
        if ($data['id_dong_may']) {
            $dm_stmt = $db->prepare("SELECT ten_dong_may FROM dong_may WHERE id = ?");
            $dm_stmt->execute([$data['id_dong_may']]);
            $dong_may_data = $dm_stmt->fetch();
            $ten_dong_may = $dong_may_data ? $dong_may_data['ten_dong_may'] : null;
        }
        
        // Update BOM item
        $sql = "UPDATE bom_thiet_bi SET 
                id_thiet_bi = ?, id_dong_may = ?, ten_dong_may = ?, id_vat_tu = ?, 
                so_luong = ?, ghi_chu = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['id_dong_may'], $ten_dong_may,
            $data['id_vat_tu'], $data['so_luong'], $data['ghi_chu'], $id
        ]);
        
        logActivity($_SESSION['user_id'], 'update_bom', "Cập nhật BOM ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật BOM thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật BOM: ' . $e->getMessage()], 500);
    }
}

function deleteBOM() {
    global $db;
    
    try {
        $id = $_POST['id'];
        
        $stmt = $db->prepare("DELETE FROM bom_thiet_bi WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_bom', "Xóa BOM ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Xóa vật tư khỏi BOM thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa BOM: ' . $e->getMessage()], 500);
    }
}

function importBOM() {
    global $db;
    
    try {
        $equipment_id = $_POST['equipment_id'];
        $replace_existing = isset($_POST['replace_existing']);
        
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng chọn file Excel'], 400);
        }
        
        // Process Excel file
        require_once '../../vendor/autoload.php'; // Assuming PhpSpreadsheet is installed
        
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Delete existing BOM if replace option is selected
        if ($replace_existing) {
            $delete_stmt = $db->prepare("DELETE FROM bom_thiet_bi WHERE id_thiet_bi = ?");
            $delete_stmt->execute([$equipment_id]);
        }
        
        $imported_count = 0;
        $errors = [];
        
        // Start from row 2 (assuming row 1 has headers)
        $highestRow = $worksheet->getHighestRow();
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $ma_item = $worksheet->getCell("A{$row}")->getValue();
            $so_luong = $worksheet->getCell("B{$row}")->getValue();
            $dong_may_name = $worksheet->getCell("C{$row}")->getValue();
            $ghi_chu = $worksheet->getCell("D{$row}")->getValue();
            
            if (empty($ma_item) || empty($so_luong)) {
                continue; // Skip empty rows
            }
            
            // Find vat_tu by ma_item
            $vat_tu_stmt = $db->prepare("SELECT id FROM vat_tu WHERE ma_item = ?");
            $vat_tu_stmt->execute([$ma_item]);
            $vat_tu = $vat_tu_stmt->fetch();
            
            if (!$vat_tu) {
                $errors[] = "Dòng $row: Không tìm thấy vật tư có mã $ma_item";
                continue;
            }
            
            // Find dong_may if specified
            $dong_may_id = null;
            if (!empty($dong_may_name)) {
                $dm_stmt = $db->prepare("SELECT id FROM dong_may WHERE ten_dong_may LIKE ?");
                $dm_stmt->execute(["%$dong_may_name%"]);
                $dong_may = $dm_stmt->fetch();
                $dong_may_id = $dong_may ? $dong_may['id'] : null;
            }
            
            // Insert BOM item
            try {
                $insert_stmt = $db->prepare("INSERT INTO bom_thiet_bi (id_thiet_bi, id_dong_may, ten_dong_may, id_vat_tu, so_luong, ghi_chu) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->execute([$equipment_id, $dong_may_id, $dong_may_name, $vat_tu['id'], $so_luong, $ghi_chu]);
                $imported_count++;
            } catch (Exception $e) {
                $errors[] = "Dòng $row: " . $e->getMessage();
            }
        }
        
        logActivity($_SESSION['user_id'], 'import_bom', "Import BOM cho thiết bị ID: $equipment_id, $imported_count items");
        
        $response = [
            'success' => true,
            'message' => "Import thành công $imported_count dòng",
            'data' => ['imported_count' => $imported_count]
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        jsonResponse($response);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi import: ' . $e->getMessage()], 500);
    }
}

function exportBOM() {
    global $db;
    
    // Build query with filters (similar to listBOM)
    $search = $_POST['search'] ?? '';
    $equipment_id = $_POST['equipment_id'] ?? '';
    $dong_may_id = $_POST['dong_may_id'] ?? '';
    $chung_loai = $_POST['chung_loai'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(vt.ma_item LIKE ? OR vt.ten_vat_tu LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "bom.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($dong_may_id)) {
        $where_conditions[] = "bom.id_dong_may = ?";
        $params[] = $dong_may_id;
    }
    
    if (!empty($chung_loai)) {
        $where_conditions[] = "vt.chung_loai = ?";
        $params[] = $chung_loai;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT tb.id_thiet_bi as 'ID Thiết bị', tb.ten_thiet_bi as 'Tên thiết bị',
                   dm.ten_dong_may as 'Dòng máy', vt.ma_item as 'Mã vật tư', 
                   vt.ten_vat_tu as 'Tên vật tư', bom.so_luong as 'Số lượng',
                   vt.dvt as 'ĐVT', vt.gia as 'Đơn giá',
                   (bom.so_luong * vt.gia) as 'Thành tiền',
                   vt.chung_loai as 'Chủng loại', bom.ghi_chu as 'Ghi chú'
            FROM bom_thiet_bi bom
            JOIN thiet_bi tb ON bom.id_thiet_bi = tb.id
            JOIN vat_tu vt ON bom.id_vat_tu = vt.id
            LEFT JOIN dong_may dm ON bom.id_dong_may = dm.id
            $where_clause
            ORDER BY tb.id_thiet_bi, dm.ten_dong_may, vt.ma_item";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Create CSV
    $filename = 'bom_thiet_bi_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

function downloadTemplate() {
    $filename = 'bom_import_template.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['Mã vật tư', 'Số lượng', 'Tên dòng máy', 'Ghi chú']);
    
    // Add sample data
    fputcsv($output, ['VT001', '2', 'Dòng máy trộn bột chính', 'Gối đỡ chính và phụ']);
    fputcsv($output, ['VT002', '1', 'Dòng máy trộn bột chính', 'Dây curoa truyền động']);
    fputcsv($output, ['VT012', '0.5', '', 'Mỡ bôi trơn định kỳ']);
    
    fclose($output);
    exit();
}
?>