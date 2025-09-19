<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$month = $_POST['month'] ?? '';
$budgets = $_POST['budget'] ?? [];

if (empty($month)) {
    echo json_encode(['success' => false, 'message' => 'Tháng không hợp lệ']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Xóa budget cũ
    $db->execute("DELETE FROM monthly_budgets WHERE month = ?", [$month]);
    
    // Thêm budget mới
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];
    
    foreach ($budgets as $brandy => $amount) {
        $amount = floatval($amount);
        if ($amount > 0) {
            $db->execute(
                "INSERT INTO monthly_budgets (month, brandy, budget_amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$month, $brandy, $amount, $userId]
            );
        }
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Lưu thành công']);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
$budgetValues = $_POST['budget_value'] ?? [];
$budgetUnits = $_POST['budget_unit'] ?? [];

foreach ($budgetValues as $brandy => $value) {
    $value = floatval($value);
    $unit = floatval($budgetUnits[$brandy] ?? 1000000); // Mặc định triệu
    
    $finalAmount = $value * $unit;
    
    if ($finalAmount > 0) {
        $db->execute(
            "INSERT INTO monthly_budgets (month, brandy, budget_amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$month, $brandy, $finalAmount, $userId]
        );
    }
}
?>