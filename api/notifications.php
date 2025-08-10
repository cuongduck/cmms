<?php
// api/notifications.php - API thông báo cho header
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

try {
    $notifications = [];
    
    // Bảo trì sắp đến hạn (7 ngày tới)
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM ke_hoach_bao_tri 
        WHERE ngay_bao_tri_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
        AND trang_thai = 'chua_thuc_hien'
    ");
    $upcoming_maintenance = $stmt->fetch()['count'];
    
    if ($upcoming_maintenance > 0) {
        $notifications[] = [
            'title' => 'Bảo trì sắp đến hạn',
            'message' => "$upcoming_maintenance thiết bị cần bảo trì trong 7 ngày tới",
            'link' => 'modules/maintenance/',
            'type' => 'warning'
        ];
    }
    
    // Bảo trì quá hạn
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM ke_hoach_bao_tri 
        WHERE ngay_bao_tri_tiep_theo < CURDATE() 
        AND trang_thai = 'chua_thuc_hien'
    ");
    $overdue_maintenance = $stmt->fetch()['count'];
    
    if ($overdue_maintenance > 0) {
        $notifications[] = [
            'title' => 'Bảo trì quá hạn',
            'message' => "$overdue_maintenance thiết bị đã quá hạn bảo trì",
            'link' => 'modules/maintenance/',
            'type' => 'danger'
        ];
    }
    
    // Vật tư tồn kho thấp
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM ton_kho tk 
        JOIN vat_tu vt ON tk.id_vat_tu = vt.id 
        WHERE tk.so_luong_ton <= tk.so_luong_toi_thieu
    ");
    $low_stock = $stmt->fetch()['count'];
    
    if ($low_stock > 0) {
        $notifications[] = [
            'title' => 'Vật tư tồn kho thấp',
            'message' => "$low_stock loại vật tư có tồn kho thấp",
            'link' => 'modules/inventory/',
            'type' => 'warning'
        ];
    }
    
    // Hiệu chuẩn sắp đến hạn (30 ngày)
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM ke_hoach_hieu_chuan 
        WHERE ngay_hieu_chuan_tiep_theo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        AND trang_thai = 'chua_thuc_hien'
    ");
    $upcoming_calibration = $stmt->fetch()['count'];
    
    if ($upcoming_calibration > 0) {
        $notifications[] = [
            'title' => 'Hiệu chuẩn sắp đến hạn',
            'message' => "$upcoming_calibration thiết bị cần hiệu chuẩn trong 30 ngày tới",
            'link' => 'modules/calibration/',
            'type' => 'info'
        ];
    }
    
    // Yêu cầu công việc chờ duyệt (chỉ tổ trưởng và admin)
    if (hasPermission(['admin', 'to_truong'])) {
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM yeu_cau_cong_viec 
            WHERE trang_thai = 'cho_duyet'
        ");
        $pending_requests = $stmt->fetch()['count'];
        
        if ($pending_requests > 0) {
            $notifications[] = [
                'title' => 'Yêu cầu công việc chờ duyệt',
                'message' => "$pending_requests yêu cầu công việc cần được duyệt",
                'link' => 'modules/tasks/requests.php',
                'type' => 'primary'
            ];
        }
    }
    
    jsonResponse(['success' => true, 'data' => $notifications]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Lỗi tải thông báo'], 500);
}
?>