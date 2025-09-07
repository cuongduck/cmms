<?php
/**
 * Transaction History API
 * /modules/inventory/api/transactions.php
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('inventory', 'view');

$type = $_GET['type'] ?? 'all';
$itemCode = $_GET['item_code'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $sql = "SELECT 
        t.*,
        p.part_name,
        p.category,
        CASE 
            WHEN p.id IS NOT NULL THEN 'Trong BOM'
            ELSE 'Ngoài BOM'
        END as bom_status
    FROM transaction t
    LEFT JOIN parts p ON t.ItemCode = p.part_code
    WHERE 1=1";
    
    $params = [];
    
    // Filter by item code if specified
    if (!empty($itemCode)) {
        $sql .= " AND t.ItemCode = ?";
        $params[] = $itemCode;
    }
    
    // Filter by transaction type
    switch ($type) {
        case 'in':
            $sql .= " AND t.TransactedQty > 0";
            break;
        case 'out':
            $sql .= " AND t.TransactedQty < 0";
            break;
        case 'item':
            // Already filtered by item code above
            break;
        case 'all':
        default:
            // No additional filter
            break;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as counted";
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'];
    
    // Add order and limit
    $sql .= " ORDER BY t.TransactionDate DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $transactions = $db->fetchAll($sql, $params);
    
    // Calculate summary
    $summaryData = [
        'total_in' => 0,
        'total_out' => 0,
        'total_value_in' => 0,
        'total_value_out' => 0,
        'transaction_count' => count($transactions)
    ];
    
    foreach ($transactions as $trans) {
        if ($trans['TransactedQty'] > 0) {
            $summaryData['total_in'] += $trans['TransactedQty'];
            $summaryData['total_value_in'] += $trans['TotalAmount'] ?? 0;
        } else {
            $summaryData['total_out'] += abs($trans['TransactedQty']);
            $summaryData['total_value_out'] += abs($trans['TotalAmount'] ?? 0);
        }
    }
    
    $pagination = paginate($total, $page, $per_page);
    
    ob_start();
    ?>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng nhập</h5>
                    <h3><?php echo number_format($summaryData['total_in'], 2); ?></h3>
                    <small><?php echo formatCurrency($summaryData['total_value_in']); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Tổng xuất</h5>
                    <h3><?php echo number_format($summaryData['total_out'], 2); ?></h3>
                    <small><?php echo formatCurrency($summaryData['total_value_out']); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Số giao dịch</h5>
                    <h3><?php echo number_format($total); ?></h3>
                    <small>Trang <?php echo $page; ?>/<?php echo $pagination['total_pages']; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Giá trị ròng</h5>
                    <h3><?php echo formatCurrency($summaryData['total_value_in'] - $summaryData['total_value_out']); ?></h3>
                    <small>Chênh lệch nhập/xuất</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link <?php echo $type === 'all' ? 'active' : ''; ?>" 
                    onclick="showTransactionHistory('all', '<?php echo $itemCode; ?>')">
                <i class="fas fa-list me-1"></i>Tất cả
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $type === 'in' ? 'active' : ''; ?>" 
                    onclick="showTransactionHistory('in', '<?php echo $itemCode; ?>')">
                <i class="fas fa-plus me-1 text-success"></i>Nhập kho
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $type === 'out' ? 'active' : ''; ?>" 
                    onclick="showTransactionHistory('out', '<?php echo $itemCode; ?>')">
                <i class="fas fa-minus me-1 text-danger"></i>Xuất kho
            </button>
        </li>
    </ul>
    
    <!-- Transactions Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>Số chứng từ</th>
                    <th>Ngày giao dịch</th>
                    <th>Loại giao dịch</th>
                    <th>Mã vật tư</th>
                    <th>Tên vật tư</th>
                    <th class="text-end">Số lượng</th>
                    <th>ĐVT</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                    <th>Phòng ban</th>
                    <th>Lý do</th>
                    <th>Loại vật tư</th>
                    <th>Người yêu cầu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="13" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Không có giao dịch nào
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td>
                        <code class="text-primary"><?php echo htmlspecialchars($trans['Number'] ?? '-'); ?></code>
                        <?php if ($trans['Status']): ?>
                            <span class="badge bg-<?php echo getTransactionStatusClass($trans['Status']); ?> ms-1">
                                <?php echo htmlspecialchars($trans['Status']); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?php echo formatDateTime($trans['TransactionDate']); ?></div>
                        <small class="text-muted"><?php echo date('H:i', strtotime($trans['TransactionDate'])); ?></small>
                    </td>
                    <td>
                        <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType']); ?>">
                            <?php echo htmlspecialchars($trans['TransactionType'] ?? '-'); ?>
                        </span>
                    </td>
                    <td>
                        <code><?php echo htmlspecialchars($trans['ItemCode']); ?></code>
                    </td>
                    <td>
                        <div class="fw-medium"><?php echo htmlspecialchars($trans['ItemDesc']); ?></div>
                        <?php if ($trans['part_name']): ?>
                            <small class="text-muted">BOM: <?php echo htmlspecialchars($trans['part_name']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <span class="fw-bold <?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($trans['TransactedQty'] > 0 ? '+' : '') . number_format($trans['TransactedQty'], 2); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($trans['UOM'] ?? '-'); ?></span>
                    </td>
                    <td class="text-end">
                        <?php echo number_format($trans['Price'] ?? 0, 0); ?> đ
                    </td>
                    <td class="text-end fw-bold">
                        <span class="<?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($trans['TotalAmount'] ?? 0, 0); ?> đ
                        </span>
                    </td>
                    <td>
                        <small><?php echo htmlspecialchars($trans['Department'] ?? '-'); ?></small>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($trans['Reason'] ?? '-'); ?></span>
                    </td>
                    <td>
                        <span class="badge <?php echo $trans['bom_status'] === 'Trong BOM' ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?php echo $trans['bom_status']; ?>
                        </span>
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
    
    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div>
            <small class="text-muted">
                Hiển thị <?php echo number_format(($page - 1) * $per_page + 1); ?> - 
                <?php echo number_format(min($page * $per_page, $total)); ?> 
                trong tổng số <?php echo number_format($total); ?> giao dịch
            </small>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($pagination['has_previous']): ?>
                <li class="page-item">
                    <button class="page-link" onclick="loadTransactionPage(<?php echo $pagination['previous_page']; ?>)">‹</button>
                </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($pagination['total_pages'], $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
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
    <?php endif; ?>
    
    <script>
    function loadTransactionPage(page) {
        const currentUrl = new URL(window.location.href);
        const type = '<?php echo $type; ?>';
        const itemCode = '<?php echo $itemCode; ?>';
        
        let url = '../api/transactions.php?type=' + type + '&page=' + page;
        if (itemCode) {
            url += '&item_code=' + encodeURIComponent(itemCode);
        }
        
        CMMS.ajax({
            url: url,
            method: 'GET',
            success: function(data) {
                document.getElementById('transactionContent').innerHTML = data.html;
            }
        });
    }
    </script>
    
    <?php
    $html = ob_get_clean();
    
    jsonResponse([
        'success' => true,
        'html' => $html,
        'summary' => $summaryData,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    error_log("Error in transactions API: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi tải dữ liệu giao dịch'
    ], 500);
}

// Helper functions
function getTransactionTypeClass($type) {
    $classes = [
        'Receipt' => 'bg-success',
        'Issue' => 'bg-danger', 
        'Transfer' => 'bg-info',
        'Adjustment' => 'bg-warning',
        'Return' => 'bg-secondary'
    ];
    return $classes[$type] ?? 'bg-primary';
}

function getTransactionStatusClass($status) {
    $classes = [
        'Completed' => 'success',
        'Pending' => 'warning',
        'Cancelled' => 'danger',
        'Draft' => 'secondary'
    ];
    return $classes[$status] ?? 'primary';
}