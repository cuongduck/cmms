<?php
/**
 * Transactions List Page
 * /modules/inventory/transactions.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$partId = intval($_GET['part_id'] ?? 0);
$pageTitle = 'Lịch sử giao dịch';
$currentModule = 'inventory';
$moduleCSS = 'inventory';
$moduleJS = 'inventory-transactions';

requirePermission('inventory', 'view');

$sql = "SELECT t.* FROM transaction t 
        JOIN part_inventory_mapping pim ON t.ItemCode = pim.item_code
        WHERE pim.part_id = ?
        ORDER BY t.TransactionDate DESC";
$transactions = $db->fetchAll($sql, [$partId]);

require_once '../../includes/header.php';
?>

<h3>Lịch sử giao dịch</h3>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Loại</th>
                    <th>Số lượng</th>
                    <th>Giá trị</th>
                    <th>Lý do</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">Không có giao dịch</td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td><?php echo formatDateTime($trans['TransactionDate']); ?></td>
                    <td>
                        <span class="badge <?php echo getTransactionTypeClass($trans['TransactionType']); ?>">
                            <?php echo htmlspecialchars($trans['TransactionType']); ?>
                        </span>
                    </td>
                    <td class="<?php echo $trans['TransactedQty'] > 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($trans['TransactedQty'], 2); ?> <?php echo htmlspecialchars($trans['UOM']); ?>
                    </td>
                    <td><?php echo formatVND($trans['TransValue'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($trans['Reason']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>