<?php
// modules/equipment/api.php - API cho Equipment Management
require_once '../../auth/check_auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        listEquipment();
        break;
    case 'detail':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getEquipmentDetail();
        break;
    case 'create':
        checkApiPermission(['admin', 'to_truong']);
        createEquipment();
        break;
    case 'update':
        checkApiPermission(['admin', 'to_truong']);
        updateEquipment();
        break;
    case 'delete':
        checkApiPermission(['admin', 'to_truong']);
        deleteEquipment();
        break;
    case 'get_xuong':
        getXuongList();
        break;
    case 'get_lines':
        getLinesList();
        break;
    case 'get_khu_vuc':
        getKhuVucList();
        break;
    case 'get_dong_may':
        getDongMayList();
        break;
    case 'export':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        exportEquipment();
        break;
    case 'search_by_id':
        searchEquipmentById();
        break;    
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listEquipment() {
    global $db;
    
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';
    $xuong = $_POST['xuong'] ?? '';
    $line = $_POST['line'] ?? '';
    $tinh_trang = $_POST['tinh_trang'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(tb.id_thiet_bi LIKE ? OR tb.ten_thiet_bi LIKE ? OR tb.nganh LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($xuong)) {
        $where_conditions[] = "tb.id_xuong = ?";
        $params[] = $xuong;
    }
    
    if (!empty($line)) {
        $where_conditions[] = "tb.id_line = ?";
        $params[] = $line;
    }
    
    if (!empty($tinh_trang)) {
        $where_conditions[] = "tb.tinh_trang = ?";
        $params[] = $tinh_trang;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM thiet_bi tb $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated data
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc, 
                   dm.ten_dong_may, u.full_name as chu_quan_name
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
            LEFT JOIN users u ON tb.nguoi_chu_quan = u.id
            $where_clause
            ORDER BY tb.created_at DESC
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $equipment = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $equipment,
        'pagination' => $pagination
    ]);
}

function getEquipmentDetail() {
    global $db;
    
    $id = $_GET['id'];
    
    $sql = "SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc, 
                   dm.ten_dong_may, u.full_name as chu_quan_name
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
            LEFT JOIN users u ON tb.nguoi_chu_quan = u.id
            WHERE tb.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();
    
    if ($equipment) {
        jsonResponse(['success' => true, 'data' => $equipment]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
    }
}

function getXuongList() {
    global $db;
    
    $stmt = $db->query("SELECT id, ten_xuong FROM xuong ORDER BY ten_xuong");
    $xuong = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $xuong]);
}

function getLinesList() {
    global $db;
    
    $xuong_id = $_GET['xuong_id'];
    
    $stmt = $db->prepare("SELECT id, ten_line FROM production_line WHERE id_xuong = ? ORDER BY ten_line");
    $stmt->execute([$xuong_id]);
    $lines = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $lines]);
}

function getKhuVucList() {
    global $db;
    
    $line_id = $_GET['line_id'];
    
    $stmt = $db->prepare("SELECT id, ten_khu_vuc FROM khu_vuc WHERE id_line = ? ORDER BY ten_khu_vuc");
    $stmt->execute([$line_id]);
    $khu_vuc = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $khu_vuc]);
}

function getDongMayList() {
    global $db;
    
    $khu_vuc_id = $_GET['khu_vuc_id'];
    
    $stmt = $db->prepare("SELECT id, ten_dong_may FROM dong_may WHERE id_khu_vuc = ? ORDER BY ten_dong_may");
    $stmt->execute([$khu_vuc_id]);
    $dong_may = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $dong_may]);
}

function createEquipment() {
    global $db;
    
    try {
        $data = [
            'id_thiet_bi' => cleanInput($_POST['id_thiet_bi']),
            'id_xuong' => $_POST['id_xuong'],
            'id_line' => $_POST['id_line'],
            'id_khu_vuc' => $_POST['id_khu_vuc'],
            'vi_tri' => cleanInput($_POST['vi_tri']),
            'id_dong_may' => $_POST['id_dong_may'] ?: null,
            'nganh' => cleanInput($_POST['nganh']),
            'ten_thiet_bi' => cleanInput($_POST['ten_thiet_bi']),
            'nam_san_xuat' => $_POST['nam_san_xuat'] ?: null,
            'cong_suat' => cleanInput($_POST['cong_suat']),
            'thong_so_ky_thuat' => cleanInput($_POST['thong_so_ky_thuat']),
            'tinh_trang' => $_POST['tinh_trang'],
            'nguoi_chu_quan' => $_POST['nguoi_chu_quan'] ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Validate required fields
        if (empty($data['id_thiet_bi']) || empty($data['ten_thiet_bi']) || empty($data['id_xuong']) || empty($data['id_line'])) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
        }
        
        // Check duplicate ID
        $check_stmt = $db->prepare("SELECT id FROM thiet_bi WHERE id_thiet_bi = ?");
        $check_stmt->execute([$data['id_thiet_bi']]);
        if ($check_stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'ID thiết bị đã tồn tại'], 400);
        }
        
        // Handle file upload
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['hinh_anh'], 'uploads/equipment/', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $data['hinh_anh'] = $upload_result['file_path'];
            }
        }
        
        // Generate QR code data
        $qr_data = json_encode([
            'id' => $data['id_thiet_bi'],
            'name' => $data['ten_thiet_bi'],
            'type' => 'equipment'
        ]);
        $data['qr_code'] = $qr_data;
        
        // Insert equipment
        $sql = "INSERT INTO thiet_bi (id_thiet_bi, id_xuong, id_line, id_khu_vuc, vi_tri, id_dong_may, 
                nganh, ten_thiet_bi, nam_san_xuat, cong_suat, thong_so_ky_thuat, hinh_anh, 
                tinh_trang, nguoi_chu_quan, qr_code, ghi_chu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['id_xuong'], $data['id_line'], $data['id_khu_vuc'],
            $data['vi_tri'], $data['id_dong_may'], $data['nganh'], $data['ten_thiet_bi'],
            $data['nam_san_xuat'], $data['cong_suat'], $data['thong_so_ky_thuat'], 
            $data['hinh_anh'], $data['tinh_trang'], $data['nguoi_chu_quan'], 
            $data['qr_code'], $data['ghi_chu']
        ]);
        
        logActivity($_SESSION['user_id'], 'create_equipment', "Tạo thiết bị: {$data['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Tạo thiết bị thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi tạo thiết bị: ' . $e->getMessage()], 500);
    }
}

function updateEquipment() {
    global $db;
    
    try {
        $id = $_POST['id'];
        $data = [
            'id_thiet_bi' => cleanInput($_POST['id_thiet_bi']),
            'id_xuong' => $_POST['id_xuong'],
            'id_line' => $_POST['id_line'],
            'id_khu_vuc' => $_POST['id_khu_vuc'],
            'vi_tri' => cleanInput($_POST['vi_tri']),
            'id_dong_may' => $_POST['id_dong_may'] ?: null,
            'nganh' => cleanInput($_POST['nganh']),
            'ten_thiet_bi' => cleanInput($_POST['ten_thiet_bi']),
            'nam_san_xuat' => $_POST['nam_san_xuat'] ?: null,
            'cong_suat' => cleanInput($_POST['cong_suat']),
            'thong_so_ky_thuat' => cleanInput($_POST['thong_so_ky_thuat']),
            'tinh_trang' => $_POST['tinh_trang'],
            'nguoi_chu_quan' => $_POST['nguoi_chu_quan'] ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Check duplicate ID (exclude current record)
        $check_stmt = $db->prepare("SELECT id FROM thiet_bi WHERE id_thiet_bi = ? AND id != ?");
        $check_stmt->execute([$data['id_thiet_bi'], $id]);
        if ($check_stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'ID thiết bị đã tồn tại'], 400);
        }
        
        // Handle file upload
        $image_update = '';
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['hinh_anh'], 'uploads/equipment/', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $data['hinh_anh'] = $upload_result['file_path'];
                $image_update = ', hinh_anh = ?';
            }
        }
        
        // Update QR code data
        $qr_data = json_encode([
            'id' => $data['id_thiet_bi'],
            'name' => $data['ten_thiet_bi'],
            'type' => 'equipment'
        ]);
        
        // Update equipment
        $sql = "UPDATE thiet_bi SET 
                id_thiet_bi = ?, id_xuong = ?, id_line = ?, id_khu_vuc = ?, vi_tri = ?, 
                id_dong_may = ?, nganh = ?, ten_thiet_bi = ?, nam_san_xuat = ?, cong_suat = ?, 
                thong_so_ky_thuat = ?, tinh_trang = ?, nguoi_chu_quan = ?, qr_code = ?, ghi_chu = ?
                $image_update 
                WHERE id = ?";
        
        $params = [
            $data['id_thiet_bi'], $data['id_xuong'], $data['id_line'], $data['id_khu_vuc'],
            $data['vi_tri'], $data['id_dong_may'], $data['nganh'], $data['ten_thiet_bi'],
            $data['nam_san_xuat'], $data['cong_suat'], $data['thong_so_ky_thuat'], 
            $data['tinh_trang'], $data['nguoi_chu_quan'], $qr_data, $data['ghi_chu']
        ];
        
        if (!empty($image_update)) {
            $params[] = $data['hinh_anh'];
        }
        $params[] = $id;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logActivity($_SESSION['user_id'], 'update_equipment', "Cập nhật thiết bị: {$data['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật thiết bị thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật thiết bị: ' . $e->getMessage()], 500);
    }
}

function deleteEquipment() {
    global $db;
    
    try {
        $id = $_POST['id'];
        
        // Get equipment info before delete
        $stmt = $db->prepare("SELECT id_thiet_bi, hinh_anh FROM thiet_bi WHERE id = ?");
        $stmt->execute([$id]);
        $equipment = $stmt->fetch();
        
        if (!$equipment) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
        }
        
        // Check if equipment has related data
        $check_maintenance = $db->prepare("SELECT COUNT(*) as count FROM ke_hoach_bao_tri WHERE id_thiet_bi = ?");
        $check_maintenance->execute([$id]);
        if ($check_maintenance->fetch()['count'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Không thể xóa thiết bị có kế hoạch bảo trì'], 400);
        }
        
        // Delete equipment
        $stmt = $db->prepare("DELETE FROM thiet_bi WHERE id = ?");
        $stmt->execute([$id]);
        
        // Delete image file
        if ($equipment['hinh_anh'] && file_exists($equipment['hinh_anh'])) {
            unlink($equipment['hinh_anh']);
        }
        
        logActivity($_SESSION['user_id'], 'delete_equipment', "Xóa thiết bị: {$equipment['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Xóa thiết bị thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa thiết bị: ' . $e->getMessage()], 500);
    }
}

function exportEquipment() {
    global $db;
    
    // Build query with filters
    $search = $_POST['search'] ?? '';
    $xuong = $_POST['xuong'] ?? '';
    $line = $_POST['line'] ?? '';
    $tinh_trang = $_POST['tinh_trang'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(tb.id_thiet_bi LIKE ? OR tb.ten_thiet_bi LIKE ? OR tb.nganh LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($xuong)) {
        $where_conditions[] = "tb.id_xuong = ?";
        $params[] = $xuong;
    }
    
    if (!empty($line)) {
        $where_conditions[] = "tb.id_line = ?";
        $params[] = $line;
    }
    
    if (!empty($tinh_trang)) {
        $where_conditions[] = "tb.tinh_trang = ?";
        $params[] = $tinh_trang;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT tb.id_thiet_bi as 'ID Thiết bị', tb.ten_thiet_bi as 'Tên thiết bị',
                   x.ten_xuong as 'Xưởng', pl.ten_line as 'Line', kv.ten_khu_vuc as 'Khu vực',
                   tb.vi_tri as 'Vị trí', dm.ten_dong_may as 'Dòng máy', tb.nganh as 'Ngành',
                   tb.nam_san_xuat as 'Năm SX', tb.cong_suat as 'Công suất',
                   tb.tinh_trang as 'Tình trạng', u.full_name as 'Người chủ quản',
                   tb.created_at as 'Ngày tạo'
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
            LEFT JOIN users u ON tb.nguoi_chu_quan = u.id
            $where_clause
            ORDER BY tb.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Create CSV
    $filename = 'danh_sach_thiet_bi_' . date('Y-m-d_H-i-s') . '.csv';
    
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
function searchEquipmentById() {
    global $db;
    
    $equipment_id = $_GET['equipment_id'];
    
    $sql = "SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc, 
                   dm.ten_dong_may, u.full_name as chu_quan_name
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
            LEFT JOIN users u ON tb.nguoi_chu_quan = u.id
            WHERE tb.id_thiet_bi = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch();
    
    if ($equipment) {
        jsonResponse(['success' => true, 'data' => $equipment]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
    }
}
?>
