<?php
// modules/tasks/api.php - Task Management API
require_once '../../auth/check_auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);
        listTasks();
        break;
    case 'kanban':
        checkApiPermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);
        getKanbanData();
        break;
    case 'detail':
        checkApiPermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);
        getTaskDetail();
        break;
    case 'statistics':
        checkApiPermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);
        getTaskStatistics();
        break;
    case 'create':
        checkApiPermission(['admin', 'to_truong']);
        createTask();
        break;
    case 'update':
        checkApiPermission(['admin', 'to_truong']);
        updateTask();
        break;
    case 'delete':
        checkApiPermission(['admin', 'to_truong']);
        deleteTask();
        break;
    case 'update_progress':
        checkApiPermission(['admin', 'to_truong', 'user']);
        updateTaskProgress();
        break;
    case 'approve':
        checkApiPermission(['admin', 'truong_ca']);
        approveTask();
        break;
    case 'assign':
        checkApiPermission(['admin', 'to_truong']);
        assignTask();
        break;
    case 'get_users':
        getUsersList();
        break;
    case 'export':
        checkApiPermission(['admin', 'to_truong', 'truong_ca', 'user', 'viewer']);
        exportTasks();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listTasks() {
    global $db;
    
    $page = $_POST['page'] ?? 1;
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_cong_viec = $_POST['loai_cong_viec'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? '';
    $uu_tien = $_POST['uu_tien'] ?? '';
    $nguoi_duoc_giao = $_POST['nguoi_duoc_giao'] ?? '';
    $search = $_POST['search'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    // Handle special search terms
    if (!empty($search)) {
        if ($search === 'overdue') {
            $where_conditions[] = "kh.ngay_ket_thuc < CURDATE() AND kh.trang_thai NOT IN ('hoan_thanh', 'huy')";
        } elseif ($search === 'this_week') {
            $where_conditions[] = "WEEK(kh.ngay_ket_thuc) = WEEK(CURDATE()) AND YEAR(kh.ngay_ket_thuc) = YEAR(CURDATE())";
        } else {
            $where_conditions[] = "(kh.tieu_de LIKE ? OR kh.mo_ta LIKE ? OR tb.id_thiet_bi LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "kh.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_cong_viec)) {
        $where_conditions[] = "kh.loai_cong_viec = ?";
        $params[] = $loai_cong_viec;
    }
    
    if (!empty($trang_thai)) {
        $where_conditions[] = "kh.trang_thai = ?";
        $params[] = $trang_thai;
    }
    
    if (!empty($uu_tien)) {
        $where_conditions[] = "kh.uu_tien = ?";
        $params[] = $uu_tien;
    }
    
    if (!empty($nguoi_duoc_giao)) {
        $where_conditions[] = "kh.nguoi_duoc_giao = ?";
        $params[] = $nguoi_duoc_giao;
    }
    
    // Role-based filtering
    if ($_SESSION['user_role'] === 'user') {
        $where_conditions[] = "(kh.nguoi_duoc_giao = ? OR kh.nguoi_tao = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM ke_hoach_cong_viec kh
                  LEFT JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
                  $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    // Get paginated data
    $pagination = getPagination($page, $total_records);
    
    $sql = "SELECT kh.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   ut.full_name as nguoi_tao_name,
                   ug.full_name as nguoi_duoc_giao_name,
                   ux.full_name as nguoi_xac_nhan_name
            FROM ke_hoach_cong_viec kh
            LEFT JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            LEFT JOIN users ut ON kh.nguoi_tao = ut.id
            LEFT JOIN users ug ON kh.nguoi_duoc_giao = ug.id
            LEFT JOIN users ux ON kh.nguoi_xac_nhan = ux.id
            $where_clause
            ORDER BY 
                CASE 
                    WHEN kh.trang_thai = 'khan_cap' THEN 1
                    WHEN kh.ngay_ket_thuc < CURDATE() AND kh.trang_thai NOT IN ('hoan_thanh', 'huy') THEN 2
                    WHEN kh.trang_thai = 'dang_thuc_hien' THEN 3
                    ELSE 4
                END,
                kh.uu_tien = 'khan_cap' DESC,
                kh.ngay_ket_thuc ASC
            LIMIT {$pagination['records_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $tasks,
        'pagination' => $pagination
    ]);
}

function getKanbanData() {
    global $db;
    
    // Apply same filters as list but without pagination
    $equipment_id = $_POST['equipment_id'] ?? '';
    $loai_cong_viec = $_POST['loai_cong_viec'] ?? '';
    $uu_tien = $_POST['uu_tien'] ?? '';
    $nguoi_duoc_giao = $_POST['nguoi_duoc_giao'] ?? '';
    
    $where_conditions = ['1=1'];
    $params = [];
    
    if (!empty($equipment_id)) {
        $where_conditions[] = "kh.id_thiet_bi = ?";
        $params[] = $equipment_id;
    }
    
    if (!empty($loai_cong_viec)) {
        $where_conditions[] = "kh.loai_cong_viec = ?";
        $params[] = $loai_cong_viec;
    }
    
    if (!empty($uu_tien)) {
        $where_conditions[] = "kh.uu_tien = ?";
        $params[] = $uu_tien;
    }
    
    if (!empty($nguoi_duoc_giao)) {
        $where_conditions[] = "kh.nguoi_duoc_giao = ?";
        $params[] = $nguoi_duoc_giao;
    }
    
    // Role-based filtering
    if ($_SESSION['user_role'] === 'user') {
        $where_conditions[] = "(kh.nguoi_duoc_giao = ? OR kh.nguoi_tao = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT kh.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   ut.full_name as nguoi_tao_name,
                   ug.full_name as nguoi_duoc_giao_name,
                   ux.full_name as nguoi_xac_nhan_name
            FROM ke_hoach_cong_viec kh
            LEFT JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            LEFT JOIN users ut ON kh.nguoi_tao = ut.id
            LEFT JOIN users ug ON kh.nguoi_duoc_giao = ug.id
            LEFT JOIN users ux ON kh.nguoi_xac_nhan = ux.id
            $where_clause
            ORDER BY kh.ngay_ket_thuc ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $tasks]);
}

function getTaskDetail() {
    global $db;
    
    $id = $_GET['id'];
    
    // Get task details
    $sql = "SELECT kh.*, tb.id_thiet_bi, tb.ten_thiet_bi,
                   ut.full_name as nguoi_tao_name,
                   ug.full_name as nguoi_duoc_giao_name,
                   ux.full_name as nguoi_xac_nhan_name
            FROM ke_hoach_cong_viec kh
            LEFT JOIN thiet_bi tb ON kh.id_thiet_bi = tb.id
            LEFT JOIN users ut ON kh.nguoi_tao = ut.id
            LEFT JOIN users ug ON kh.nguoi_duoc_giao = ug.id
            LEFT JOIN users ux ON kh.nguoi_xac_nhan = ux.id
            WHERE kh.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        jsonResponse(['success' => false, 'message' => 'Không tìm thấy công việc'], 404);
    }
    
    // Get execution details
    $execution_sql = "SELECT ct.*, u.full_name as nguoi_thuc_hien_name
                      FROM chi_tiet_thuc_hien ct
                      LEFT JOIN users u ON ct.nguoi_thuc_hien = u.id
                      WHERE ct.id_cong_viec = ?
                      ORDER BY ct.ngay_thuc_hien DESC, ct.created_at DESC";
    $execution_stmt = $db->prepare($execution_sql);
    $execution_stmt->execute([$id]);
    $task['execution_details'] = $execution_stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $task]);
}

function getTaskStatistics() {
    global $db;
    
    try {
        $user_filter = '';
        $params = [];
        
        // Role-based filtering for statistics
        if ($_SESSION['user_role'] === 'user') {
            $user_filter = "AND (nguoi_duoc_giao = ? OR nguoi_tao = ?)";
            $params = [$_SESSION['user_id'], $_SESSION['user_id']];
        }
        
        // New tasks
        $new_sql = "SELECT COUNT(*) as count FROM ke_hoach_cong_viec WHERE trang_thai = 'moi_tao' $user_filter";
        $new_stmt = $db->prepare($new_sql);
        $new_stmt->execute($params);
        $new_tasks = $new_stmt->fetch()['count'];
        
        // Assigned tasks
        $assigned_sql = "SELECT COUNT(*) as count FROM ke_hoach_cong_viec WHERE trang_thai = 'da_giao' $user_filter";
        $assigned_stmt = $db->prepare($assigned_sql);
        $assigned_stmt->execute($params);
        $assigned_tasks = $assigned_stmt->fetch()['count'];
        
        // In progress tasks
        $progress_sql = "SELECT COUNT(*) as count FROM ke_hoach_cong_viec WHERE trang_thai = 'dang_thuc_hien' $user_filter";
        $progress_stmt = $db->prepare($progress_sql);
        $progress_stmt->execute($params);
        $progress_tasks = $progress_stmt->fetch()['count'];
        
        // Completed this week
        $completed_sql = "SELECT COUNT(*) as count FROM ke_hoach_cong_viec 
                         WHERE trang_thai = 'hoan_thanh' 
                         AND WEEK(updated_at) = WEEK(CURDATE()) 
                         AND YEAR(updated_at) = YEAR(CURDATE()) $user_filter";
        $completed_stmt = $db->prepare($completed_sql);
        $completed_stmt->execute($params);
        $completed_this_week = $completed_stmt->fetch()['count'];
        
        jsonResponse([
            'success' => true,
            'data' => [
                'new_tasks' => $new_tasks,
                'assigned_tasks' => $assigned_tasks,
                'progress_tasks' => $progress_tasks,
                'completed_this_week' => $completed_this_week
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi truy vấn thống kê'], 500);
    }
}

function createTask() {
    global $db;
    
    try {
        $data = [
            'tieu_de' => cleanInput($_POST['tieu_de']),
            'mo_ta' => cleanInput($_POST['mo_ta']),
            'id_thiet_bi' => $_POST['id_thiet_bi'] ?: null,
            'loai_cong_viec' => $_POST['loai_cong_viec'],
            'uu_tien' => $_POST['uu_tien'],
            'ngay_bat_dau' => $_POST['ngay_bat_dau'],
            'ngay_ket_thuc' => $_POST['ngay_ket_thuc'],
            'gio_du_kien' => $_POST['gio_du_kien'] ?: 1,
            'nguoi_tao' => $_SESSION['user_id'],
            'nguoi_duoc_giao' => $_POST['nguoi_duoc_giao'] ?: null,
            'nguoi_xac_nhan' => $_POST['nguoi_xac_nhan'] ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Validate required fields
        if (empty($data['tieu_de']) || empty($data['ngay_bat_dau']) || empty($data['ngay_ket_thuc'])) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc'], 400);
        }
        
        // Validate date range
        if (strtotime($data['ngay_bat_dau']) > strtotime($data['ngay_ket_thuc'])) {
            jsonResponse(['success' => false, 'message' => 'Ngày kết thúc phải sau ngày bắt đầu'], 400);
        }
        
        // Determine initial status
        $trang_thai = 'moi_tao';
        if ($data['nguoi_duoc_giao']) {
            $trang_thai = 'da_giao';
        }
        
        // Insert task
        $sql = "INSERT INTO ke_hoach_cong_viec 
                (tieu_de, mo_ta, id_thiet_bi, loai_cong_viec, uu_tien, ngay_bat_dau, ngay_ket_thuc, 
                 gio_du_kien, nguoi_tao, nguoi_duoc_giao, nguoi_xac_nhan, trang_thai, ghi_chu) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['tieu_de'], $data['mo_ta'], $data['id_thiet_bi'], $data['loai_cong_viec'],
            $data['uu_tien'], $data['ngay_bat_dau'], $data['ngay_ket_thuc'], $data['gio_du_kien'],
            $data['nguoi_tao'], $data['nguoi_duoc_giao'], $data['nguoi_xac_nhan'], $trang_thai, $data['ghi_chu']
        ]);
        
        logActivity($_SESSION['user_id'], 'create_task', "Tạo công việc: {$data['tieu_de']}");
        
        jsonResponse(['success' => true, 'message' => 'Tạo công việc thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi tạo công việc: ' . $e->getMessage()], 500);
    }
}

function updateTask() {
    global $db;
    
    try {
        $id = $_POST['id'];
        $data = [
            'tieu_de' => cleanInput($_POST['tieu_de']),
            'mo_ta' => cleanInput($_POST['mo_ta']),
            'id_thiet_bi' => $_POST['id_thiet_bi'] ?: null,
            'loai_cong_viec' => $_POST['loai_cong_viec'],
            'uu_tien' => $_POST['uu_tien'],
            'ngay_bat_dau' => $_POST['ngay_bat_dau'],
            'ngay_ket_thuc' => $_POST['ngay_ket_thuc'],
            'gio_du_kien' => $_POST['gio_du_kien'] ?: 1,
            'nguoi_duoc_giao' => $_POST['nguoi_duoc_giao'] ?: null,
            'nguoi_xac_nhan' => $_POST['nguoi_xac_nhan'] ?: null,
            'ghi_chu' => cleanInput($_POST['ghi_chu'])
        ];
        
        // Update task
        $sql = "UPDATE ke_hoach_cong_viec SET 
                tieu_de = ?, mo_ta = ?, id_thiet_bi = ?, loai_cong_viec = ?, uu_tien = ?, 
                ngay_bat_dau = ?, ngay_ket_thuc = ?, gio_du_kien = ?, nguoi_duoc_giao = ?, 
                nguoi_xac_nhan = ?, ghi_chu = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['tieu_de'], $data['mo_ta'], $data['id_thiet_bi'], $data['loai_cong_viec'],
            $data['uu_tien'], $data['ngay_bat_dau'], $data['ngay_ket_thuc'], $data['gio_du_kien'],
            $data['nguoi_duoc_giao'], $data['nguoi_xac_nhan'], $data['ghi_chu'], $id
        ]);
        
        logActivity($_SESSION['user_id'], 'update_task', "Cập nhật công việc ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Cập nhật công việc thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật công việc: ' . $e->getMessage()], 500);
    }
}

function deleteTask() {
    global $db;
    
    try {
        $id = $_POST['id'];
        
        // Check if task has execution details
        $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM chi_tiet_thuc_hien WHERE id_cong_viec = ?");
        $check_stmt->execute([$id]);
        if ($check_stmt->fetch()['count'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Không thể xóa công việc đã có tiến độ thực hiện'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM ke_hoach_cong_viec WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_task', "Xóa công việc ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Xóa công việc thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xóa công việc: ' . $e->getMessage()], 500);
    }
}

function updateTaskProgress() {
    global $db;
    
    try {
        $task_id = $_POST['task_id'];
        $data = [
            'id_cong_viec' => $task_id,
            'nguoi_thuc_hien' => $_SESSION['user_id'],
            'ngay_thuc_hien' => $_POST['ngay_thuc_hien'],
            'gio_bat_dau' => $_POST['gio_bat_dau'] ?: null,
            'gio_ket_thuc' => $_POST['gio_ket_thuc'] ?: null,
            'noi_dung_thuc_hien' => cleanInput($_POST['noi_dung_thuc_hien']),
            'ket_qua' => cleanInput($_POST['ket_qua']),
            'van_de_gap_phai' => cleanInput($_POST['van_de_gap_phai']),
            'trang_thai' => $_POST['trang_thai']
        ];
        
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
                    
                    $upload_result = uploadFile($file_info, 'uploads/tasks/', ['jpg', 'jpeg', 'png']);
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
            // Insert execution detail
            $sql = "INSERT INTO chi_tiet_thuc_hien 
                    (id_cong_viec, nguoi_thuc_hien, ngay_thuc_hien, gio_bat_dau, gio_ket_thuc, 
                     noi_dung_thuc_hien, ket_qua, van_de_gap_phai, vat_tu_su_dung, hinh_anh, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $data['id_cong_viec'], $data['nguoi_thuc_hien'], $data['ngay_thuc_hien'],
                $data['gio_bat_dau'], $data['gio_ket_thuc'], $data['noi_dung_thuc_hien'],
                $data['ket_qua'], $data['van_de_gap_phai'], $data['vat_tu_su_dung'],
                $data['hinh_anh'], $data['trang_thai']
            ]);
            
            $detail_id = $db->lastInsertId();
            
            // Update task status
            $task_status = 'dang_thuc_hien';
            if ($data['trang_thai'] === 'hoan_thanh') {
                $task_status = 'cho_xac_nhan'; // Needs approval
            } elseif ($data['trang_thai'] === 'gap_van_de') {
                $task_status = 'dang_thuc_hien'; // Still in progress but with issues
            }
            
            $update_sql = "UPDATE ke_hoach_cong_viec SET trang_thai = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->execute([$task_status, $task_id]);
            
            // Update inventory for materials used
            foreach ($materials_used as $material) {
                // Insert inventory transaction
                $inv_sql = "INSERT INTO xuat_nhap_kho 
                           (id_vat_tu, loai_giao_dich, so_luong, don_gia, ly_do, nguoi_thuc_hien, ghi_chu)
                           VALUES (?, 'xuat', ?, ?, 'Xuất cho công việc', ?, ?)";
                $inv_stmt = $db->prepare($inv_sql);
                $inv_stmt->execute([
                    $material['id_vat_tu'], $material['so_luong'], $material['don_gia'],
                    $_SESSION['user_id'], "Công việc ID: $task_id"
                ]);
                
                // Update stock quantity
                $stock_sql = "UPDATE ton_kho SET so_luong_ton = so_luong_ton - ? WHERE id_vat_tu = ?";
                $stock_stmt = $db->prepare($stock_sql);
                $stock_stmt->execute([$material['so_luong'], $material['id_vat_tu']]);
            }
            
            $db->commit();
            
            logActivity($_SESSION['user_id'], 'update_task_progress', "Cập nhật tiến độ công việc ID: $task_id");
            
            jsonResponse(['success' => true, 'message' => 'Cập nhật tiến độ thành công']);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi cập nhật tiến độ: ' . $e->getMessage()], 500);
    }
}

function approveTask() {
    global $db;
    
    try {
        $id = $_POST['id'];
        
        // Update task status to completed
        $stmt = $db->prepare("UPDATE ke_hoach_cong_viec SET trang_thai = 'hoan_thanh', nguoi_xac_nhan = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        logActivity($_SESSION['user_id'], 'approve_task', "Xác nhận hoàn thành công việc ID: $id");
        
        jsonResponse(['success' => true, 'message' => 'Xác nhận hoàn thành công việc thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi xác nhận công việc: ' . $e->getMessage()], 500);
    }
}

function assignTask() {
    global $db;
    
    try {
        $id = $_POST['id'];
        $nguoi_duoc_giao = $_POST['nguoi_duoc_giao'];
        
        $stmt = $db->prepare("UPDATE ke_hoach_cong_viec SET nguoi_duoc_giao = ?, trang_thai = 'da_giao' WHERE id = ?");
        $stmt->execute([$nguoi_duoc_giao, $id]);
        
        logActivity($_SESSION['user_id'], 'assign_task', "Giao công việc ID: $id cho user ID: $nguoi_duoc_giao");
        
        jsonResponse(['success' => true, 'message' => 'Giao công việc thành công']);
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Lỗi giao công việc: ' . $e->getMessage()], 500);
    }
}

function getUsersList() {
    global $db;
    
    $stmt = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();