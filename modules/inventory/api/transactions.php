<?php
/**
 * Transaction History API - Fixed Version
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Tắt hiển thị lỗi để không làm hỏng JSON

try {
    require_once '../../../config/config.php';
    require_once '../../../config/database.php';
    require_once '../../../config/auth.php';
    require_once '../../../config/functions.php'; // Thêm để có function paginate()
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $type = $_GET['type'] ?? 'all';
    $itemCode = $_GET['item_code'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $sql = "SELECT * FROM transaction WHERE 1=1";
    $params = [];

    if (!empty($itemCode)) {
        $sql .= " AND ItemCode = ?";
        $params[] = $itemCode;
    }
    
    if ($type == 'in') {
        $sql .= " AND COALESCE(TransactedQty, 0) > 0";
    } elseif ($type == 'out') {
        $sql .= " AND COALESCE(TransactedQty, 0) < 0";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as counted";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    
    // Add pagination
    $sql .= " ORDER BY TransactionDate DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $transactions = $db->fetchAll($sql, $params);
    
    // Calculate summary - Fix abs() với null
    $summaryData = [
        'total_in' => 0,
        'total_out' => 0,
        'total_value_in' => 0,
        'total_value_out' => 0,
        'transaction_count' => count($transactions)
    ];
    
    foreach ($transactions as $trans) {
        $qty = $trans['TransactedQty'] ?? 0;
        $amount = $trans['TotalAmount'] ?? 0;
        
        if ($qty > 0) {
            $summaryData['total_in'] += $qty;
            $summaryData['total_value_in'] += $amount;
        } else {
            $summaryData['total_out'] += abs($qty); // $qty đã được đảm bảo không null
            $summaryData['total_value_out'] += abs($amount); // $amount đã được đảm bảo không null
        }
    }
    
    // Simple pagination object thay vì dùng function paginate()
    $totalPages = ceil($total / $per_page);
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $total,
        'per_page' => $per_page,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages,
        'previous_page' => $page - 1,
        'next_page' => $page + 1
    ];
    
    ob_start();
    ?>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng nhập</h5>
                    <h3><?php echo number_format($summaryData['total_in'], 2); ?></h3>
                    <small><?php echo number_format($summaryData['total_value_in'], 0); ?> đ</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng xuất</h5>
                    <h3><?php echo number_format($summaryData['total_out'], 2); ?></h3>
                    <small><?php echo number_format($summaryData['total_value_out'], 0); ?> đ</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Số giao dịch</h5>
                    <h3><?php echo number_format($total); ?></h3>
                    <small>Trang <?php echo $page; ?>/<?php echo $totalPages; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Giá trị ròng</h5>
                    <h3><?php echo number_format($summaryData['total_value_in'] - $summaryData['total_value_out'], 0); ?> đ</h3>
                    <small>Chênh lệch nhập/xuất</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter buttons -->
    <div class="mb-3">
        <div class="btn-group" role="group">
            <button type="button" class="btn <?php echo $type === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>" 
                    onclick="filterTransactions('all', '<?php echo $itemCode; ?>')">
                Tất cả
            </button>
            <button type="button" class="btn <?php echo $type === 'in' ? 'btn-success' : 'btn-outline-success'; ?>" 
                    onclick="filterTransactions('in', '<?php echo $itemCode; ?>')">
                Nhập kho
            </button>
            <button type="button" class="btn <?php echo $type === 'out' ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                    onclick="filterTransactions('out', '<?php echo $itemCode; ?>')">
                Xuất kho
            </button>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>Ngày giao dịch</th>
                    <th>Mã vật tư</th>
                    <th>Tên vật tư</th>
                    <th class="text-end">Số lượng</th>
                    <th>ĐVT</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                    <th>Loại</th>
                    <th>Lý do</th>
                    <th>Người yêu cầu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Không có giao dịch nào
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td>
                        <div><?php echo date('d/m/Y', strtotime($trans['TransactionDate'] ?? 'now')); ?></div>
                        <small class="text-muted"><?php echo date('H:i', strtotime($trans['TransactionDate'] ?? 'now')); ?></small>
                    </td>
                    <td>
                        <code><?php echo htmlspecialchars($trans['ItemCode'] ?? ''); ?></code>
                    </td>
                    <td>
                        <div class="fw-medium"><?php echo htmlspecialchars($trans['ItemDesc'] ?? ''); ?></div>
                    </td>
                    <td class="text-end">
                        <?php $qty = $trans['TransactedQty'] ?? 0; ?>
                        <span class="fw-bold <?php echo $qty > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($qty > 0 ? '+' : '') . number_format($qty, 2); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($trans['UOM'] ?? '-'); ?></span>
                    </td>
                    <td class="text-end">
                        <?php echo number_format($trans['Price'] ?? 0, 0); ?> đ
                    </td>
                    <td class="text-end fw-bold">
                        <?php $amount = $trans['TotalAmount'] ?? 0; ?>
                        <span class="<?php echo $qty > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($amount, 0); ?> đ
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $qty > 0 ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($trans['TransactionType'] ?? '-'); ?>
                        </span>
                    </td>
                    <td>
                        <small><?php echo htmlspecialchars($trans['Reason'] ?? '-'); ?></small>
                    </td>
                    <td>
                        <small><?php echo htmlspecialchars($trans['Requester'] ?? '-'); ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Simple Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-3">
        <nav>
            <ul class="pagination pagination-sm">
                <?php if ($pagination['has_previous']): ?>
                <li class="page-item">
                    <button class="page-link" onclick="loadTransactionPage(<?php echo $pagination['previous_page']; ?>)">‹</button>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <button class="page-link" onclick="loadTransactionPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                </li>
                <?php endfor; ?>
                
                <?php if ($pagination['has_next']): ?>
                <li class="page-item">
                    <button class="page-link" onclick="loadTransactionPage(<?php echo $pagination['next_page']; ?>)">›</button>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    
    <div class="text-center mt-2">
        <small class="text-muted">
            Hiển thị <?php echo number_format(($page - 1) * $per_page + 1); ?> - 
            <?php echo number_format(min($page * $per_page, $total)); ?> 
            trong tổng số <?php echo number_format($total); ?> giao dịch
        </small>
    </div>
    <?php endif; ?>
    
    <script>
    function filterTransactions(type, itemCode) {
        let url = 'api/transactions.php?type=' + type;
        if (itemCode) {
            url += '&item_code=' + encodeURIComponent(itemCode);
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('transactionContent').innerHTML = data.html;
                } else {
                    alert('Có lỗi: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function loadTransactionPage(page) {
        const type = '<?php echo $type; ?>';
        const itemCode = '<?php echo $itemCode; ?>';
        
        let url = 'api/transactions.php?type=' + type + '&page=' + page;
        if (itemCode) {
            url += '&item_code=' + encodeURIComponent(itemCode);
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('transactionContent').innerHTML = data.html;
                } else {
                    alert('Có lỗi: ' + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    </script>
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'summary' => $summaryData,
        'pagination' => $pagination
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Transaction API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>