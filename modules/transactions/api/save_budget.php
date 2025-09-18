<?php
/**
 * Save Budget API
 * /modules/transactions/api/save_budget.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('inventory', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$month = $_POST['month'] ?? '';
$budgets = $_POST['budget'] ?? [];

if (empty($month)) {
    jsonResponse(['success' => false, 'message' => 'Tháng không hợp lệ'], 400);
}

try {
    $db->beginTransaction();
    
    // Xóa budget cũ cho tháng này
    $deleteSql = "DELETE FROM monthly_budgets WHERE month = ?";
    $db->execute($deleteSql, [$month]);
    
    // Thêm budget mới
    $insertSql = "INSERT INTO monthly_budgets (month, brandy, budget_amount, created_by, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
    
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];
    
    foreach ($budgets as $brandy => $amount) {
        $amount = floatval($amount);
        if ($amount > 0) {
            $db->execute($insertSql, [$month, $brandy, $amount, $userId]);
        }
    }
    
    $db->commit();
    
    // Log activity
    logActivity('update_budget', 'transactions', "Cập nhật ngân sách tháng $month");
    
    jsonResponse([
        'success' => true,
        'message' => 'Cập nhật ngân sách thành công'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Budget save error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi lưu ngân sách'
    ], 500);
}
?>