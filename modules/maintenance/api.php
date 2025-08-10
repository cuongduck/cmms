<?php
// modules/maintenance/api.php - Maintenance API
require_once '../../auth/check_auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        listMaintenance();
        break;
    case 'detail':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getMaintenanceDetail();
        break;
    case 'statistics':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getMaintenanceStatistics();
        break;
    case 'create':
        checkApiPermission(['admin', 'to_truong']);
        createMaintenance();
        break;
    case 'update':
        checkApiPermission(['admin', 'to_truong']);
        updateMaintenance();
        break;
    case 'delete':
        checkApiPermission(['admin', 'to_truong']);
        deleteMaintenance();
        break;
    case 'execute':
        checkApiPermission(['admin', 'to_truong', 'user']);
        executeMaintenanceWork();
        break;
    case 'calendar_data':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getCalendarData();
        break;
    case 'export':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        exportMaintenance();
        break;
    case 'equipment_history':
        getEquipmentHistory();
        break;
    case 'history':
        getMaintenanceHistory();
        break;
    case 'history_detail':
        getMaintenanceHistoryDetail();
        break;
    case 'export_history':
        exportMaintenanceHistory();
        break;    
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listMaintenance() {
    global $db;
    
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_bao_tri = $_POST['loai_bao_tri'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? '';
    $uu_tien = $_POST['uu_tien'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        if ($search === 'upcoming_7_days') {
            $where_conditions[] = "kh.ngay_bao_tri_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            $where_conditions[] = "kh.trang_thai = 'chua_thuc_hien'";
        } elseif ($search === 'this_week') {
            $where_conditions[] = "WEEK(kh.ngay_bao_tri_tiep_theo) = WEEK(CURDATE()) AND YEAR(kh.ngay_bao_tri_tiep_theo) = YEAR(CURDATE())";
        } else {
            $where_conditions[] = "(kh.ten_ke_hoach LIKE ? OR tb.id_thiet_bi LIKE ? OR tb.ten_thiet_bi LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "kh.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_bao_tri)) {
        $where_conditions[] = "kh.loai_bao_tri = ?";
        $params[] = $loai_bao_tri;
    }
    
    if (!empty($trang_thai)) {
        $where_conditions[] = "kh.trang_thai = ?";
        $params[] = $trang_thai;
    }
    
    if (!empty($uu_tien)) {
        $where_conditions[] = "kh.uu_tien = ?";
        $params[] = $uu_tien;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Update overdue status
    updateOverdueStatus();
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM ke_hoach_bao_tri kh
                  JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
                  LEFT JOIN users u ON kh.nguoi_thuc_hien = u.id
                  $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated data
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT kh.*, tb.id_thiet_bi, tb.ten_thiet_bi, 
                   x.ten_xuong, pl.ten_line,
                   u.full_name as nguoi_thuc_hien_name,
                   uc.full_name as created_by_name
            FROM ke_hoach_bao_tri kh
            JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            JOIN xuong x ON tb.id_xuong = x.id
            JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN users u ON kh.nguoi_thuc_hien = u.id
            LEFT JOIN users uc ON kh.created_by = uc.id
            $where_clause
            ORDER BY 
                CASE 
                    WHEN kh.trang_thai = 'qua_han' THEN 1
                    WHEN kh.trang_thai = 'dang_thuc_hien' THEN 2
                    WHEN kh.ngay_bao_tri_tiep_theo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 3
                    ELSE 4
                END,
                kh.uu_tien = 'khan_cap' DESC,
                kh.uu_tien = 'cao' DESC,
                kh.ngay_bao_tri_tiep_theo ASC
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $maintenance_plans = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $maintenance_plans,
        'pagination' => $pagination
    ]);
}

function getMaintenanceDetail() {
    global $db;
    
    $id = $_GET['id'];
    
    // Get maintenance plan details
    $sql = "SELECT kh.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   x.ten_xuong, pl.ten_line,
                   u.full_name as nguoi_thuc_hien_name,
                   uc.full_name as created_by_name
            FROM ke_hoach_bao_tri kh
            JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            JOIN xuong x ON tb.id_xuong = x.id
            JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN users u ON kh.nguoi_thuc_hien = u.id
            LEFT JOIN users uc ON kh.created_by = uc.id
            WHERE kh.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $maintenance = $stmt->fetch();
    
    if (!$maintenance) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy kế hoạch bảo trì'], 404);
    }
    
    // Get maintenance history
    $history_sql = "SELECT lsbt.*, u.full_name as nguoi_thuc_hien_name
                    FROM lich_su_bao_tri lsbt
                    LEFT JOIN users u ON lsbt.nguoi_thuc_hien = u.id
                    WHERE lsbt.id_ke_hoach = ?
                    ORDER BY lsbt.ngay_thuc_hien DESC
                    LIMIT 5";
    $history_stmt = $db->prepare($history_sql);
    $history_stmt->execute([$id]);
    $maintenance['maintenance_history'] = $history_stmt->fetchAll();
    
    // Get equipment BOM items
    $bom_sql = "SELECT bom.*, vt.ma_item, vt.ten_vat_tu, vt.dvt, vt.gia
                FROM bom_thiet_bi bom
                JOIN vat_tu vt ON bom.id_vat_tu = vt.id
                WHERE bom.id_thiet_bi = ?
                ORDER BY vt.ma_item";
    $bom_stmt = $db->prepare($bom_sql);
    $bom_stmt->execute([$maintenance['id_thiet_bi']]);
    $maintenance['bom_items'] = $bom_stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $maintenance]);
}

function getMaintenanceStatistics() {
    global $db;
    
    // Update overdue status first
    updateOverdueStatus();
    
    try {
        // Overdue maintenance
        $overdue_sql = "SELECT COUNT(*) as count FROM ke_hoach_bao_tri WHERE trang_thai = 'qua_han'";
        $overdue_stmt = $db->query($overdue_sql);
        $overdue = $overdue_stmt->fetch()['count'];
        
        // Upcoming maintenance (7 days)
        $upcoming_sql = "SELECT COUNT(*) as count FROM ke_hoach_bao_tri 
                        WHERE ngay_bao_tri_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                        AND trang_thai = 'chua_thuc_hien'";
        $upcoming_stmt = $db->query($upcoming_sql);
        $upcoming = $upcoming_stmt->fetch()['count'];
        
        // In progress maintenance
        $progress_sql = "SELECT COUNT(*) as count FROM ke_hoach_bao_tri WHERE trang_thai = 'dang_thuc_hien'";
        $progress_stmt = $db->query($progress_sql);
        $in_progress = $progress_stmt->fetch()['count'];
        
        // Completed this month
        $completed_sql = "SELECT COUNT(*) as count FROM lich_su_bao_tri 
                         WHERE MONTH(ngay_thuc_hien) = MONTH(CURDATE()) 
                         AND YEAR(ngay_thuc_hien) = YEAR(CURDATE())
                         AND trang_thai = 'hoan_thanh'";
        $completed_stmt = $db->query($completed_sql);
        $completed_this_month = $completed_stmt->fetch()['count'];
        
        jsonResponse([
            'success' => true,
            'data' => [
                'overdue' => $overdue,
                'upcoming' => $upcoming,
                'in_progress' => $in_progress,
                'completed_this_month' => $completed_this_month
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi truy vấn thống kê'], 500);
    }
}

function updateOverdueStatus() {
    global $db;
    
    // Update overdue maintenance plans
    $update_sql = "UPDATE ke_hoach_bao_tri 
                   SET trang_thai = 'qua_han' 
                   WHERE ngay_bao_tri_tiep_theo < CURDATE() 
                   AND trang_thai = 'chua_thuc_hien'";
    $db->exec($update_sql);
}

function createMaintenance() {
    global $db;
    
    try {
        $data = [
            'id_thiet_bi' => $_POST['id_thiet_bi'],
            'loai_bao_tri' => $_POST['loai_bao_tri'],
            'ten_ke_hoach' => cleanInput($_POST['ten_ke_hoach']),
            'mo_ta' => cleanInput($_POST['mo_ta']),
            'chu_ky_ngay' => $_POST['chu_ky_ngay'] ?: null,
            'ngay_bao_tri_tiep_theo' => $_POST['ngay_bao_tri_tiep_theo'],
            'nguoi_thuc_hien' => $_POST['nguoi_thuc_hien'] ?: null,
            'uu_tien' => $_POST['uu_tien'],
            'created_by' => $_SESSION['user_id']
        ];
        
        // Validate required fields
        if (empty($data['id_thiet_bi']) || empty($data['ten_ke_hoach']) || empty($data['ngay_bao_tri_tiep_theo'])) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
        }
        
        // Calculate next maintenance date based on cycle
        if ($data['chu_ky_ngay'] && $data['loai_bao_tri'] === 'dinh_ky') {
            $next_date = date('Y-m-d', strtotime($data['ngay_bao_tri_tiep_theo'] . ' + ' . $data['chu_ky_ngay'] . ' days'));
        }
        
        // Insert maintenance plan
        $sql = "INSERT INTO ke_hoach_bao_tri 
                (id_thiet_bi, loai_bao_tri, ten_ke_hoach, mo_ta, chu_ky_ngay, 
                 ngay_bao_tri_tiep_theo, nguoi_thuc_hien, uu_tien, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['loai_bao_tri'], $data['ten_ke_hoach'],
            $data['mo_ta'], $data['chu_ky_ngay'], $data['ngay_bao_tri_tiep_theo'],
            $data['nguoi_thuc_hien'], $data['uu_tien'], $data['created_by']
        ]);
        
        logActivity($_SESSION['user_id'], 'create_maintenance', "Tạo kế hoạch bảo trì: {$data['ten_ke_hoach']}");
        
        jsonResponse(['success' => true, 'message' => 'Tạo kế hoạch bảo trì thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi tạo kế hoạch bảo trì: ' . $e->getMessage()], 500);
    }
}

function updateMaintenance() {
    global $db;
    
    try {
        $id = $_POST['id'];
        $data = [
            'id_thiet_bi' => $_POST['id_thiet_bi'],
            'loai_bao_tri' => $_POST['loai_bao_tri'],
            'ten_ke_hoach' => cleanInput($_POST['ten_ke_hoach']),
            'mo_ta' => cleanInput($_POST['mo_ta']),
            'chu_ky_ngay' => $_POST['chu_ky_ngay'] ?: null,
            'ngay_bao_tri_tiep_theo' => $_POST['ngay_bao_tri_tiep_theo'],
            'nguoi_thuc_hien' => $_POST['nguoi_thuc_hien'] ?: null,
            'uu_tien' => $_POST['uu_tien']
        ];
        
        // Update maintenance plan
        $sql = "UPDATE ke_hoach_bao_tri SET 
                id_thiet_bi = ?, loai_bao_tri = ?, ten_ke_hoach = ?, mo_ta = ?, 
                chu_ky_ngay = ?, ngay_bao_tri_tiep_theo = ?, nguoi_thuc_hien = ?, uu_tien = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['id_thiet_bi'], $data['loai_bao_tri'], $data['ten_ke_hoach'],
            $data['mo_ta'], $data['chu_ky_ngay'], $data['ngay_bao_tri_tiep_theo'],
            $data['nguoi_thuc_hien'], $data['uu_tien'], $id
        ]);
        
        logActivity($_SESSION['user_id'], 'update_maintenance', "Cập nhật kế hoạch bảo trì ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật kế hoạch bảo trì thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật kế hoạch bảo trì: ' . $e->getMessage()], 500);
    }
}

function deleteMaintenance() {
    global $db;
    
    try {
        $id = $_POST['id'];
        
        // Check if maintenance has history
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM lich_su_bao_tri WHERE id_ke_hoach = ?");
        $check_stmt->execute([$id]);
        if ($check_stmt->fetch()['count'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Không thể xóa kế hoạch bảo trì đã có lịch sử thực hiện'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM ke_hoach_bao_tri WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_maintenance', "Xóa kế hoạch bảo trì ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Xóa kế hoạch bảo trì thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa kế hoạch bảo trì: ' . $e->getMessage()], 500);
    }
}

function executeMaintenanceWork() {
    global $db;
    
    try {
        $maintenance_id = $_POST['maintenance_id'];
        $data = [
            'id_ke_hoach' => $maintenance_id,
            'id_thiet_bi' => null, // Will be fetched from maintenance plan
            'loai_bao_tri' => null, // Will be fetched from maintenance plan
            'ten_cong_viec' => cleanInput($_POST['ten_cong_viec']),
            'mo_ta' => cleanInput($_POST['mo_ta']),
            'ngay_thuc_hien' => $_POST['ngay_thuc_hien'],
            'gio_bat_dau' => $_POST['gio_bat_dau'] ?: null,
            'gio_ket_thuc' => $_POST['gio_ket_thuc'] ?: null,
            'nguoi_thuc_hien' => $_SESSION['user_id'],
            'chi_phi' => $_POST['chi_phi'] ?: 0,
            'ket_qua' => cleanInput($_POST['ket_qua']),
            'ghi_chu' => cleanInput($_POST['ghi_chu']),
            'trang_thai' => $_POST['trang_thai']
        ];
        
        // Get maintenance plan details
        $plan_stmt = $db->prepare("SELECT id_thiet_bi, loai_bao_tri, chu_ky_ngay FROM ke_hoach_bao_tri WHERE id = ?");
        $plan_stmt->execute([$maintenance_id]);
        $plan = $plan_stmt->fetch();
        
        if (!$plan) {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy kế hoạch bảo trì'], 404);
        }
        
        $data['id_thiet_bi'] = $plan['id_thiet_bi'];
        $data['loai_bao_tri'] = $plan['loai_bao_tri'];
        
        // Process materials used
        $materials_used = [];
        if (isset($_POST['materials'])) {
            foreach ($_POST['materials'] as $material) {
                if (!empty($material['id_vat_tu']) && !empty($material['so_luong'])) {
                    $materials_used[] = [
                        'id_vat_tu' => $material['id_vat_tu'],
                        'so_luong' => $material['so_luong'],
                        'don_gia' => $material['don_gia'] ?: 0
                    ];
                }
            }
        }
        $data['vat_tu_su_dung'] = json_encode($materials_used);
        
        // Handle file uploads
        $uploaded_images = [];
        if (isset($_FILES['hinh_anh'])) {
            foreach ($_FILES['hinh_anh']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $file_info = [
                        'name' => $_FILES['hinh_anh']['name'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['hinh_anh']['error'][$key],
                        'size' => $_FILES['hinh_anh']['size'][$key]
                    ];
                    
                    $upload_result = uploadFile($file_info, 'uploads/maintenance/', ['jpg', 'jpeg', 'png']);
                    if ($upload_result['success']) {
                        $uploaded_images[] = $upload_result['file_path'];
                    }
                }
            }
        }
        $data['hinh_anh'] = implode(',', $uploaded_images);
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert maintenance history
            $sql = "INSERT INTO lich_su_bao_tri 
                    (id_ke_hoach, id_thiet_bi, loai_bao_tri, ten_cong_viec, mo_ta, 
                     ngay_thuc_hien, gio_bat_dau, gio_ket_thuc, nguoi_thuc_hien, 
                     chi_phi, vat_tu_su_dung, ket_qua, ghi_chu, hinh_anh, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['id_ke_hoach'], $data['id_thiet_bi'], $data['loai_bao_tri'],
                $data['ten_cong_viec'], $data['mo_ta'], $data['ngay_thuc_hien'],
                $data['gio_bat_dau'], $data['gio_ket_thuc'], $data['nguoi_thuc_hien'],
                $data['chi_phi'], $data['vat_tu_su_dung'], $data['ket_qua'],
                $data['ghi_chu'], $data['hinh_anh'], $data['trang_thai']
            ]);
            
            $history_id = $db->lastInsertId();
            
            // Update maintenance plan status and next date
            if ($data['trang_thai'] === 'hoan_thanh') {
                $next_date = null;
                if ($plan['chu_ky_ngay'] && $plan['loai_bao_tri'] === 'dinh_ky') {
                    $next_date = date('Y-m-d', strtotime($data['ngay_thuc_hien'] . ' + ' . $plan['chu_ky_ngay'] . ' days'));
                }
                
                $update_sql = "UPDATE ke_hoach_bao_tri SET 
                              trang_thai = 'hoan_thanh',
                              lan_bao_tri_cuoi = ?,
                              ngay_bao_tri_tiep_theo = ?
                              WHERE id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([$data['ngay_thuc_hien'], $next_date, $maintenance_id]);
                
                // Create new maintenance plan if cyclic
                if ($next_date && $plan['loai_bao_tri'] === 'dinh_ky') {
                    $new_plan_sql = "INSERT INTO ke_hoach_bao_tri 
                                    (id_thiet_bi, loai_bao_tri, ten_ke_hoach, mo_ta, chu_ky_ngay,
                                     ngay_bao_tri_tiep_theo, nguoi_thuc_hien, uu_tien, created_by)
                                    SELECT id_thiet_bi, loai_bao_tri, ten_ke_hoach, mo_ta, chu_ky_ngay,
                                           ?, nguoi_thuc_hien, uu_tien, ?
                                    FROM ke_hoach_bao_tri WHERE id = ?";
                    $new_plan_stmt = $db->prepare($new_plan_sql);
                    $new_plan_stmt->execute([$next_date, $_SESSION['user_id'], $maintenance_id]);
                }
            } else {
                // Update status to in progress
                $update_sql = "UPDATE ke_hoach_bao_tri SET trang_thai = 'dang_thuc_hien' WHERE id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([$maintenance_id]);
            }
            
            // Update inventory for materials used
            foreach ($materials_used as $material) {
                // Insert inventory transaction
                $inv_sql = "INSERT INTO xuat_nhap_kho 
                           (id_vat_tu, loai_giao_dich, so_luong, don_gia, ly_do, id_bao_tri, nguoi_thuc_hien, ghi_chu)
                           VALUES (?, 'xuat', ?, ?, 'Xuất cho bảo trì', ?, ?, ?)";
                $inv_stmt = $db->prepare($inv_sql);
                $inv_stmt->execute([
                    $material['id_vat_tu'], $material['so_luong'], $material['don_gia'],
                    $history_id, $_SESSION['user_id'], "Bảo trì: {$data['ten_cong_viec']}"
                ]);
                
                // Update stock quantity
                $stock_sql = "UPDATE ton_kho SET so_luong_ton = so_luong_ton - ? WHERE id_vat_tu = ?";
                $stock_stmt = $db->prepare($stock_sql);
                $stock_stmt->execute([$material['so_luong'], $material['id_vat_tu']]);
            }
            
            $db->commit();
            
            logActivity($_SESSION['user_id'], 'execute_maintenance', "Thực hiện bảo trì: {$data['ten_cong_viec']}");
            
            jsonResponse(['success' => true, 'message' => 'Lưu kết quả bảo trì thành công']);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi thực hiện bảo trì: ' . $e->getMessage()], 500);
    }
}

function getCalendarData() {
    global $db;
    
    $sql = "SELECT kh.id, kh.ten_ke_hoach, kh.ngay_bao_tri_tiep_theo, kh.uu_tien,
                   tb.id_thiet_bi, tb.ten_thiet_bi
            FROM ke_hoach_bao_tri kh
            JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            WHERE kh.trang_thai IN ('chua_thuc_hien', 'dang_thuc_hien')
            AND kh.ngay_bao_tri_tiep_theo >= CURDATE()
            AND kh.ngay_bao_tri_tiep_theo <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
            ORDER BY kh.ngay_bao_tri_tiep_theo";
    
    $stmt = $db->query($sql);
    $calendar_data = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $calendar_data]);
}

function exportMaintenance() {
    global $db;
    
    // Build query with filters
    $search = $_POST['search'] ?? '';
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_bao_tri = $_POST['loai_bao_tri'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? '';
    $uu_tien = $_POST['uu_tien'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(kh.ten_ke_hoach LIKE ? OR tb.id_thiet_bi LIKE ? OR tb.ten_thiet_bi LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "kh.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_bao_tri)) {
        $where_conditions[] = "kh.loai_bao_tri = ?";
        $params[] = $loai_bao_tri;
    }
    
    if (!empty($trang_thai)) {
        $where_conditions[] = "kh.trang_thai = ?";
        $params[] = $trang_thai;
    }
    
    if (!empty($uu_tien)) {
        $where_conditions[] = "kh.uu_tien = ?";
        $params[] = $uu_tien;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT tb.id_thiet_bi as 'ID Thiết bị', tb.ten_thiet_bi as 'Tên thiết bị',
                   kh.ten_ke_hoach as 'Tên kế hoạch', kh.loai_bao_tri as 'Loại bảo trì',
                   kh.chu_ky_ngay as 'Chu kỳ (ngày)', kh.ngay_bao_tri_tiep_theo as 'Ngày tiếp theo',
                   kh.trang_thai as 'Trạng thái', kh.uu_tien as 'Ưu tiên',
                   u.full_name as 'Người thực hiện', kh.mo_ta as 'Mô tả',
                   kh.created_at as 'Ngày tạo'
            FROM ke_hoach_bao_tri kh
            JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            LEFT JOIN users u ON kh.nguoi_thuc_hien = u.id
            $where_clause
            ORDER BY kh.ngay_bao_tri_tiep_theo ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Create CSV
    $filename = 'ke_hoach_bao_tri_' . date('Y-m-d_H-i-s') . '.csv';
    
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
function getEquipmentHistory() {
    global $db;
    
    $equipment_id = $_GET['equipment_id'];
    
    $sql = "SELECT lsbt.*, u.full_name as nguoi_thuc_hien_name
            FROM lich_su_bao_tri lsbt
            LEFT JOIN users u ON lsbt.nguoi_thuc_hien = u.id
            WHERE lsbt.id_thiet_bi = ?
            ORDER BY lsbt.ngay_thuc_hien DESC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$equipment_id]);
    $history = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $history]);
}

function getMaintenanceHistory() {
    global $db;
    
    $page = $_POST['page'] ?? 1;
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_bao_tri = $_POST['loai_bao_tri'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? '';
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "lsbt.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_bao_tri)) {
        $where_conditions[] = "lsbt.loai_bao_tri = ?";
        $params[] = $loai_bao_tri;
    }
    
    if (!empty($trang_thai)) {
        $where_conditions[] = "lsbt.trang_thai = ?";
        $params[] = $trang_thai;
    }
    
    if (!empty($from_date)) {
        $where_conditions[] = "lsbt.ngay_thuc_hien >= ?";
        $params[] = $from_date;
    }
    
    if (!empty($to_date)) {
        $where_conditions[] = "lsbt.ngay_thuc_hien <= ?";
        $params[] = $to_date;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM lich_su_bao_tri lsbt
                  JOIN thiet_bi tb ON lsbt.id_thiet_bi = tb.id
                  $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated data
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT lsbt.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   u.full_name as nguoi_thuc_hien_name
            FROM lich_su_bao_tri lsbt
            JOIN thiet_bi tb ON lsbt.id_thiet_bi = tb.id
            LEFT JOIN users u ON lsbt.nguoi_thuc_hien = u.id
            $where_clause
            ORDER BY lsbt.ngay_thuc_hien DESC
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $history,
        'pagination' => $pagination
    ]);
}

function getMaintenanceHistoryDetail() {
    global $db;
    
    $id = $_GET['id'];
    
    $sql = "SELECT lsbt.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   u.full_name as nguoi_thuc_hien_name
            FROM lich_su_bao_tri lsbt
            JOIN thiet_bi tb ON lsbt.id_thiet_bi = tb.id
            LEFT JOIN users u ON lsbt.nguoi_thuc_hien = u.id
            WHERE lsbt.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $history = $stmt->fetch();
    
    if ($history) {
        jsonResponse(['success' => true, 'data' => $history]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy lịch sử bảo trì'], 404);
    }
}

function exportMaintenanceHistory() {
    global $db;
    
    // Build query with filters (similar to getMaintenanceHistory)
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_bao_tri = $_POST['loai_bao_tri'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? '';
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "lsbt.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_bao_tri)) {
        $where_conditions[] = "lsbt.loai_bao_tri = ?";
        $params[] = $loai_bao_tri;
    }
    
    if (!empty($trang_thai)) {
        $where_conditions[] = "lsbt.trang_thai = ?";
        $params[] = $trang_thai;
    }
    
    if (!empty($from_date)) {
        $where_conditions[] = "lsbt.ngay_thuc_hien >= ?";
        $params[] = $from_date;
    }
    
    if (!empty($to_date)) {
        $where_conditions[] = "lsbt.ngay_thuc_hien <= ?";
        $params[] = $to_date;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT tb.id_thiet_bi as 'ID Thiết bị', tb.ten_thiet_bi as 'Tên thiết bị',
                   lsbt.ten_cong_viec as 'Công việc', lsbt.loai_bao_tri as 'Loại bảo trì',
                   lsbt.ngay_thuc_hien as 'Ngày thực hiện', 
                   CONCAT(lsbt.gio_bat_dau, ' - ', lsbt.gio_ket_thuc) as 'Thời gian',
                   u.full_name as 'Người thực hiện', lsbt.chi_phi as 'Chi phí',
                   lsbt.trang_thai as 'Trạng thái', lsbt.mo_ta as 'Mô tả',
                   lsbt.ket_qua as 'Kết quả', lsbt.ghi_chu as 'Ghi chú'
            FROM lich_su_bao_tri lsbt
            JOIN thiet_bi tb ON lsbt.id_thiet_bi = tb.id
            LEFT JOIN users u ON lsbt.nguoi_thuc_hien = u.id
            $where_clause
            ORDER BY lsbt.ngay_thuc_hien DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Create CSV
    $filename = 'lich_su_bao_tri_' . date('Y-m-d_H-i-s') . '.csv';
    
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
?>