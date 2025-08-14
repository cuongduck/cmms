<?php
// modules/equipment/api.php - Performance Optimized Version
require_once '../../auth/check_auth.php';

// Enable output compression
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set response headers for better caching
header('Cache-Control: public, max-age=300'); // 5 minutes cache
header('Vary: Accept-Encoding');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        listEquipmentOptimized();
        break;
    case 'list_simple':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getEquipmentListSimple();
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
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getXuongListCached();
        break;
    case 'get_lines':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getLinesListCached();
        break;
    case 'get_khu_vuc':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getKhuVucListCached();
        break;
    case 'get_dong_may':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getDongMayList();
        break;
    case 'get_cum_thiet_bi':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getCumThietBiList();
        break;
    case 'export':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        exportEquipment();
        break;
    case 'search_by_id':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        searchEquipmentById();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

// ============================================================================
// OPTIMIZED FUNCTIONS
// ============================================================================

function listEquipmentOptimized() {
    global $db;
    
    try {
        $page = (int)($_POST['page'] ?? 1);
        $search = trim($_POST['search'] ?? '');
        $xuong = (int)($_POST['xuong'] ?? 0) ?: null;
        $line = (int)($_POST['line'] ?? 0) ?: null;
        $khu_vuc = (int)($_POST['khu_vuc'] ?? 0) ?: null;
        $tinh_trang = trim($_POST['tinh_trang'] ?? '');
        $limit = 15; // Increased from 10 for better UX
        
        // Validate inputs
        $page = max(1, $page);
        $limit = min(50, max(5, $limit)); // Max 50 items per page
        
        // Use optimized view instead of complex JOIN
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(id_thiet_bi LIKE ? OR ten_thiet_bi LIKE ? OR nganh LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($xuong) {
            $where_conditions[] = "id_xuong = ?";
            $params[] = $xuong;
        }
        
        if ($line) {
            $where_conditions[] = "id_line = ?";
            $params[] = $line;
        }
        
        if ($khu_vuc) {
            $where_conditions[] = "id_khu_vuc = ?";
            $params[] = $khu_vuc;
        }
        
        if (!empty($tinh_trang)) {
            $where_conditions[] = "tinh_trang = ?";
            $params[] = $tinh_trang;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Count total records using optimized query
        $count_sql = "SELECT COUNT(*) as total FROM v_equipment_optimized $where_clause";
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = (int)$count_stmt->fetch()['total'];
        
        // Get paginated data using optimized view
        $pagination = getPagination($page, $total_records, $limit);
        
        $sql = "SELECT 
                    id, id_thiet_bi, ten_thiet_bi, nganh, tinh_trang, nam_san_xuat,
                    ten_xuong, ten_line, ten_khu_vuc, ten_dong_may, ten_cum,
                    chu_quan_name, vi_tri_day_du
                FROM v_equipment_optimized 
                $where_clause
                ORDER BY created_at DESC
                LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'success' => true,
            'data' => $equipment,
            'pagination' => $pagination,
            'debug' => [
                'query_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'total_records' => $total_records,
                'page' => $page
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Equipment list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách thiết bị'], 500);
    }
}

function getXuongListCached() {
    global $db;
    
    try {
        // Simple cache using static variable
        static $xuong_cache = null;
        
        if ($xuong_cache === null) {
            $stmt = $db->query("SELECT id, ma_xuong, ten_xuong FROM xuong ORDER BY ten_xuong");
            $xuong_cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        jsonResponse(['success' => true, 'data' => $xuong_cache]);
        
    } catch (Exception $e) {
        error_log("Xuong list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách xưởng'], 500);
    }
}

function getKhuVucListCached() {
    global $db;
    
    try {
        $xuong_id = (int)($_GET['xuong_id'] ?? 0);
        
        if (!$xuong_id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu xuong_id'], 400);
            return;
        }
        
        // Use prepared statement with index
        $stmt = $db->prepare("SELECT id, ma_khu_vuc, ten_khu_vuc, loai_khu_vuc 
                             FROM khu_vuc 
                             WHERE id_xuong = ? 
                             ORDER BY loai_khu_vuc, ten_khu_vuc");
        $stmt->execute([$xuong_id]);
        $khu_vuc = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $khu_vuc]);
        
    } catch (Exception $e) {
        error_log("Khu vuc list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách khu vực'], 500);
    }
}

function getLinesListCached() {
    global $db;
    
    try {
        $xuong_id = (int)($_GET['xuong_id'] ?? 0);
        
        if (!$xuong_id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu xuong_id'], 400);
            return;
        }
        
        // Use indexed query
        $stmt = $db->prepare("SELECT id, ma_line, ten_line 
                             FROM production_line 
                             WHERE id_xuong = ? 
                             ORDER BY ten_line");
        $stmt->execute([$xuong_id]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $lines]);
        
    } catch (Exception $e) {
        error_log("Lines list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách line'], 500);
    }
}

function getDongMayList() {
    global $db;
    
    try {
        $line_id = (int)($_GET['line_id'] ?? 0);
        
        if (!$line_id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu line_id'], 400);
            return;
        }
        
        $stmt = $db->prepare("SELECT id, ma_dong_may, ten_dong_may 
                             FROM dong_may 
                             WHERE id_line = ? 
                             ORDER BY ten_dong_may");
        $stmt->execute([$line_id]);
        $dong_may = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $dong_may]);
        
    } catch (Exception $e) {
        error_log("Dong may list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách dòng máy'], 500);
    }
}

function getCumThietBiList() {
    global $db;
    
    try {
        $dong_may_id = (int)($_GET['dong_may_id'] ?? 0);
        
        if (!$dong_may_id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu dong_may_id'], 400);
            return;
        }
        
        $stmt = $db->prepare("SELECT id, ma_cum, ten_cum 
                             FROM cum_thiet_bi 
                             WHERE id_dong_may = ? 
                             ORDER BY ten_cum");
        $stmt->execute([$dong_may_id]);
        $cum_thiet_bi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $cum_thiet_bi]);
        
    } catch (Exception $e) {
        error_log("Cum thiet bi list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách cụm thiết bị'], 500);
    }
}

function getEquipmentListSimple() {
    global $db;
    
    try {
        // Use indexed columns only
        $sql = "SELECT id, id_thiet_bi, ten_thiet_bi 
                FROM thiet_bi 
                WHERE tinh_trang IN ('hoat_dong', 'bao_tri')
                ORDER BY id_thiet_bi 
                LIMIT 1000"; // Reasonable limit
        
        $stmt = $db->query($sql);
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $equipment]);
        
    } catch (Exception $e) {
        error_log("Equipment list_simple error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách thiết bị'], 500);
    }
}

function getEquipmentDetail() {
    global $db;
    
    try {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu ID thiết bị'], 400);
            return;
        }
        
        // Use optimized view for detail
        $sql = "SELECT * FROM v_equipment_optimized WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($equipment) {
            jsonResponse(['success' => true, 'data' => $equipment]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
        }
        
    } catch (Exception $e) {
        error_log("Equipment detail error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải chi tiết thiết bị'], 500);
    }
}

function searchEquipmentById() {
    global $db;
    
    try {
        $equipment_id = trim($_GET['equipment_id'] ?? '');
        
        if (empty($equipment_id)) {
            jsonResponse(['success' => false, 'message' => 'Thiếu ID thiết bị'], 400);
            return;
        }
        
        // Use index for fast lookup
        $sql = "SELECT * FROM v_equipment_optimized WHERE id_thiet_bi = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$equipment_id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($equipment) {
            jsonResponse(['success' => true, 'data' => $equipment]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
        }
        
    } catch (Exception $e) {
        error_log("Search equipment error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tìm kiếm thiết bị'], 500);
    }
}

// ============================================================================
// ORIGINAL FUNCTIONS (CREATE, UPDATE, DELETE, EXPORT) - Keep as is
// ============================================================================

function createEquipment() {
    global $db;
    
    try {
        // Begin transaction for better performance
        $db->beginTransaction();
        
        $data = [
            'id_thiet_bi' => cleanInput($_POST['id_thiet_bi']),
            'id_xuong' => (int)$_POST['id_xuong'],
            'id_line' => (int)($_POST['id_line'] ?: 0) ?: null,
            'id_khu_vuc' => (int)$_POST['id_khu_vuc'],
            'id_dong_may' => (int)($_POST['id_dong_may'] ?: 0) ?: null,
            'id_cum_thiet_bi' => (int)($_POST['id_cum_thiet_bi'] ?: 0) ?: null,
            'nganh' => cleanInput($_POST['nganh']),
            'ten_thiet_bi' => cleanInput($_POST['ten_thiet_bi']),
            'nam_san_xuat' => (int)($_POST['nam_san_xuat'] ?: 0) ?: null,
            'cong_suat' => cleanInput($_POST['cong_suat']),
            'thong_so_ky_thuat' => cleanInput($_POST['thong_so_ky_thuat']),
            'tinh_trang' => $_POST['tinh_trang'],
            'nguoi_chu_quan' => (int)($_POST['nguoi_chu_quan'] ?: 0) ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Validate required fields
        if (empty($data['id_thiet_bi']) || empty($data['ten_thiet_bi']) || !$data['id_xuong'] || !$data['id_khu_vuc']) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
            return;
        }
        
        // Check duplicate ID using index
        $check_stmt = $db->prepare("SELECT 1 FROM thiet_bi WHERE id_thiet_bi = ? LIMIT 1");
        $check_stmt->execute([$data['id_thiet_bi']]);
        if ($check_stmt->fetch()) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'ID thiết bị đã tồn tại'], 400);
            return;
        }
        
        // Handle file upload
        $hinh_anh = null;
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['hinh_anh'], 'uploads/equipment/', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $hinh_anh = $upload_result['file_path'];
            }
        }
        
        // Generate QR code data
        $qr_data = json_encode([
            'id' => $data['id_thiet_bi'],
            'name' => $data['ten_thiet_bi'],
            'type' => 'equipment'
        ], JSON_UNESCAPED_UNICODE);
        
        // Insert equipment
        $sql = "INSERT INTO thiet_bi (id_thiet_bi, id_xuong, id_line, id_khu_vuc, id_dong_may, id_cum_thiet_bi,
                nganh, ten_thiet_bi, nam_san_xuat, cong_suat, thong_so_ky_thuat, hinh_anh, 
                tinh_trang, nguoi_chu_quan, qr_code, ghi_chu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['id_xuong'], $data['id_line'], $data['id_khu_vuc'],
            $data['id_dong_may'], $data['id_cum_thiet_bi'], $data['nganh'], $data['ten_thiet_bi'],
            $data['nam_san_xuat'], $data['cong_suat'], $data['thong_so_ky_thuat'], 
            $hinh_anh, $data['tinh_trang'], $data['nguoi_chu_quan'], 
            $qr_data, $data['ghi_chu']
        ]);
        
        $db->commit();
        
        logActivity($_SESSION['user_id'], 'create_equipment', "Tạo thiết bị: {$data['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Tạo thiết bị thành công']);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Create equipment error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tạo thiết bị'], 500);
    }
}

function updateEquipment() {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $id = (int)$_POST['id'];
        $data = [
            'id_thiet_bi' => cleanInput($_POST['id_thiet_bi']),
            'id_xuong' => (int)$_POST['id_xuong'],
            'id_line' => (int)($_POST['id_line'] ?: 0) ?: null,
            'id_khu_vuc' => (int)$_POST['id_khu_vuc'],
            'id_dong_may' => (int)($_POST['id_dong_may'] ?: 0) ?: null,
            'id_cum_thiet_bi' => (int)($_POST['id_cum_thiet_bi'] ?: 0) ?: null,
            'nganh' => cleanInput($_POST['nganh']),
            'ten_thiet_bi' => cleanInput($_POST['ten_thiet_bi']),
            'nam_san_xuat' => (int)($_POST['nam_san_xuat'] ?: 0) ?: null,
            'cong_suat' => cleanInput($_POST['cong_suat']),
            'thong_so_ky_thuat' => cleanInput($_POST['thong_so_ky_thuat']),
            'tinh_trang' => $_POST['tinh_trang'],
            'nguoi_chu_quan' => (int)($_POST['nguoi_chu_quan'] ?: 0) ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Check duplicate ID (exclude current record)
        $check_stmt = $db->prepare("SELECT 1 FROM thiet_bi WHERE id_thiet_bi = ? AND id != ? LIMIT 1");
        $check_stmt->execute([$data['id_thiet_bi'], $id]);
        if ($check_stmt->fetch()) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'ID thiết bị đã tồn tại'], 400);
            return;
        }
        
        // Handle file upload
        $image_update = '';
        $hinh_anh = null;
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['hinh_anh'], 'uploads/equipment/', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $hinh_anh = $upload_result['file_path'];
                $image_update = ', hinh_anh = ?';
            }
        }
        
        // Update QR code data
        $qr_data = json_encode([
            'id' => $data['id_thiet_bi'],
            'name' => $data['ten_thiet_bi'],
            'type' => 'equipment'
        ], JSON_UNESCAPED_UNICODE);
        
        // Update equipment
        $sql = "UPDATE thiet_bi SET 
                id_thiet_bi = ?, id_xuong = ?, id_line = ?, id_khu_vuc = ?, id_dong_may = ?,
                id_cum_thiet_bi = ?, nganh = ?, ten_thiet_bi = ?, nam_san_xuat = ?, cong_suat = ?, 
                thong_so_ky_thuat = ?, tinh_trang = ?, nguoi_chu_quan = ?, qr_code = ?, ghi_chu = ?
                $image_update 
                WHERE id = ?";
        
        $params = [
            $data['id_thiet_bi'], $data['id_xuong'], $data['id_line'], $data['id_khu_vuc'],
            $data['id_dong_may'], $data['id_cum_thiet_bi'], $data['nganh'], $data['ten_thiet_bi'],
            $data['nam_san_xuat'], $data['cong_suat'], $data['thong_so_ky_thuat'], 
            $data['tinh_trang'], $data['nguoi_chu_quan'], $qr_data, $data['ghi_chu']
        ];
        
        if (!empty($image_update)) {
            $params[] = $hinh_anh;
        }
        $params[] = $id;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $db->commit();
        
        logActivity($_SESSION['user_id'], 'update_equipment', "Cập nhật thiết bị: {$data['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật thiết bị thành công']);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Update equipment error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật thiết bị'], 500);
    }
}

function deleteEquipment() {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $id = (int)$_POST['id'];
        
        // Get equipment info before delete
        $stmt = $db->prepare("SELECT id_thiet_bi, hinh_anh FROM thiet_bi WHERE id = ?");
        $stmt->execute([$id]);
        $equipment = $stmt->fetch();
        
        if (!$equipment) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy thiết bị'], 404);
            return;
        }
        
        // Check if equipment has related data
        $check_maintenance = $db->prepare("SELECT 1 FROM ke_hoach_bao_tri WHERE id_thiet_bi = ? LIMIT 1");
        $check_maintenance->execute([$id]);
        if ($check_maintenance->fetch()) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Không thể xóa thiết bị có kế hoạch bảo trì'], 400);
            return;
        }
        
        // Delete equipment
        $stmt = $db->prepare("DELETE FROM thiet_bi WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        // Delete image file
        if ($equipment['hinh_anh'] && file_exists($equipment['hinh_anh'])) {
            unlink($equipment['hinh_anh']);
        }
        
        logActivity($_SESSION['user_id'], 'delete_equipment', "Xóa thiết bị: {$equipment['id_thiet_bi']}");
        
        jsonResponse(['success' => true, 'message' => 'Xóa thiết bị thành công']);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Delete equipment error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa thiết bị'], 500);
    }
}

function exportEquipment() {
    global $db;
    
    try {
        // Build query with filters using optimized view
        $search = trim($_POST['search'] ?? '');
        $xuong = (int)($_POST['xuong'] ?? 0) ?: null;
        $line = (int)($_POST['line'] ?? 0) ?: null;
        $khu_vuc = (int)($_POST['khu_vuc'] ?? 0) ?: null;
        $tinh_trang = trim($_POST['tinh_trang'] ?? '');
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(id_thiet_bi LIKE ? OR ten_thiet_bi LIKE ? OR nganh LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($xuong) {
            $where_conditions[] = "id_xuong = ?";
            $params[] = $xuong;
        }
        
        if ($line) {
            $where_conditions[] = "id_line = ?";
            $params[] = $line;
        }
        
        if ($khu_vuc) {
            $where_conditions[] = "id_khu_vuc = ?";
            $params[] = $khu_vuc;
        }
        
        if (!empty($tinh_trang)) {
            $where_conditions[] = "tinh_trang = ?";
            $params[] = $tinh_trang;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT 
                    id_thiet_bi as 'ID Thiết bị', 
                    ten_thiet_bi as 'Tên thiết bị',
                    ten_xuong as 'Xưởng', 
                    ten_line as 'Line', 
                    ten_khu_vuc as 'Khu vực',
                    ten_dong_may as 'Dòng máy', 
                    ten_cum as 'Cụm thiết bị', 
                    nganh as 'Ngành', 
                    nam_san_xuat as 'Năm SX', 
                    cong_suat as 'Công suất',
                    tinh_trang as 'Tình trạng', 
                    chu_quan_name as 'Người chủ quản',
                    created_at as 'Ngày tạo'
                FROM v_equipment_optimized
                $where_clause
                ORDER BY created_at DESC
                LIMIT 5000"; // Reasonable limit for export
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create CSV
        $filename = 'danh_sach_thiet_bi_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
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
        
    } catch (Exception $e) {
        error_log("Export equipment error: " . $e->getMessage());
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Lỗi xuất dữ liệu'], 500);
    }
}

// Flush output buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>